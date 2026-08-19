<?php
namespace App\Services;

use Exception;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\Table;
use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BigQueryService
{
    protected BigQueryClient $bigQuery;
    protected Dataset $dataset;
    protected Table $table;

    public function __construct()
    {
        // 1. SSL Verification config based on environment
        $httpClient = new GuzzleClient([
            'verify' => ! app()->environment('local'),
        ]);

        // 2. Fetch configurations with default fallbacks
        $projectId   = config('services.bigquery.project_id');
        $keyFilePath = config('services.bigquery.key_file');
        $location    = config('services.bigquery.location');
        $datasetName = config('services.bigquery.dataset');
        $tableName   = config('services.bigquery.table');

        // 4. Initialize BigQuery Client
        $this->bigQuery = new BigQueryClient([ 
            'projectId'   => $projectId,
            'keyFilePath' => $keyFilePath,
            'location'    => $location,
            'httpHandler' => new Guzzle6HttpHandler($httpClient),
        ]);

        // 5. Initialize Dataset & Table Objects
        $this->dataset = $this->bigQuery->dataset($datasetName);
        $this->table   = $this->dataset->table($tableName);
    }

    public function runQuery(string $query, array $params = []): array
    {
        $queryConfig = $this->bigQuery->query($query);
        if (! empty($params)) {
            $queryConfig->parameters($params);
        }
        $queryResults = $this->bigQuery->runQuery($queryConfig);

        $results = [];
        foreach ($queryResults as $row) {
            $results[] = $row;
        }

        return $results;
    }

   
        public function getIndicatorData(array $params, string $datasetId = null, string $tableId = null): array
        {
            $startTime = microtime(true);
            
            $projectId   = config('services.bigquery.project_id');
            $datasetName = $datasetId ?: config('services.bigquery.dataset');
            $tableName   = $tableId ?: config('services.bigquery.table');

            $fullTablePath = $projectId 
                ? "`{$projectId}.{$datasetName}.{$tableName}`" 
                : "`{$datasetName}.{$tableName}`";
            
            // Accurate request tracking ke liye log context upar hi generate kar liya
            $logContext = [
                'params'          => $params, 
                'resolved_table'  => $fullTablePath,
                'passed_dataset'  => $datasetId,
                'passed_table'    => $tableId
            ];
            
            // Log::info('BigQuery: Fetching indicator data initiated.', $logContext);

            try {
                $perPage = (int) ($params['per_page'] ?? 2000);
                $page    = (int) ($params['page'] ?? 1);
                $offset  = ($page - 1) * $perPage;

                // Base WHERE conditions
                $whereConditions = ["indicator_id = @indicatorId"];
                $bindings = [
                    'indicatorId' => (int) $params['indicator']
                ];

                if (!empty($params['source'])) {
                    $whereConditions[] = "data_source_id = @source";
                    $bindings['source'] = (int) $params['source'];
                }

                if (!empty($params['state_id'])) {
                    $whereConditions[] = "state_id = @state_id";
                    $bindings['state_id'] = (int) $params['state_id'];
                }

                if (!empty($params['year'])) {
                    $whereConditions[] = "year = @year";
                    $bindings['year'] = (string) $params['year'];
                }

                $whereClause = "WHERE " . implode(" AND ", $whereConditions);

                // --- 2. Dynamic Table Injection ---
                $dataQuery = "
                    SELECT 
                        data_source_id, 
                        indicator_id, 
                        state_id, 
                        year, 
                        value, 
                        TO_JSON_STRING(additional_filters) AS additional_filters,
                        COUNT(*) OVER() as full_count
                    FROM {$fullTablePath} 
                    {$whereClause} 
                    LIMIT {$perPage} OFFSET {$offset}
                ";

                $cacheKey = 'bq_indicator_' . md5(json_encode($params) . $fullTablePath);
                $cacheMaxPerPage = (int) env('BQ_INDICATOR_CACHE_MAX_PER_PAGE', 40000);
                $shouldCache = $perPage <= $cacheMaxPerPage;
                $isFromCache = \Cache::has($cacheKey);

                if ($shouldCache) {
                    } else {
                        $rows = $this->runQuery($dataQuery, $bindings);
                     }


                $rows = \Cache::remember($cacheKey, now()->addMinutes(15), function () use ($dataQuery, $bindings, $fullTablePath) {
                    return $this->runQuery($dataQuery, $bindings);
                });

                $totalRecords = !empty($rows) ? (int) $rows[0]['full_count'] : 0;
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                return [
                    'data'         => $rows,
                    'total'        => $totalRecords,
                    'per_page'     => $perPage,
                    'current_page' => $page,
                    'last_page'    => $perPage > 0 ? ceil($totalRecords / $perPage) : 1,
                ];

            } catch (Exception $e) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                return [
                    'data'         => [],
                    'total'        => 0,
                    'per_page'     => $params['per_page'] ?? 2000,
                    'current_page' => $params['page'] ?? 1,
                    'last_page'    => 1,
                    'error'        => $e->getMessage(),
                ];
            }
        }
    /**
     * Fetch years and additional_filters dynamically 
     */

    public function getIndicatorFilters($indicatorIdentifier, int $dataSourceId, string $datasetId = null, string $tableId = null): array
    {
        $startTime = microtime(true);

        // --- Dynamic Config Setup ---
        $resolvedDatasetId = $datasetId ?: config('services.bigquery.dataset');
        $resolvedTableId   = $tableId ?: config('services.bigquery.table');
        $projectId         = config('services.bigquery.project_id');

        $fullTablePath = $projectId 
            ? "`{$projectId}.{$resolvedDatasetId}.{$resolvedTableId}`" 
            : "`{$resolvedDatasetId}.{$resolvedTableId}`";

        try {
            $dsId = (int) $dataSourceId;

            // BigQuery table contains `indicator_id` column
            $indId = (int) $indicatorIdentifier;

            // 1. Query Preparation (Filtering using indicator_id and data_source_id)
            $query = "SELECT DISTINCT 
                        CAST(year AS STRING) AS year, 
                        TO_JSON_STRING(additional_filters) AS additional_filters 
                    FROM {$fullTablePath} 
                    WHERE indicator_id = {$indId} 
                        AND data_source_id = {$dsId}";

            // Debug Log
            \Log::debug('BigQuery Executing Query for Indicator Filters', [
                'raw_query'            => $query,
                'indicator_identifier' => $indId,
                'data_source_id'       => $dsId
            ]);

            // 2. Run Query
            $rows = $this->runQuery($query);
            $filterData = [];

            \Log::debug('BigQuery Query Executed', [
                'rows_count' => count($rows),
                'table'      => $fullTablePath
            ]);

            // 3. Data Processing
            foreach ($rows as $row) {
                if (! empty($row['year'])) {
                    $filterData['year'][] = (string) $row['year'];
                }

                if (! empty($row['additional_filters']) && $row['additional_filters'] !== 'null') {
                    $decoded = json_decode($row['additional_filters'], true);

                    if (is_array($decoded)) {
                        foreach ($decoded as $key => $val) {
                            if (is_numeric($key)) {
                                continue;
                            }

                            if ($val !== null && $val !== '' && $val !== []) {
                                if (is_array($val)) {
                                    foreach ($val as $subVal) {
                                        if (is_scalar($subVal) && $subVal !== '') {
                                            $filterData[$key][] = (string) $subVal;
                                        }
                                    }
                                } elseif (is_scalar($val)) {
                                    $filterData[$key][] = (string) $val;
                                }
                            }
                        }
                    }
                }
            }

            // 4. Unique Values Formatting
            foreach ($filterData as $key => $values) {
                $uniqueValues = array_values(array_unique(array_filter($values)));
                if (! empty($uniqueValues)) {
                    $filterData[$key] = $uniqueValues;
                } else {
                    unset($filterData[$key]);
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // 5. Success Telemetry Log
            \Log::info('BigQuery Filters Processed Successfully.', [
                'target_table'      => $fullTablePath,
                'identifier'        => $indId,
                'data_source_id'    => $dsId,
                'total_rows'        => count($rows),
                'extracted_keys'    => array_keys($filterData),
                'execution_time_ms' => $executionTime
            ]);

            return [
                'keys'               => array_keys($filterData),
                'filter_data'        => $filterData,
                'total_rows_fetched' => count($rows)
            ];

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            \Log::error('BigQuery Filter Parsing Error Exception.', [
                'target_table'      => $fullTablePath ?? 'Initialization Failed',
                'identifier'        => $indicatorIdentifier,
                'data_source_id'    => $dataSourceId,
                'execution_time_ms' => $executionTime,
                'message'           => $e->getMessage(),
                'trace'             => $e->getTraceAsString()
            ]);

            return [
                'keys'        => [],
                'filter_data' => [],
                'error'       => $e->getMessage(),
            ];
        }
    }


    /**
     * Merge hone ke baad BigQuery table me duplicate state_ids ko master_id se update karein.
     *
     * @param int $masterId Sahi state ki ID
     * @param array $duplicateIds Jo duplicate IDs delete/hide hui hain unki array
     * @return long Updated rows ka count
     */

       /**
     * Update duplicate state IDs to Master State ID in BigQuery.
     * 
     * @param int $masterId
     * @param array $duplicateIds
     * @return int Total affected rows
     */
    public function updateMergedStates(int $masterId, array $duplicateIds): int
    {
        if (empty($duplicateIds)) {
            Log::info("BigQuery: Duplicate IDs array is empty. Skipping sync.");
            return 0;
        }

        // Strict integer sanitization aur formatting
        $cleanDuplicateIds = array_map('intval', $duplicateIds);
        $duplicateIdsString = implode(',', $cleanDuplicateIds);
        $masterId = (int) $masterId;

        $datasetId = config('services.bigquery.dataset');
        $tableName = config('services.bigquery.table');
        $fullTableName = "`{$datasetId}.{$tableName}`";

        try {
            // --- STEP 1: VERIFY & LOG CURRENT TARGETED RECORDS ---
            $selectQuery = "SELECT data_source_id, indicator_id, state_id, year, value 
                            FROM {$fullTableName} 
                            WHERE state_id IN ({$duplicateIdsString}) 
                            LIMIT 100";
            
            $selectJobConfig = $this->bigQuery->query($selectQuery);
            $selectResponse = $this->bigQuery->runQuery($selectJobConfig);
            
            $targetedRecords = [];
            foreach ($selectResponse as $row) {
                $targetedRecords[] = [
                    'data_source_id' => $row['data_source_id'] ?? 'N/A',
                    'indicator_id'   => $row['indicator_id'] ?? 'N/A',
                    'current_state'  => $row['state_id'] ?? 'N/A',
                    'year'           => $row['year'] ?? 'N/A',
                    'value'          => $row['value'] ?? 'N/A',
                ];
            }

            if (!empty($targetedRecords)) {
                Log::info("BigQuery: Found target rows to update", [
                    'duplicate_ids' => $cleanDuplicateIds,
                    'records_count' => count($targetedRecords),
                    'preview_data'  => $targetedRecords
                ]);
            } else {
                // Agar pehle direct select nahi chal raha tha, to raw logs trigger karein debugging ke liye
                Log::warning("BigQuery Check: No rows returned via SELECT query for IDs: {$duplicateIdsString}. Please verify your environment config dataset or table mapping.");
            }

            // --- STEP 2: RUN SAFE PARAMETERIZED UPDATE QUERY ---
            $updateQuery = "UPDATE {$fullTableName} 
                            SET state_id = @masterId 
                            WHERE state_id IN ({$duplicateIdsString})";

            // Query execution with explicit Parameter Binding configurations
            $queryJobConfig = $this->bigQuery->query($updateQuery)
                ->parameters([
                    'masterId' => $masterId
                ]);

            $response = $this->bigQuery->runQuery($queryJobConfig);
            
            // Wait for query to complete securely to pull exact metadata
            if (!$response->isComplete()) {
                Log::info("BigQuery: Waiting for update job pipeline execution...");
                $response->waitUntilComplete();
            }

            $info = $response->info();
            $numDmlAffectedRows = isset($info['numDmlAffectedRows']) ? (int)$info['numDmlAffectedRows'] : 0;

            Log::info("BigQuery Sync Success", [
                'master_id'     => $masterId,
                'duplicate_ids' => $cleanDuplicateIds,
                'affected_rows' => $numDmlAffectedRows
            ]);

            return $numDmlAffectedRows;

        } catch (\Exception $e) {
            Log::error("BigQuery Sync Process Exception thrown", [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'query'   => $updateQuery ?? 'Not defined'
            ]);
            throw new \Exception("BigQuery data sync failed: " . $e->getMessage());
        }
    }

}