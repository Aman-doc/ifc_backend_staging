<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chart;
use App\Models\ChartType;
use App\Models\Theme;
use App\Models\Indicator;
use App\Services\BigQueryService;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    /**
     * BigQuery Service Instance
     */
    protected BigQueryService $bigQueryService;

    /**
     * Inject BigQueryService dependency
     */
    public function __construct(BigQueryService $bigQueryService)
    {
        $this->bigQueryService = $bigQueryService;
    }

    /**
     * Display a listing of indicators
     */
  /**
     * Display a listing of indicators based on theme JSON mapping.
     */
    public function index(Request $request)
    {
        // 1. Get all themes for dropdown
        $themes = Theme::orderBy('name')->get();

        // 2. Base query
        $query = Indicator::with(['dataSource', 'charts.chartType']);

        // 3. Filter by selected theme if present
        if ($request->filled('theme_id')) {
            $theme = Theme::find($request->theme_id);

            if ($theme) {
                // JSON structure format: {"14": ["350"], "33": ["2", "5"]} 
                // where Key = data_source_id and Value = array of indicator IDs/Codes
                $rawIndicatorIds = is_string($theme->indicator_ids) 
                    ? json_decode($theme->indicator_ids, true) 
                    : $theme->indicator_ids;

                if (is_array($rawIndicatorIds) && !empty($rawIndicatorIds)) {
                    // Strict composite WHERE matching (data_source_id AND indicator ID/Code)
                    $query->where(function ($q) use ($rawIndicatorIds) {
                        foreach ($rawIndicatorIds as $sourceId => $ids) {
                            if (is_array($ids) && !empty($ids)) {
                                $q->orWhere(function ($subQ) use ($sourceId, $ids) {
                                    $subQ->where('data_source_id', (int) $sourceId)
                                         ->where(function ($idMatchQ) use ($ids) {
                                             $idMatchQ->whereIn('id', $ids)
                                                      ->orWhereIn('indicator_code', $ids);
                                         });
                                });
                            }
                        }
                    });
                } else {
                    // Empty JSON state
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Invalid Theme ID
                $query->whereRaw('1 = 0');
            }
        }

        $indicators = $query->latest()->get();

        return view('admin.charts.index', compact('indicators', 'themes'));
    }

    /**
     * Show the form for creating a new chart resource.
     */
    public function create(Request $request, $indicatorId = null)
    {
        $startTime = microtime(true);
        $indicatorId = $indicatorId ?? $request->query('indicator_id');

        if (!$indicatorId) {
            \Log::warning('Chart Creation Aborted: Indicator ID is missing from the request context.', [
                'url' => $request->fullUrl(),
                'ip'  => $request->ip()
            ]);
            return redirect()->back()->with('error', 'Indicator ID missing hai.');
        }

        \Log::info('Chart Creation Pipeline Started.', ['resolved_indicator_id' => $indicatorId]);

        // 1. Fetch Indicator along with its data source and parent relations
        try {
            $indicator = Indicator::with(['dataSource', 'parent'])->findOrFail($indicatorId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Chart Creation Database Error: Indicator not found in MySQL.', [
                'indicator_id' => $indicatorId,
                'message'      => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Indicator database mein nahi mila.');
        }

        // 2. Configurations aur dynamic details extract karein
        $dataSource   = $indicator->dataSource;
        $dataSourceId = (int) $indicator->data_source_id; 
        $datasetId    = config('services.bigquery.dataset');
        $tableId      = config('services.bigquery.table');

        if (!$datasetId || !$tableId) {
            \Log::warning('BigQuery Environment Configurations Missing or Incomplete.', [
                'resolved_dataset' => $datasetId ?? 'NULL (Using fallback in service)',
                'resolved_table'   => $tableId ?? 'NULL (Using fallback in service)'
            ]);
        }

        // 3. Hierarchy Identification Logic - FIXED FOR EXISTING INDICATORS
        // BigQuery table dynamically uses numeric IDs matching target definitions.
        if (!empty($indicator->parent_id)) {
            // Case A: Explicit child indicator -> Use the parent's actual database ID
            $targetIdentifier = $indicator->parent_id;
        } else {
            // Fallback Default: Use its own standalone master database ID to match stored row records
            $targetIdentifier = $indicator->id;
        }

        \Log::info('Chart Creation Target Identifier Resolved.', [
            'indicator_id'     => $indicator->id,
            'indicator_code'   => $indicator->indicator_code,
            'parent_id'        => $indicator->parent_id,
            'data_source_id'   => $dataSourceId,
            'target_used'      => $targetIdentifier // Correctly yields 656 instead of "16" now
        ]);

        // 4. Dynamic BigQuery Filters fetch karein passing the corrected targetIdentifier
        $bqFilters = $this->bigQueryService->getIndicatorFilters(
            $targetIdentifier,
            $dataSourceId, 
            $datasetId, 
            $tableId
        ) ?? ['filter_data' => [], 'keys' => []];

        // 5. Chart types fetch karein
        $chartTypes = ChartType::all();

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        \Log::info('Admin Chart Create View Rendered Successfully.', [
            'indicator_id'      => $indicator->id,
            'target_identifier' => $targetIdentifier,
            'data_source_id'    => $dataSourceId,
            'execution_time_ms' => $executionTime
        ]);

        return view('admin.charts.create', compact('indicator', 'chartTypes', 'bqFilters'));
    }
 
    public function store(Request $request)
    {
        // 1. Validation (Added 'source' rule)
        $validated = $request->validate([
            'indicator_id'  => 'required|exists:indicators,id',
            'chart_type_id' => 'required|exists:chart_types,id',
            'chart_name'    => 'required|string|max:255',
            'source'        => 'nullable|string|max:255', // Added Source Validation
            'display_order' => 'nullable|integer',
            'config'        => 'nullable|array',
        ]);

        $rawConfig = $request->input('config', []);
        $cleanedConfig = $this->sanitizeConfig($rawConfig);

        // 3. DB Entry (Added 'source' to array)
        Chart::create([
            'indicator_id'  => $validated['indicator_id'],
            'chart_type_id' => $validated['chart_type_id'],
            'chart_name'    => $validated['chart_name'],
            'source'        => $validated['source'] ?? null, // Saved to DB
            'display_order' => $request->input('display_order', 0),
            'field_config'  => $cleanedConfig,
        ]);

        return redirect()->route('admin.charts.index')->with('success', 'Chart configuration saved successfully!');
    }

    public function update(Request $request, Chart $chart)
    {
        // Form Validation (Added 'source' rule)
        $request->validate([
            'chart_name'    => 'required|string|max:255',
            'source'        => 'nullable|string|max:255', // Added Source Validation
            'chart_type_id' => 'required|exists:chart_types,id',
            'display_order' => 'nullable|integer',
            'config'        => 'nullable|array'
        ]);

        $rawConfig = $request->input('config', []);
        $cleanedConfig = $this->sanitizeConfig($rawConfig);

        // Update Chart (Added 'source' update payload)
        $chart->update([
            'chart_name'    => $request->chart_name,
            'source'        => $request->source ?? null, // Updated in DB
            'chart_type_id' => $request->chart_type_id,
            'display_order' => $request->display_order ?? 0,
            'field_config'  => $cleanedConfig, // Sanitized array save hoga
        ]);

        return redirect()->route('admin.charts.index')
            ->with('success', 'Chart configuration updated successfully!');
    }

   /**
     * Helper method to clean and format field configuration
     */
    private function sanitizeConfig(array $rawConfig): array
    {
        $cleanedConfig = [];

        foreach ($rawConfig as $fieldKey => $fieldValue) {

            // Agar value array nahi hai (text, select, radio, etc.)
            if (!is_array($fieldValue)) {
                $cleanedConfig[$fieldKey] = $fieldValue;
                continue;
            }

            // CHECK: Indicator Type field verification
            $isIndicatorField = isset($fieldValue['indicator_key']) || isset($fieldValue['key']) || isset($fieldValue['values']) || isset($fieldValue['filter']) || isset($fieldValue['hide']);

            if (!$isIndicatorField) {
                $cleanedConfig[$fieldKey] = $fieldValue;
                continue;
            }

            $cleanedField = [];

            // Save key / indicator_key properly for API response
            $indicatorKey = $fieldValue['indicator_key'] ?? $fieldValue['key'] ?? '';
            $cleanedField['key'] = $indicatorKey;
            $cleanedField['indicator_key'] = $indicatorKey;

            $selectedValues = $fieldValue['values'] ?? [];
            $cleanedField['values'] = array_values($selectedValues);

            $cleanedField['default_first_value'] = isset($fieldValue['default_first_value']) && $fieldValue['default_first_value'] == '1';
            $cleanedField['filter'] = isset($fieldValue['filter']) && $fieldValue['filter'] == '1';
            $cleanedField['multiple_select'] = isset($fieldValue['multiple_select']) && $fieldValue['multiple_select'] == '1';
            $cleanedField['hide'] = isset($fieldValue['hide']) && $fieldValue['hide'] == '1'; // ADDED: Hide Flag

            // FIXED: Space matching resolution for color array saving
            if (isset($fieldValue['colors']) && is_array($fieldValue['colors'])) {
                $cleanedColors = [];
                
                // Trim selected values for foolproof strict comparison array
                $trimmedSelectedValues = array_map(function($v) {
                    return trim((string)$v);
                }, $selectedValues);

                foreach ($fieldValue['colors'] as $valName => $colorHex) {
                    $cleanValName = trim((string)$valName);
                    
                    // Ab check original string aur trimmed string dono ko support karega
                    if ((in_array($valName, $selectedValues) || in_array($cleanValName, $trimmedSelectedValues)) && !empty($colorHex)) {
                        $cleanedColors[$valName] = strtoupper($colorHex);
                    }
                }
                $cleanedField['colors'] = $cleanedColors;
            } else {
                $cleanedField['colors'] = [];
            }

            $cleanedConfig[$fieldKey] = $cleanedField;
        }

        return $cleanedConfig;
    }

   public function edit(Chart $chart) 
    {
        // 1. JSON Configuration parse karein
        if (is_string($chart->field_config)) {
            $savedConfig = json_decode($chart->field_config, true) ?? [];
        } else {
            $savedConfig = $chart->field_config ?? [];
        }
        $chart->field_config = $savedConfig;

        // 2. Chart se associated Indicator relation with parent load karein
        $indicator = $chart->indicator ?? Indicator::with(['dataSource', 'parent'])->findOrFail($chart->indicator_id);

        // 3. Values extract karein dynamic filters ke liye
        $dataSource   = $indicator->dataSource;
        $dataSourceId = (int) $indicator->data_source_id;
        $datasetId    = config('services.bigquery.dataset');
        $tableId      = config('services.bigquery.table');

        // Hierarchy Identification Logic - FIXED FOR ALL EXISTING & NEW INDICATORS
        if (!empty($indicator->parent_id)) {
            // Case A: Explicit child indicator -> Use the parent's actual database ID
            $targetIdentifier = $indicator->parent_id;
        } else {
            // Fallback Default: Standalone Master -> Use its own database ID to match stored row records
            $targetIdentifier = $indicator->id;
        }

        // Logger context sync (Debugging ke liye helpful rahega)
        \Log::info('Chart Edit Target Identifier Resolved.', [
            'chart_id'         => $chart->id,
            'indicator_id'     => $indicator->id,
            'indicator_code'   => $indicator->indicator_code,
            'parent_id'        => $indicator->parent_id,
            'data_source_id'   => $dataSourceId,
            'target_used'      => $targetIdentifier // Ab yahan correctly 656 pass hoga na ki string "16"
        ]);

        // 4. Fetch dynamic BigQuery filters passing the corrected targetIdentifier
        $bqFilters = $this->bigQueryService->getIndicatorFilters(
            $targetIdentifier, 
            $dataSourceId, 
            $datasetId, 
            $tableId
        ) ?? ['filter_data' => [], 'keys' => []];
        // dd($bqFilters);
        

        // 5. Active Chart Types fetch karein
        $chartTypes = ChartType::all();

        return view('admin.charts.edit', compact('chart', 'indicator', 'chartTypes', 'bqFilters', 'savedConfig'));
    }

   


    public function destroy(Chart $chart)
    {
        $chart->delete();

        return redirect()->route('admin.charts.index')
            ->with('success', 'Chart deleted successfully!');
    }


    // clone chart 
        /**
     * Duplicate an existing chart resource like WordPress.
     */
    public function duplicate(Chart $chart)
    {
        try {
            // 1. Eloquent model ka clone/replicate banayein
            $clonedChart = $chart->replicate();

            // 2. Name modify karein taaki user ko samajh aaye ye copy hai
            $clonedChart->chart_name = $chart->chart_name . ' (Copy)';

            // 3. Display order ko automatic priority dene ke liye increment kar sakte hain (+1)
            $clonedChart->display_order = $chart->display_order + 1;

            // 4. Save the new duplicated chart
            $clonedChart->save();

            return redirect()->back()->with('success', "Chart '{$chart->chart_name}' duplicated successfully!");
        } catch (\Exception $e) {
            \Log::error('Chart Duplication Failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Chart duplicate karne mein koi problem aayi.');
        }
    }



}