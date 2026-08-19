<?php

namespace App\Imports;

use App\Models\State;
use App\Models\StateAlias;
use App\Models\Indicator;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;

class IndicatorDataValuesImport
{
    private array $indicators = [];
    private array $resolvedStates = [];
    private $bigQueryTable;
    private array $chunkRows = [];
    private int $rowCount = 0; 

    public function __construct()
    {
        $httpClient = new GuzzleClient([
            'verify'          => ! app()->environment('local'),
            'timeout'         => 180.0,
            'connect_timeout' => 30.0,
        ]);

        $projectId   = config('services.bigquery.project_id');
        $keyFilePath = config('services.bigquery.key_file');
        $datasetName = config('services.bigquery.dataset');
        $tableName   = config('services.bigquery.table');

        if ($projectId && $keyFilePath) {
            $bigQuery = new BigQueryClient([
                'projectId'   => $projectId,
                'keyFilePath' => $keyFilePath,
                'httpHandler' => new Guzzle6HttpHandler($httpClient),
            ]);
            $this->bigQueryTable = $bigQuery->dataset($datasetName)->table($tableName);
            Log::info("BigQuery Client successfully initialized for table: {$tableName}");
        } else {
            Log::error("BigQuery configuration missing! Project ID or Key File path is empty.");
        }
    }

    public function model(array $row)
    {
        try {
            // 1. Keys ko trim aur lowercase karna
            $row = array_change_key_case(array_map('trim', $row), CASE_LOWER);

            // 2. State Id Resolve karna
            $rawStateName = $row['state'] ?? $row['states'] ?? $row['state_name'] ?? '';
            if ($rawStateName === '') {
                return null; 
            }
            $stateId = $this->resolveStateId($rawStateName);

            // 3. Resolve Indicator Name
            $indicatorName = $row['indicator'] ?? $row['indicator_name'] ?? '';
            if ($indicatorName === '') {
                return null;
            }

            // Fixed Single Data Source CPI = 2
            $dataSourceId = 2; 

            // Indicator updateOrCreate logic
            $indicatorCacheKey = $dataSourceId . '_' . $indicatorName;
            if (!isset($this->indicators[$indicatorCacheKey])) {
                $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $dataSourceId,
                        'name'           => $indicatorName
                    ],
                    [
                        'updated_at'     => date('Y-m-d H:i:s')
                    ]
                );
                $this->indicators[$indicatorCacheKey] = $indicatorModel->id;
            }
        
            $indicatorId = $this->indicators[$indicatorCacheKey];

            // 4. Extract Dynamic Attributes for additional_filters
            $filters = [];
            $excludeKeys = ['state', 'states', 'state_name', 'year', 'value', 'index', 'indicator', 'indicator_name'];

            foreach ($row as $key => $val) {
                if (!in_array($key, $excludeKeys) && $val !== null && $val !== '') {
                    $filters[$key] = $val;
                }
            }

            $yearValue = $row['year'] ?? null;
            $rawValue = $row['index'] ?? $row['value'] ?? 0.0;
            $numericValue = (float) $rawValue;

            $finalFilters = !empty($filters) ? json_encode($filters) : json_encode(new \stdClass());

            // ========================================================
            // PAYLOAD PUSH TO CHUNK BUFFER
            // ========================================================
            if ($this->bigQueryTable) {
                $this->chunkRows[] = [
                    'data' => [
                        'data_source_id'     => (int) $dataSourceId,
                        'indicator_id'       => (int) $indicatorId,
                        'state_id'           => (int) $stateId,
                        'year'               => $yearValue ? (string) $yearValue : null,
                        'value'              => $numericValue,
                        'additional_filters' => $finalFilters,
                        'created_at'         => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                Log::warning("Skipping BigQuery push because \$bigQueryTable is not initialized.");
            }

            $this->rowCount++;
            return null;

        } catch (\Exception $e) {
            Log::error('Pipeline Stream Row Processing Error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'row'  => $row ?? []
            ]);
            throw $e; 
        }
    }

    private function resolveStateId(string $rawStateName): int
    {
        if (isset($this->resolvedStates[$rawStateName])) {
            return $this->resolvedStates[$rawStateName];
        }

        $alias = StateAlias::where('raw_name', $rawStateName)->first();
        if ($alias) {
            $this->resolvedStates[$rawStateName] = $alias->state_id;
            return $alias->state_id;
        }

        $state = State::where('name', $rawStateName)->first();
        
        if (!$state) {
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $rawStateName);
            $computedCode = strtoupper(substr($cleanName, 0, 5));

            try {
                $state = State::create([
                    'name' => $rawStateName,
                    'code' => $computedCode
                ]);
            } catch (\Exception $dbException) {
                $absoluteFallbackCode = strtoupper(substr(md5(uniqid()), 0, 3));
                $state = State::create([
                    'name' => $rawStateName,
                    'code' => $absoluteFallbackCode
                ]);
            }
        }

        try {
            StateAlias::firstOrCreate([
                'state_id' => $state->id,
                'raw_name' => $rawStateName
            ]);
        } catch (\Exception $aliasException) {
            // Ignore
        }

        $this->resolvedStates[$rawStateName] = $state->id;
        return $state->id;
    }

    public function flushToBigQuery()
    {
        Log::info("flushToBigQuery called. Current chunkRows count: " . count($this->chunkRows));

        if ($this->bigQueryTable && !empty($this->chunkRows)) {
            try {
                $totalToInsert = count($this->chunkRows);
                Log::info("Attempting to push batch of {$totalToInsert} records to Google BigQuery...");
                
                $insertResponse = $this->bigQueryTable->insertRows($this->chunkRows);
                
                if (!$insertResponse->isSuccessful()) {
                    Log::error("BigQuery Insert Failed completely for batch!");
                    foreach ($insertResponse->failedRows() as $index => $failedRow) {
                        // Log exact row and error details returned by Google BigQuery
                        Log::error("BigQuery Failed Row Index {$index}:", [
                            'errors' => $failedRow->errors(),
                            'row_sample' => json_encode($failedRow->row())
                        ]);
                    }
                } else {
                    Log::info("Success: {$totalToInsert} records streamed to BigQuery smoothly.");
                }
            } catch (\Exception $e) {
                Log::error("BigQuery Exception inside flush: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // Clear buffer safely after execution attempt
            $this->chunkRows = [];
        } else {
            Log::warning("flushToBigQuery skipped: either bigQueryTable is null or chunkRows is empty.");
        }
    }

    public function __destruct()
    {
        $this->flushToBigQuery();
    }
}