<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\SubIndicator;
use App\Models\Indicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BigQueryService;
use Illuminate\Support\Facades\Log;

class ThemeController extends Controller
{
    /**
     * Helper function to fetch and format charts based on new schema:
     * - Table: charts (contains field_config JSON)
     * - Table: chart_types (contains name/slug)
     */
    /**
     * Helper function to fetch and format charts based on new schema:
     * - Table: charts (contains field_config JSON)
     * - Table: chart_types (contains name/slug)
     */

    private function formatChartsData($indicatorId)
    {
        $charts = DB::table('charts as c')
            ->leftJoin('chart_types as ct', 'c.chart_type_id', '=', 'ct.id')
            ->where('c.indicator_id', $indicatorId)
            ->select(
                'c.id',
                'c.chart_name',
                'c.source',
                'c.field_config',
                'ct.slug as chart_type_slug',
                'ct.name as chart_type_name'
            )
            ->orderBy('c.display_order', 'asc')
            ->get();

        $formattedCharts = [];

        foreach ($charts as $chart) {
            $fieldConfig = [];

            if (!empty($chart->field_config)) {
                $decoded = is_string($chart->field_config)
                    ? json_decode($chart->field_config, true)
                    : $chart->field_config;

                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }

                $fieldConfig = is_array($decoded) ? $decoded : [];
            }

            $fieldsConfiguration = [];

            foreach ($fieldConfig as $labelKey => $config) {

                if (!is_array($config)) {
                    if ($config !== null && $config !== '') {
                        $fieldsConfiguration[$labelKey] = $config;
                    }
                    continue;
                }

                $rawValues = $config['values'] ?? $config['field_value'] ?? [];
                if (is_string($rawValues)) {
                    $rawValues = json_decode($rawValues, true) ?: [];
                }
                $values = is_array($rawValues) ? $rawValues : [];

                $rawColors = $config['filter_colors'] ?? $config['colors'] ?? [];
                if (is_string($rawColors)) {
                    $rawColors = json_decode($rawColors, true) ?: [];
                }
                $colorsMap = is_array($rawColors) ? $rawColors : [];
                // dd($colorsMap);

                $keyName = $config['key'] ?? $config['indicator_key'] ?? $config['field_key'] ?? '';

                // Empty configuration slots ignore karne ke liye check
                if (empty($values) && empty($keyName) && empty($config['isFilter']) && empty($config['filter']) && empty($config['is_chart_filter'])) {
                    continue;
                }

                $formattedValues = [];
                foreach ($values as $val) {
                    $valStr = (string) $val;
                    $trimmedValStr = trim($valStr);

                    $color = '#3b82f6'; // Default color fallback

                    // 1. Direct match check
                    if (isset($colorsMap[$valStr]) && !empty($colorsMap[$valStr])) {
                        $color = $colorsMap[$valStr];
                    }
                    // 2. Fallback: Lookup after trimming keys from the saved colors map
                    else {
                        foreach ($colorsMap as $colorKey => $colorHex) {
                            if (trim((string) $colorKey) === $trimmedValStr && !empty($colorHex)) {
                                $color = $colorHex;
                                break;
                            }
                        }
                    }

                    $formattedValues[] = [
                        'value' => $valStr,
                        'color' => (string) $color
                    ];
                }

                // Formatted Indicator Field Structure
                $fieldsConfiguration[$labelKey] = [
                    'key' => (string) $keyName,
                    'isFilter' => filter_var($config['isFilter'] ?? $config['filter'] ?? $config['is_chart_filter'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'isMultiple' => filter_var($config['isMultiple'] ?? $config['multiple_select'] ?? $config['is_multiple'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'isDefaultSelected' => filter_var($config['isDefaultSelected'] ?? $config['default_first_value'] ?? $config['is_default_selected'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'values' => $formattedValues
                ];
            }

            $formattedCharts[] = [
                'id' => (int) $chart->id,
                'chart_name' => $chart->chart_name,
                'chart_source' => $chart->source,
                'chart_type' => $chart->chart_type_slug ?? $chart->chart_type_name ?? '',
                'fields_configuration' => $fieldsConfiguration
            ];
        }

        return $formattedCharts;
    }

    /**
     * API 1: /api/themes
     */
    public function index(Request $request)
    {
        $themes = Theme::select('id', 'name')->get();

        return response()->json($themes);
    }


    public function theme_data_sources(Request $request)
    {
        $query = DB::table('themes');

        if ($request->filled('theme_id')) {
            $query->where('id', $request->theme_id);
        }

        $themes = $query->get();

        if ($themes->isEmpty()) {
            return response()->json($request->filled('theme_id') ? ['message' => 'Theme not found'] : [], 200);
        }

        $result = [];

        foreach ($themes as $theme) {
            // Parse JSON columns from themes table
            $dataSourceIds = !empty($theme->data_source_ids)
                ? (is_array($theme->data_source_ids) ? $theme->data_source_ids : json_decode($theme->data_source_ids, true))
                : [];

            $indicatorMapping = !empty($theme->indicator_ids)
                ? (is_array($theme->indicator_ids) ? $theme->indicator_ids : json_decode($theme->indicator_ids, true))
                : [];

            if (empty($dataSourceIds)) {
                continue;
            }

            // Fetch data sources
            $dataSources = DB::table('data_sources')
                ->whereIn('id', $dataSourceIds)
                ->get();

            $sourcesList = [];

            foreach ($dataSources as $source) {
                // Find indicators linked to this data_source inside theme's JSON
                $indicatorIds = $indicatorMapping[$source->id] ?? $indicatorMapping[(string) $source->id] ?? [];

                $indicators = [];
                if (!empty($indicatorIds)) {
                    $indicatorRecords = DB::table('indicators')
                        ->whereIn('id', $indicatorIds)
                        ->get();



                    foreach ($indicatorRecords as $ind) {
                        $chartsList = $this->formatChartsData($ind->id);
                        // dd($ind);// 1. Pehle JSON string ko PHP array me convert karein
                        $indicatorSourceArray = json_decode($request->indicator_source, true);

                        $indicatorSourceArray = json_decode($ind->source, true);

                        // 2. Dynamic theme_id ke hisab se value nikalein
                        $themeId = $request->theme_id;
                        $themeValue = $indicatorSourceArray[$themeId] ?? $source->title;
                        $indicators[] = [
                            'id' => (int) $ind->id,
                            'name' => $ind->name,
                            'alice_name' => $ind->alias,
                            'indicator_source' => $themeValue['text'] ?? null,
                            'indicator_order'  => $themeValue['order'] ?? 0,
                            'charts' => $chartsList
                        ];
                    }
                }

                $sourcesList[] = [
                    'id' => (int) $source->id,
                    'name' => $source->dataset_id ?? $source->title ?? '',
                    'description' => $source->title ?? '',
                    'indicators' => $indicators
                ];
            }

            $result[] = $sourcesList;
        }

        return response()->json($request->filled('theme_id') ? ($result[0] ?? ['message' => 'Theme not found']) : $result);
    }




    public function getThemeWithIndicators(Request $request, BigQueryService $bigQueryService)
    {
        $startTime = microtime(true);

        $indicatorId = $request->get('indicator');
        if (!$indicatorId) {
            return response()->json(['message' => 'indicator parameter is required'], 400);
        }

        // 1. Check karein ki user ne pagination params pass kiye hain ya nahi
        $isPaginated = $request->has('per_page') || $request->has('page');
       
        $perPage = $isPaginated ? max(1, (int) $request->get('per_page', 10000)) : null;
        $page = $isPaginated ? max(1, (int) $request->get('page', 1)) : null;
 
        // 2. Prepare payload
        $queryParams = [
            'indicator' => $indicatorId,
            'source' => $request->get('source'),
            'state_id' => $request->get('state_id'),
            'year' => $request->get('year'),
            'per_page' => $perPage, // null rahega agar request me nahi hai
            'page' => $page,    // null rahega agar request me nahi hai
        ];

        $currentIndicator = Indicator::find($indicatorId);
        if ($currentIndicator && !empty($currentIndicator->parent_id)) {
            $queryParams['indicator'] = $currentIndicator->parent_id;
        }

        // 3. BigQuery Data Fetch
        $bqResult = $bigQueryService->getIndicatorData($queryParams);

        if (!$bqResult || !isset($bqResult['data'])) {
            \Log::error('Theme Indicators Pipeline Error: Service returned invalid payload or crashed.', [
                'query_params' => $queryParams
            ]);
            return response()->json(['message' => 'No data retrieved from BigQueryService'], 500);
        }

        $dataList = [];
        $rows = $bqResult['data'];
        $sampleFilters = [];


        // 4. Process Rows and Flatten JSON Filters
        foreach ($rows as $index => $row) {
            // Log::info("Row Index {$index} Full Data: " . json_encode($row, JSON_PRETTY_PRINT));
            $item = [
                'value' => (float) ($row['value'] ?? 0),
                'state_id' => (int) ($row['state_id'] ?? 0),
                'year' => (string) ($row['year'] ?? ''),
            ];

            if (!empty($row['additional_filters']) && $row['additional_filters'] !== 'null') {
                $additionalFilters = json_decode($row['additional_filters'], true);
                if (is_array($additionalFilters)) {
                    foreach ($additionalFilters as $key => $val) {
                        if ($key !== '' && $val !== null) {
                            $item[$key] = $val;
                        }
                    }
                }
            }

            if ($index < 3) {
                // dd($index);
                $sampleFilters[] = [
                    'raw_additional_filters' => $row['additional_filters'] ?? null,
                    'parsed_item_keys' => array_keys($item)
                ];
            }

            $dataList[] = $item;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

       $subIndicators = SubIndicator::where('indicator_id', $indicatorId)
            ->whereNotNull('alias_name')
            ->where('alias_name', '!=', '')
            ->pluck('alias_name', 'name');


        $data_alice = [
            'sub_indicator' => $subIndicators,
            ];
        

        // 5. Response Builder
        $response = [
            'data' => $dataList,
            'dataset_alias'=> $data_alice
            
        ];

        // Agar pagination query me bheja tha tabhi pagination block include karein
        if ($isPaginated) {
            $totalRecords = (int) ($bqResult['total'] ?? count($dataList));
            $effectivePerPage = (int) ($bqResult['per_page'] ?? $perPage);
            $currentPage = (int) ($bqResult['current_page'] ?? $page);
            $lastPage = $bqResult['last_page'] ?? (int) ceil($totalRecords / max(1, $effectivePerPage));

            $response['pagination'] = [
                'total' => $totalRecords,
                'per_page' => $effectivePerPage,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
            ];
        }

        return response()->json($response);
    }



    public function show($id)
    {
        $theme = Theme::find($id);

        if (!$theme) {
            return response()->json(['status' => 'error', 'message' => 'Theme not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $theme]);
    }


}