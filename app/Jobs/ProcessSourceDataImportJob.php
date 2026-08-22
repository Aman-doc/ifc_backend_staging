<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\SubIndicator;
use App\Services\StateResolverService;
use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Indicator;

class ProcessSourceDataImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Set to 0 for no time limit
    public int $timeout = 0;

    // Ensure job doesn't stay marked as reserved forever if worker dies
    public $failOnTimeout = true;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 1;

    protected DataSource $dataSource;

    protected function isTargetAsiYear($value): bool
    {
        $normalized = $this->normalizeTargetYearValue($value);
        $normalized = preg_replace('/[\s_\-\/]+/', '', $normalized);

        return $normalized === '202324';
    }


    protected function normalizeTargetYearValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtolower(trim((string) $value));
    }

    /**
     * Create a new job instance.
     */
    public function __construct(DataSource $dataSource)
    {
        $this->dataSource = $dataSource;
    }

    /**
     * Execute the job.
     */ 
   
   public function handle(): void
    {
        $datasetSource = $this->dataSource->dataset_id;
        $dataSourceId  = $this->dataSource->id;
        Log::info(" ----------- Start data import for DataSource ID: {$dataSourceId} | Dataset: {$datasetSource} -------");
        
        // 1. File Path Resolve
        $keyFilePath = config('services.bigquery.key_file');
        if (str_contains($keyFilePath, '${STORAGE_PATH}')) {
            $keyFilePath = str_replace('${STORAGE_PATH}', storage_path(), $keyFilePath);
        }
        Log::info("Using BigQuery key file path: {$keyFilePath}");

        
        // 2. String Configuration Values (Log ke liye)
        $projectId   = config('services.bigquery.project_id');
        $datasetName = config('services.bigquery.dataset'); // String
        $tableName   = config('services.bigquery.table');   // String

        // 3. Initialize BigQuery Client
        $bigQuery = new BigQueryClient([
            'keyFilePath' => $keyFilePath,
            'projectId'   => $projectId,
        ]);

        // 4. Create BigQuery Objects for API operations
        $datasetObject = $bigQuery->dataset($datasetName);
        $tableObject   = $datasetObject->table($tableName);

        // 5. Safe Logging
        Log::info("BigQuery client initialized for Project: {$projectId} | Dataset: {$datasetName} | Table: {$tableName}");

        $totalSavedRecords    = 0;
        $savedIndicatorsCount = 0;

        $standardKeys = [
            'state',
            'state_ut',
            'raw_state_name',
            'indicator',
            'indicator_name',
            'indicator_code',
            'year',
            'time_period',
            'value',
            'dataset'
        ];

        // Chunking Configuration
        $batchBuffer = [];
        $batchSize   = 2000;

        // 6. Flush Batch Closure ($tableObject use ho raha hai)
        $flushBatch = function () use ($tableObject, &$batchBuffer, &$totalSavedRecords, $datasetSource, $dataSourceId) {
            if (empty($batchBuffer)) {
                return;
            }

            // Object ke upar insertRows call ho raha hai
            $insertResponse = $tableObject->insertRows($batchBuffer);

            if (!$insertResponse->isSuccessful()) {
                foreach ($insertResponse->failedRows() as $failedRow) {
                    Log::error('BigQuery Streaming Insert Error', [
                        'errors' => $failedRow['errors'] ?? [],
                        'row'    => $failedRow['row'] ?? []
                    ]);
                }
            } else {
                $totalSavedRecords += count($batchBuffer);
            }

            $batchBuffer = [];
        };

        if ($datasetSource === "NSS77") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['data'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource} (DataSource ID: {$dataSourceId}).");
                return;
            }

            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $indicatorName = $indicator['description'] ?? 
                                $indicator['definition'] ??
                                null;
                $indicator_code =$indicator['indicator_code'];
                $module = $indicator['module'] ?? null;
                if (!$indicatorCode) continue;

                // Default to main data source ID
                $currentDataSourceId = $dataSourceId; 

                // Handle Module-Based Data Sources Creation
                if (!empty($module)) {
                    // Unique Dataset ID for child module (e.g. NSS78_MODULE_NAME)
                    $moduleDatasetId = strtoupper($datasetSource) . '_' . strtoupper(str_replace(' ', '_', $module));
                    $moduleTitle     = "{$datasetSource} - {$module}";
                    Log::info("Data_source_id: {$dataSourceId}");
                    // Find or Create Child DataSource with parent_datasource_id
                    $childDataSource = DataSource::updateOrCreate(
                            [
                                'dataset_id' => $moduleDatasetId, // Match strictly on unique dataset_id
                            ],
                            [
                                'parent_datasource_id' => $dataSourceId, // Set/Update parent_datasource_id
                                'title'                => $moduleTitle,
                                'description'          => "Sub-module dataset for {$moduleTitle}",
                                'is_synced'            => false,
                            ]
                        );
                                        // Assign child data source ID for inserting records
                    $currentDataSourceId = $childDataSource->id;

                            $indicatorModel = Indicator::updateOrCreate(
                                        [
                                            // Y yahan woh columns hain jo unique hone chahiye (Duplicate se bachne ke liye)
                                            'indicator_code' => $indicator_code, 
                                            'data_source_id' => $currentDataSourceId,
                                        ],
                                        [
                                            // Yahan woh data hai jo update ya insert hoga
                                            'name'           => $indicatorName,
                                            'is_synced'      => false,
                                        ]
                                    );

                    if ($childDataSource->wasRecentlyCreated) {
                        Log::info("Created new module DataSource -> Title: '{$moduleTitle}' | Dataset ID: '{$moduleDatasetId}' | Parent ID: {$dataSourceId} | New ID: {$currentDataSourceId}");
                    } else {
                        Log::info("Using existing module DataSource -> Title: '{$moduleTitle}' | ID: {$childDataSource->id}");
                    }
                } else {
                    Log::info("No module present for Indicator Code: {$indicatorCode}. Proceeding with Parent DataSource ID: {$dataSourceId}");
                }

                $page = 1;
                $indicatorInsertedRows = 0; // Track per-indicator rows for clear logging

                // do {
                //     $datasetResponse = $this->callMospiApi('get_data', [
                //         'dataset' => $datasetSource,
                //         'filters' => [
                //             'page'           => (string) $page,
                //             'indicator_code' => $indicatorCode,
                //             'module'         => $module,
                //         ]
                //     ]);

                //     if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                //         Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                //         break;
                //     }

                //     $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                //     $records  = $structuredContent['data'] ?? [];
                //     $metaData = $structuredContent['meta_data'] ?? [];

                //     if (!empty($records)) {
                //         foreach ($records as $record) {
                //             $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                //             $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                //             $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                //             $batchBuffer[] = [
                //                 'data' => [
                //                     'data_source_id'     => $currentDataSourceId,
                //                     'indicator_id'       => $indicatorCode,
                //                     'state_id'           => $stateId,
                //                     'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                //                     'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                //                     'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                //                     'created_at'         => date('Y-m-d H:i:s'),
                //                 ]
                //             ];

                //             $indicatorInsertedRows++;

                //             // Flush when buffer reaches 500
                //             if (count($batchBuffer) >= $batchSize) {
                //                 $flushBatch();
                //             }
                //         }
                //     }

                //     $totalPages = $metaData['totalPages'] ?? 1;
                //     $page++;
                // } while ($page <= $totalPages);

                // $flushBatch();

                // $savedIndicatorsCount++;

                // Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            }
        }
        else if ($datasetSource === "NSS78" ) {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['indicator'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }
                $currentDataSourceId = $dataSourceId;
            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['code'] ?? null;
                $indicatorName = $indicator['name'] ?? null;
                if (!$indicatorCode) continue;

                 $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$dataSourceId}");

                $page = 1;
                $indicatorInsertedRows = 0; // Track per-indicator rows for clear logging

                do {
                    $datasetResponse = $this->callMospiApi('get_data', [
                        'dataset' => $datasetSource,
                        'filters' => [
                            'page'           => (string) $page,
                            'Indicator' => $indicatorName,
                            'limit'          => 100,
                        ]
                    ]);

                    if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                        Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                        break;
                    }

                    $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                    $records  = $structuredContent['data'] ?? [];
                    $metaData = $structuredContent['meta_data'] ?? [];

                    if (!empty($records)) {
                        foreach ($records as $record) {
                            $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                            $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                            $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $dataSourceId,
                                    'indicator_id'       => $indicatorCode,
                                    'state_id'           => $stateId,
                                    'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                    'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];

                            $indicatorInsertedRows++;

                            if (count($batchBuffer) >= $batchSize) {
                                $flushBatch();
                            }
                        }
                    }

                    $totalPages = $metaData['totalPages'] ?? 1;
                    $page++;
                } while ($page <= $totalPages);

                $flushBatch();

                $savedIndicatorsCount++;

                Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            }
        }

        else if ($datasetSource === "PLFS") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicatorsByFrequency = $decodedData['indicators_by_frequency'] ?? [];

            if (empty($indicatorsByFrequency)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }
            $frequencyCode = 0;
            Log::info("datasetSource data {$datasetSource}.");
            foreach ($indicatorsByFrequency as $frequency => $indicators) {
                $frequencyCode++;
                
                
                foreach ($indicators as $indicator) {
                    $indicatorCode = $indicator['indicator_code'] ?? null;
                    $indicatorName = $indicator['description'] ?? null;
                    if (!$indicatorCode) continue;

                  
                    $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $dataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$dataSourceId}");

                // $indicatorId = $indicatorModel->id;
                    $metaDataResponse = $this->callMospiApi('get_metadata', [
                        'dataset' => $datasetSource,
                        'indicator_code' => $indicatorCode,
                        'frequency_code' => $frequencyCode,
                    ]);

                    if (!$metaDataResponse || !empty($metaDataResponse['result']['isError'])) {
                        Log::warning("API call failed or returned empty metadata for indicator: {$indicatorCode}:{$indicatorName}");
                        continue;
                    }

                    $filteredValues = $metaDataResponse['result']['structuredContent']['filter_values']['data'];
                    $yearTypes = $filteredValues['year_type'] ?? [null];

                    foreach ($yearTypes as $yearType) {
                        $page = 1;
                        $indicatorInsertedRows = 0; // Track per-indicator rows for clear logging

                        do {
                            $filters = [
                                'page'           => (string) $page,
                                'indicator_code' => $indicatorCode,
                                'frequency_code' => $frequencyCode,
                            ];

                            if ($yearType !== null) {
                                $filters['year_type_code'] = $yearType['year_type_code'];
                            }

                            $datasetResponse = $this->callMospiApi('get_data', [
                                'dataset' => $datasetSource,
                                'filters' => $filters,
                            ]);

                            if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                                Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                                continue;
                            }

                            $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                            $records  = $structuredContent['data'] ?? [];
                            $metaData = $structuredContent['meta_data'] ?? [];

                            if (!empty($records)) {
                                foreach ($records as $record) {
                                    $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                                    $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                                    $standardKeys = ['state', 'state_ut', 'year', 'time_period', 'value'];
                                    $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                                    // BigQuery format ke anusar array ko active kiya gya h
                                    $batchBuffer[] = [
                                        'data' => [
                                            'data_source_id'     => $dataSourceId,
                                            'indicator_id'       => $indicatorModel->id, // Raw code ki jagah MySQL dynamic ID mapped h
                                            'state_id'           => $stateId,
                                            'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                            'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                            'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                            'created_at'         => date('Y-m-d H:i:s'),
                                        ]
                                    ];

                                    $indicatorInsertedRows++;

                                    // Flush when buffer reaches 500
                                    if (count($batchBuffer) >= $batchSize) {
                                        $flushBatch();
                                    }
                                }
                            }

                            $totalPages = $metaData['totalPages'] ?? 1;
                            $page++;
                        } while ($page <= $totalPages);
                    }

                    // Loop ke bad remaining rows ko push krne ke liye check active kiya h
                    if (!empty($batchBuffer)) {
                        $flushBatch();
                    }

                    $savedIndicatorsCount++;

                    Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
                }
            }
        }


         else if ($datasetSource === "GENDER" ){
             $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['data'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }
            $currentDataSourceId = $dataSourceId;
            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $indicatorName = $indicator['label'] ?? 
                                  $indicator['description'] ??
                                    null;
                if (!$indicatorCode) continue;

                 $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$dataSourceId}");


                $page = 1;
                $indicatorInsertedRows = 0; // Track per-indicator rows for clear logging

                do {
                    $datasetResponse = $this->callMospiApi('get_data', [
                        'dataset' => $datasetSource,
                        'filters' => [
                            'page'           => (string) $page,
                            'indicator_code' => $indicatorCode,
                            'limit'          => 100,
                        ]
                    ]);

                    if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                        Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                        break;
                    }

                    $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                    $records  = $structuredContent['data'] ?? [];
                    $metaData = $structuredContent['meta_data'] ?? [];

                    if (!empty($records)) {
                        foreach ($records as $record) {
                            // 1. Raw State Name nikalna aur clean karna
                                $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? $record['state/UT'] ?? $record['State/UT'] ?? '');
                                $rawStateName = preg_replace('/\s+/', ' ', $rawStateName); 
                                $rawStateName = trim(str_replace(chr(194).chr(160), ' ', $rawStateName));

                                // 2. Custom Mapping handles for combined or special names
                                $customStateMap = [
                                    'Jammu & Kashmir and Ladakh' => 'Jammu & Kashmir', // Aap chahein toh use Jammu & Kashmir ki ID par redirect kar sakte hain
                                    // 'Other States' => 'Other States', // Agar DB me add karna ho toh
                                ];

                                // Agar custom map me exist karta hai toh use replacement name assign karein
                                if (array_key_exists($rawStateName, $customStateMap)) {
                                    $rawStateName = $customStateMap[$rawStateName];
                                }

                                // 3. MySQL DB me check karna
                                $stateId = 0;
                                if (!empty($rawStateName)) {
                                    $stateRecord = \DB::table('states')
                                        ->where('name', $rawStateName)
                                        ->first();
                                        
                                    if ($stateRecord) {
                                        $stateId = $stateRecord->id;
                                    } else {
                                        // Agar 'Other States' ke liye warning nahi chahte toh yahan filter out kar sakte hain
                                        if ($rawStateName !== 'Other States') {
                                            Log::warning("State not found in MySQL DB for name: '{$rawStateName}'");
                                        }
                                    }
                                }

                            // 3. Clean up: JSON me jaane se pehle saare possible state keys ko record se remove karein
                            unset($record['state'], $record['state_ut'], $record['state/UT'], $record['State/UT']);

                            // Ab remaining keys se additional filters banayein
                            $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                            // 4. Batch Buffer me payload add karein
                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $dataSourceId,
                                    'indicator_id'       => $indicatorCode,
                                    'state_id'           => $stateId, // Clean extracted ID yahan chali gayi
                                    'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                    'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];

                            $indicatorInsertedRows++;

                            if (count($batchBuffer) >= $batchSize) {
                                $flushBatch();
                            }
                        }
                    }

                    $totalPages = $metaData['totalPages'] ?? 1;
                    $page++;
                } while ($page <= $totalPages);

                $flushBatch();

                $savedIndicatorsCount++;

                Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            }
         }
        else if ($datasetSource === "NSS79" || $datasetSource === "HCES" || $datasetSource === "MNRE" ||  $datasetSource === "AISHE" ||  $datasetSource === "NFHS"){
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['data'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }
            $currentDataSourceId = $dataSourceId;
            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $indicatorName = $indicator['label'] ?? 
                                  $indicator['description'] ??
                                    null;
                if (!$indicatorCode) continue;

                 $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$dataSourceId}");

                $indicatorId = $indicatorModel->id;
                $page = 1;
                $indicatorInsertedRows = 0; // Track per-indicator rows for clear logging

                do {
                    $datasetResponse = $this->callMospiApi('get_data', [
                        'dataset' => $datasetSource,
                        'filters' => [
                            'page'           => (string) $page,
                            'indicator_code' => $indicatorCode,
                            'limit'          => 100,
                        ]
                    ]);

                    if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                        Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                        break;
                    }

                    $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                    $records  = $structuredContent['data'] ?? [];
                    $metaData = $structuredContent['meta_data'] ?? [];

                    if (!empty($records)) {
                        foreach ($records as $record) {
                            $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                            $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                            $additionalFilters = array_diff_key($record, array_flip($standardKeys));
                            
                           // Loop ke bahar static/array cache initialize karein (optional, par high performance ke liye best)
                            static $subIndicatorCache = [];

                            $subIndicatorText = $additionalFilters['sub_indicator'] ?? null;
                            $subIndicatorId   = null;

                            if (!empty($subIndicatorText)) {
                                $cacheKey = $indicatorId . '_' . md5($subIndicatorText);

                                if (isset($subIndicatorCache[$cacheKey])) {
                                    // DB me query kiye bina cache se ID utha lega
                                    $subIndicatorId = $subIndicatorCache[$cacheKey];
                                } else {
                                    $subIndicatorModel = SubIndicator::firstOrCreate(
                                        [
                                            'indicator_id' => $indicatorId,
                                            'name'         => $subIndicatorText,
                                        ],
                                        [
                                            'alias_name'   => null,
                                            'sector'       => $additionalFilters['sector'] ?? null,
                                            'survey'       => $additionalFilters['survey'] ?? null,
                                        ]
                                    );

                                    $subIndicatorId = $subIndicatorModel->id;
                                    $subIndicatorCache[$cacheKey] = $subIndicatorId;
                                }
                            }

                            Log::info("SubIndicator Linked -> ID: {$subIndicatorId} | Indicator ID: {$indicatorId}");

                            if (!empty($additionalFilters)) {
                                Log::info("[Sub-Indicator Debug] Indicator Code: {$indicatorCode} | Indicator ID: {$indicatorId}", [
                                    'dataset'            => $datasetSource,
                                    'sub_indicator_keys' => array_keys($additionalFilters),
                                    'additional_filters' => $additionalFilters
                                ]);
                            }
                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $dataSourceId,
                                    'indicator_id'       => $indicatorId,
                                    'state_id'           => $stateId,
                                    'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                    'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];

                            $indicatorInsertedRows++;

                            if (count($batchBuffer) >= $batchSize) {
                                $flushBatch();
                            }
                            $indicatorInsertedRows++;
                        }
                    }

                    $totalPages = $metaData['totalPages'] ?? 1;
                    $page++;
                } while ($page <= $totalPages);

                $flushBatch();

                $savedIndicatorsCount++;

                Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            }
        }
        else if ($datasetSource === "NSS76") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['data'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }

            Log::info("indicators for {$datasetSource}: " . json_encode($indicators));

            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $survey_code   = $indicator['survey_code'] ?? null;

                if (!$indicatorCode) continue;
                // Dynamic Indicator Name Fallback
                $indicatorName = $indicator['label']; 
                            

                // Default to main data source ID
                $currentDataSourceId = $dataSourceId;

                // Handle Module-Based Data Sources Creation
                if (!empty($survey_code)) {
                    // survey_code: 1=Disability module, 2=Housing & drinking water module
                    $moduleName = "Unknown";
                    switch ($survey_code) {
                        case 1:
                            $moduleName = "Disability";
                            break;
                        case 2:
                            $moduleName = "Housing & drinking water";
                            break;
                    }

                    // Unique Dataset ID for child module (e.g. NSS76_DISABILITY)
                    $moduleDatasetId = strtoupper($datasetSource) . '_' . strtoupper(str_replace(' ', '_', $moduleName));
                    $moduleTitle     = "{$datasetSource} - {$moduleName}";
                    Log::info("Data_source_id: {$dataSourceId}");

                    // Find or Create Child DataSource with parent_datasource_id
                    $childDataSource = DataSource::updateOrCreate(
                        [
                            'dataset_id' => $moduleDatasetId,
                        ],
                        [
                            'parent_datasource_id' => $dataSourceId,
                            'title'                => $moduleTitle,
                            'description'          => "Sub-module dataset for {$moduleTitle}",
                            'is_synced'            => false,
                        ]
                    );

                    // Assign child data source ID for inserting records
                    $currentDataSourceId = $childDataSource->id;

                    if ($childDataSource->wasRecentlyCreated) {
                        Log::info("Created new module DataSource -> Title: '{$moduleTitle}' | Dataset ID: '{$moduleDatasetId}' | Parent ID: {$dataSourceId} | New ID: {$currentDataSourceId}");
                    } else {
                        Log::info("Using existing module DataSource -> Title: '{$moduleTitle}' | ID: {$childDataSource->id}");
                    }
                } else {
                    Log::info("No module present for Indicator Code: {$indicatorCode}. Proceeding with Parent DataSource ID: {$dataSourceId}");
                }

                // =========================================================================
                // STEP 1: MYSQL 'indicators' TABLE MEIN ENTRY SAVE KAREIN (BigQuery insertion se pehle)
                // =========================================================================
                $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$currentDataSourceId}");

                // =========================================================================
                // STEP 2: MOSPI API SE DATA FETCH KAREIN AUR BIGQUERY BATCH PREPARE KAREIN
                // =========================================================================
                $page = 1;
                $indicatorInsertedRows = 0;

                do {
                    $datasetResponse = $this->callMospiApi('get_data', [
                        'dataset' => $datasetSource,
                        'filters' => [
                            'page'           => (string) $page,
                            'indicator_code' => $indicatorCode,
                            'survey_code'    => $survey_code,
                        ]
                    ]);

                    if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                        Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                        break;
                    }

                    $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                    $records  = $structuredContent['data'] ?? [];
                    $metaData = $structuredContent['meta_data'] ?? [];

                    if (!empty($records)) {
                        foreach ($records as $record) {
                            $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                            $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                            $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                            // BigQuery Structure WITH 'data' KEY
                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $currentDataSourceId,
                                    'indicator_id'       => (string) $indicatorCode,
                                    'state_id'           => $stateId,
                                    'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                    'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];

                            $indicatorInsertedRows++;

                            // Flush when buffer reaches batchSize
                            if (count($batchBuffer) >= $batchSize) {
                                $flushBatch();
                            }
                        }
                    }

                    $totalPages = $metaData['totalPages'] ?? 1;
                    $page++;
                } while ($page <= $totalPages);

                // Remaining buffer flush karein
                $flushBatch();

                // =========================================================================
                // STEP 3: SYNC COMPLETE HONE PAR MYSQL ME STATUS UPDATE KAREIN
                // =========================================================================
                $indicatorModel->update([
                    'is_synced'      => true,
                    'last_synced_at' => now(),
                ]);

                $savedIndicatorsCount++;
                $totalSavedRecords += $indicatorInsertedRows;

                Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            }
        }
        else if ($datasetSource === "NSS75E") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['data'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }
            
            Log::info("Indicators found for {$datasetSource}: " . json_encode($indicators));

            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $survey_code   = $indicator['survey_code'] ?? null;

                if (!$indicatorCode) continue;

                // Dynamic Indicator Name Fallback
                $indicatorName = $indicator['label'];
                            
                // Default to main data source ID
                $currentDataSourceId = $dataSourceId;
                Log::info(" {$survey_code} | Parent DataSource ID: {$dataSourceId}");
                // Handle Module-Based Data Sources Creation
                    if (!empty($survey_code)) {
                        $moduleName = "Unknown";
                        switch ($survey_code) {
                            case 2:
                                $moduleName = "Education";
                                break;
                        }

                    // Unique Dataset ID for child module
                    $moduleDatasetId = strtoupper($datasetSource) . '_' . strtoupper(str_replace(' ', '_', $moduleName));
                    $moduleTitle     = "{$datasetSource} - {$moduleName}";
                    
                    // Find or Create Child DataSource
                    $childDataSource = DataSource::updateOrCreate(
                        [
                            'dataset_id' => $moduleDatasetId,
                        ],
                        [
                            'parent_datasource_id' => $dataSourceId,
                            'title'                => $moduleTitle,
                            'description'          => "Sub-module dataset for {$moduleTitle}",
                            'is_synced'            => false,
                        ]
                    );

                    // Assign child data source ID
                    $currentDataSourceId = $childDataSource->id;

                    if ($childDataSource->wasRecentlyCreated) {
                        Log::info("Created new module DataSource -> Title: '{$moduleTitle}' | Dataset ID: '{$moduleDatasetId}' | Parent ID: {$dataSourceId} | New ID: {$currentDataSourceId}");
                    } else {
                        Log::info("Using existing module DataSource -> Title: '{$moduleTitle}' | ID: {$childDataSource->id}");
                    }
                } else {
                    Log::info("No module present for Indicator Code: {$indicatorCode}. Proceeding with Parent DataSource ID: {$dataSourceId}");
                }

                // =========================================================================
                // STEP 1: MYSQL 'indicators' TABLE MEIN ENTRY SAVE KAREIN
                // =========================================================================
                $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$currentDataSourceId}");

                // =========================================================================
                // STEP 2: MOSPI API SE DATA FETCH KAREIN AUR BIGQUERY BATCH PREPARE KAREIN
                // =========================================================================
                $page = 1;
                $indicatorInsertedRows = 0;

                do {
                    $datasetResponse = $this->callMospiApi('get_data', [
                        'dataset' => $datasetSource,
                        'filters' => [
                            'page'           => (string) $page,
                            'indicator_code' => $indicatorCode,
                            'survey_code'    => $survey_code,
                        ]
                    ]);

                    if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                        Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                        break;
                    }

                    $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                    $records  = $structuredContent['data'] ?? [];
                    $metaData = $structuredContent['meta_data'] ?? [];

                    if (!empty($records)) {
                        foreach ($records as $record) {
                            $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                            $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                            $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                            // BigQuery Expected Array Format (WITH 'data' KEY)
                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $currentDataSourceId,
                                    'indicator_id'       => (string) $indicatorCode,
                                    'state_id'           => $stateId,
                                    'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                    'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];

                            $indicatorInsertedRows++;

                            // Flush when buffer reaches batchSize
                            if (count($batchBuffer) >= $batchSize) {
                                $flushBatch();
                            }
                        }
                    }

                    $totalPages = $metaData['totalPages'] ?? 1;
                    $page++;
                } while ($page <= $totalPages);

                // Remaining buffer clear karein
                $flushBatch();

                // =========================================================================
                // STEP 3: SYNC COMPLETE HONE PAR MYSQL ME STATUS UPDATE KAREIN
                // =========================================================================
                $indicatorModel->update([
                    'is_synced'      => true,
                    'last_synced_at' => now(),
                ]);

                $savedIndicatorsCount++;
                $totalSavedRecords += $indicatorInsertedRows;

                Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows to BigQuery.");
            }
        }
        else if ($datasetSource === "NSS80") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);
            // Log::info("indicatorsResponse for {$datasetSource}: " . json_encode($indicatorsResponse));

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['data'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }

            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $survey_code   = $indicator['survey_code'] ?? null;

                if (!$indicatorCode) continue;

                // Dynamic Fallback for Indicator Name
                $indicatorName = $indicator['indicator_name'] 
                            ?? $indicator['indicator'] 
                            ?? $indicator['name'] 
                            ?? $indicator['description'] 
                            ?? $indicator['title'] 
                            ?? $indicator['desc'] 
                            ?? 'Unknown Indicator';

                // Default to main data source ID
                $currentDataSourceId = $dataSourceId;

                // Handle Module-Based Data Sources Creation
                // survey_code
                // 1=CMST
                // 2=CMSE
                // Indicator code(1-42). Indicators 1-20 = Telecom (CMST) module(Digital literacy,
                // Cyber security, Online banking, Broadband, Internet access and usage, telecom infrastructure),
                // indicators 23-42 = Education (CMSE) module (expenditure, Educational support, Private coaching,
                // fees, Type of school).
                $moduleName = "Unkown";
                if ($indicatorCode <= 20) {
                    $moduleName = "CMSE";
                } else if ($indicatorCode >= 23 && $indicatorCode <= 42) {
                    $moduleName = "CMST";
                }

                // Unique Dataset ID for child module (e.g. NSS80_CMSE)
                $moduleDatasetId = strtoupper($datasetSource) . '_' . strtoupper(str_replace(' ', '_', $moduleName));
                $moduleTitle     = "{$datasetSource} - {$moduleName}";

                // STEP 1: Find or Create Child DataSource
                $childDataSource = DataSource::updateOrCreate(
                    [
                        'dataset_id' => $moduleDatasetId,
                    ],
                    [
                        'parent_datasource_id' => $dataSourceId,
                        'title'                => $moduleTitle,
                        'description'          => "Sub-module dataset for {$moduleTitle}",
                        'is_synced'            => false,
                    ]
                );

                $currentDataSourceId = $childDataSource->id;

                if ($childDataSource->wasRecentlyCreated) {
                    Log::info("Created new module DataSource -> Title: '{$moduleTitle}' | Dataset ID: '{$moduleDatasetId}' | Parent ID: {$dataSourceId} | New ID: {$currentDataSourceId}");
                } else {
                    Log::info("Using existing module DataSource -> Title: '{$moduleTitle}' | ID: {$childDataSource->id}");
                }

                // =========================================================================
                // STEP 2: PEHLE MYSQL 'indicators' TABLE MEIN ENTRY SAVE KAREIN
                // =========================================================================
                $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | Name: '{$indicatorName}' | DataSource ID: {$currentDataSourceId}");

                // =========================================================================
                // STEP 3: MOSPI API SE DATA FETCH KAREIN AUR BIGQUERY INSERTS SET KAREIN
                // =========================================================================
                $page = 1;
                $indicatorInsertedRows = 0;

                do {
                    $datasetResponse = $this->callMospiApi('get_data', [
                        'dataset' => $datasetSource,
                        'filters' => [
                            'page'           => (string) $page,
                            'indicator_code' => $indicatorCode,
                            'survey_code'    => $survey_code,
                        ]
                    ]);

                    if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                        Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                        break;
                    }

                    $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                    $records  = $structuredContent['data'] ?? [];
                    $metaData = $structuredContent['meta_data'] ?? [];

                    if (!empty($records)) {
                        foreach ($records as $record) {
                            $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                            $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                            $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $currentDataSourceId,
                                    'indicator_id'       => $indicatorCode,
                                    'state_id'           => $stateId,
                                    'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                    'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];

                            $indicatorInsertedRows++;

                            if (count($batchBuffer) >= $batchSize) {
                                $flushBatch();
                            }
                        }
                    }

                    $totalPages = $metaData['totalPages'] ?? 1;
                    $page++;
                } while ($page <= $totalPages);

                $flushBatch();

                // =========================================================================
                // STEP 4: BIGQUERY SYNC SUCCESSFUL HONE PAR MYSQL ME STATUS UPDATE KAREIN
                // =========================================================================
                $indicatorModel->update([
                    'is_synced'      => true,
                    'last_synced_at' => now(),
                ]);

                $savedIndicatorsCount++;
                $totalSavedRecords += $indicatorInsertedRows;

                Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            }
        }

        else if ($datasetSource === "CPIALRL") {
            
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $indicators  = $decodedData['data']['indicator'] ?? [];

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }
               Log::info("indicator data",['indicators' => $indicators]);                     
            // foreach ($indicators as $indicator) {
            //     $indicatorCode = $indicator['indicator_code'] ?? null;
            //     $indicatorName = $indicator['description'];
            //     if (!$indicatorCode) continue;

            //     // Default to main data source ID
            //     $currentDataSourceId = $dataSourceId;
            //       if (!$indicatorCode) continue;

            //      $indicatorModel = Indicator::updateOrCreate(
            //         [
            //             'data_source_id' => $currentDataSourceId,
            //             'indicator_code' => (string) $indicatorCode,
            //         ],
            //         [
            //             'name'      => $indicatorName,
            //             'is_synced' => false,
            //         ]
            //     );

            //     Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$dataSourceId}");

                   
            //     $page = 1;
            //     $indicatorInsertedRows = 0; // Track per-indicator rows for clear logging

            //     // do {
            //     //     $datasetResponse = $this->callMospiApi('get_data', [
            //     //         'dataset' => $datasetSource,
            //     //         'filters' => [
            //     //             'page'           => (string) $page,
            //     //             'indicator_code' => $indicatorCode,
            //     //         ]
            //     //     ]);

            //     //     if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
            //     //         Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
            //     //         break;
            //     //     }

            //     //     $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
            //     //     $records  = $structuredContent['data'] ?? [];
            //     //     $metaData = $structuredContent['meta_data'] ?? [];

            //     //     if (!empty($records)) {
            //     //         foreach ($records as $record) {
            //     //             $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
            //     //             $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

            //     //             $additionalFilters = array_diff_key($record, array_flip($standardKeys));

            //     //             $batchBuffer[] = [
            //     //                 'data' => [
            //     //                     'data_source_id'     => $currentDataSourceId,
            //     //                     'indicator_id'       => $indicatorCode,
            //     //                     'state_id'           => $stateId,
            //     //                     'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
            //     //                     'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
            //     //                     'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
            //     //                     'created_at'         => date('Y-m-d H:i:s'),
            //     //                 ]
            //     //             ];

            //     //             $indicatorInsertedRows++;

            //     //             // Flush when buffer reaches 500
            //     //             if (count($batchBuffer) >= $batchSize) {
            //     //                 $flushBatch();
            //     //             }
            //     //         }
            //     //     }

            //     //     $totalPages = $metaData['totalPages'] ?? 1;
            //     //     $page++;
            //     // } while ($page <= $totalPages);

            //     // $flushBatch();

            //     // $savedIndicatorsCount++;

            //     // Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            // }
        }
        // working 
        // else if ($datasetSource === "UDISE") {
        //     $indicatorsResponse = $this->callMospiApi('get_indicators', [
        //         'dataset' => $datasetSource
        //     ]);

        //     if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
        //         Log::error("Failed to fetch indicators for dataset {$datasetSource}");
        //         return;
        //     }

        //     $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
        //     $decodedData = json_decode($rawContent, true);
        //     $indicators  = $decodedData['data'] ?? [];

        //     if (empty($indicators)) {
        //         Log::info("No indicators found for {$datasetSource}.");
        //         return;
        //     }

        //     foreach ($indicators as $indicator) {
        //         $indicatorCode = $indicator['indicator_code'] ?? null;
        //         $survey_code   = $indicator['survey_code'] ?? null;

        //         if (!$indicatorCode) continue;

        //         // Fallback Indicator Name Logic
        //         $indicatorName = $indicator['indicator_name'] 
        //                     ?? $indicator['indicator'] 
        //                     ?? $indicator['name'] 
        //                     ?? $indicator['description'] 
        //                     ?? $indicator['title'] 
        //                     ?? 'Unknown Indicator';

        //         // Dynamic Data Source Selection (Parent vs Sub-module)
        //         $currentDataSourceId = $dataSourceId;




        //         // Sub-module / Survey code mapping check
        //         if (!empty($survey_code)) {
        //             $moduleName = "Module_{$survey_code}"; // Sub-module name fallback

        //             $moduleDatasetId = strtoupper($datasetSource) . '_' . strtoupper(str_replace(' ', '_', $moduleName));
        //             $moduleTitle     = "{$datasetSource} - {$moduleName}";

        //             // STEP 1: Save / Resolve Data Source (Child Module)
        //             $childDataSource = DataSource::updateOrCreate(
        //                 [
        //                     'dataset_id' => $moduleDatasetId,
        //                 ],
        //                 [
        //                     'parent_datasource_id' => $dataSourceId,
        //                     'title'                => $moduleTitle,
        //                     'description'          => "Sub-module dataset for {$moduleTitle}",
        //                     'is_synced'            => false,
        //                 ]
        //             );

        //             $currentDataSourceId = $childDataSource->id;

        //             if ($childDataSource->wasRecentlyCreated) {
        //                 Log::info("Created new module DataSource -> Title: '{$moduleTitle}' | Dataset ID: '{$moduleDatasetId}' | Parent ID: {$dataSourceId} | New ID: {$currentDataSourceId}");
        //             } else {
        //                 Log::info("Using existing module DataSource -> Title: '{$moduleTitle}' | ID: {$childDataSource->id}");
        //             }
        //         }

        //         // =========================================================================
        //         // STEP 2: PEHLE MYSQL 'indicators' TABLE MEIN ENTRY SAVE KAREIN
        //         // =========================================================================
        //         $indicatorModel = Indicator::updateOrCreate(
        //             [
        //                 'data_source_id' => $currentDataSourceId, // Data source mapping
        //                 'indicator_code' => (string) $indicatorCode,
        //             ],
        //             [
        //                 'name'      => $indicatorName,
        //                 'is_synced' => false, // Pehle false rakhenge
        //             ]
        //         );

        //         Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$currentDataSourceId}");

        //         // =========================================================================
        //         // STEP 3: MOSPI API SE DATA FETCH KAREIN AUR BIGQUERY INSERTS SET KAREIN
        //         // =========================================================================
        //         $page = 1;
        //         $indicatorInsertedRows = 0; 

        //         do {
        //             $datasetResponse = $this->callMospiApi('get_data', [
        //                 'dataset' => $datasetSource,
        //                 'filters' => [
        //                     'page'           => (string) $page,
        //                     'indicator_code' => $indicatorCode,
        //                 ]
        //             ]);

        //             if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
        //                 Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
        //                 break;
        //             }

        //             $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
        //             $records  = $structuredContent['data'] ?? [];
        //             $metaData = $structuredContent['meta_data'] ?? [];

        //             if (!empty($records)) {
        //                 foreach ($records as $record) {
        //                     $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
        //                     $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

        //                     $additionalFilters = array_diff_key($record, array_flip($standardKeys));

        //                     $batchBuffer[] = [
        //                         'data' => [
        //                             'data_source_id'     => $currentDataSourceId, // Dynamic current ID (Child/Parent)
        //                             'indicator_id'       => $indicatorCode,
        //                             'state_id'           => $stateId,
        //                             'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
        //                             'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
        //                             'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
        //                             'created_at'         => date('Y-m-d H:i:s'),
        //                         ]
        //                     ];

        //                     $indicatorInsertedRows++;

        //                     // Buffer full hone par BigQuery me Push karein
        //                     if (count($batchBuffer) >= $batchSize) {
        //                         $flushBatch();
        //                     }
        //                 }
        //             }

        //             $totalPages = $metaData['totalPages'] ?? 1;
        //             $page++;
        //         } while ($page <= $totalPages);

        //         // Remaining batch rows BigQuery me bhein
        //         $flushBatch();

        //         // =========================================================================
        //         // STEP 4: BIGQUERY SYNC SUCCESSFUL HONE PAR MYSQL ME STATUS UPDATE KAREIN
        //         // =========================================================================
        //         $indicatorModel->update([
        //             'is_synced'      => true,
        //             'last_synced_at' => now(),
        //         ]);

        //         $savedIndicatorsCount++;
        //         $totalSavedRecords += $indicatorInsertedRows;

        //         Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows to BigQuery.");
        //     }
        // }
        else if ($datasetSource === "UDISE") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $indicators = $indicatorsResponse['result']['structuredContent']['data'] ?? [];

            if (empty($indicators)) {
                $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
                $decodedData = json_decode($rawContent, true);
                $indicators  = $decodedData['data'] ?? [];
            }

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }

            $udisePageLimit = max(100, (int) env('MOSPI_SYNC_PAGE_LIMIT', 2000));
            $pageRetries    = max(1, (int) env('MOSPI_SYNC_PAGE_RETRIES', 3));

            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $survey_code   = $indicator['survey_code'] ?? null;

                if (!$indicatorCode) continue;

                $indicatorName = $indicator['indicator_name'] 
                            ?? $indicator['indicator'] 
                            ?? $indicator['name'] 
                            ?? $indicator['description'] 
                            ?? $indicator['title'] 
                            ?? 'Unknown Indicator';

                $currentDataSourceId = $dataSourceId;

                if (!empty($survey_code)) {
                    $moduleName = "Module_{$survey_code}";

                    $moduleDatasetId = strtoupper($datasetSource) . '_' . strtoupper(str_replace(' ', '_', $moduleName));
                    $moduleTitle     = "{$datasetSource} - {$moduleName}";

                    $childDataSource = DataSource::updateOrCreate(
                        [
                            'dataset_id' => $moduleDatasetId,
                        ],
                        [
                            'parent_datasource_id' => $dataSourceId,
                            'title'                => $moduleTitle,
                            'description'          => "Sub-module dataset for {$moduleTitle}",
                            'is_synced'            => false,
                        ]
                    );

                    $currentDataSourceId = $childDataSource->id;

                    if ($childDataSource->wasRecentlyCreated) {
                        Log::info("Created new module DataSource -> Title: '{$moduleTitle}' | Dataset ID: '{$moduleDatasetId}' | Parent ID: {$dataSourceId} | New ID: {$currentDataSourceId}");
                    } else {
                        Log::info("Using existing module DataSource -> Title: '{$moduleTitle}' | ID: {$childDataSource->id}");
                    }
                }

                $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL DB -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | DataSource ID: {$currentDataSourceId}");

                $page = 1;
                $totalPages = null;
                $indicatorInsertedRows = 0;

                do {
                    $datasetResponse = null;

                    for ($attempt = 1; $attempt <= $pageRetries; $attempt++) {
                        $datasetResponse = $this->callMospiApi('get_data', [
                            'dataset' => $datasetSource,
                            'filters' => [
                                'page'           => (string) $page,
                                'indicator_code' => $indicatorCode,
                                'limit'          => $udisePageLimit,
                            ]
                        ]);

                        if ($datasetResponse && empty($datasetResponse['result']['isError'])) {
                            break;
                        }

                        sleep(min($attempt * 2, 5));
                    }

                    if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                        Log::warning("API call failed for indicator: {$indicatorCode} at page {$page}");
                        break;
                    }

                    $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                    $records  = $structuredContent['data'] ?? [];
                    $metaData = $structuredContent['meta_data'] ?? [];
                    $totalPages ??= max(1, (int) ($metaData['totalPages'] ?? 1));

                    if (!empty($records)) {
                        foreach ($records as $record) {
                            $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                            $stateId      = StateResolverService::getOrCreateStateId($rawStateName);

                            $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $currentDataSourceId,
                                    'indicator_id'       => $indicatorCode,
                                    'state_id'           => $stateId,
                                    'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                    'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];

                            $indicatorInsertedRows++;

                            if (count($batchBuffer) >= $batchSize) {
                                $flushBatch();
                            }
                        }
                    }

                    $page++;
                } while ($page <= $totalPages);

                $flushBatch();

                $indicatorModel->update([
                    'is_synced'      => true,
                    'last_synced_at' => now(),
                ]);

                $savedIndicatorsCount++;
                $totalSavedRecords += $indicatorInsertedRows;

                Log::info("UDISE progress: indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). BigQuery rows: {$indicatorInsertedRows}. Total so far: {$totalSavedRecords}");
            }

            $this->dataSource->update([
                'is_synced'      => true,
                'last_synced_at' => now(),
            ]);

            Log::info("UDISE import completed. BigQuery rows: {$totalSavedRecords}, Indicators: {$savedIndicatorsCount}");
        }

        else if ($datasetSource === "ASUSE") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            $data = $decodedData['indicators_by_frequency'] ?? [];
            $indicators = [];

            foreach ($data as $d) {
                $indicators = array_merge($indicators, $d);
            }

            if (empty($indicators)) {
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }

            // FIX HERE: {$indicators} ki jagah count($indicators) use kiya hai taaki array-to-string error na aaye
            // Log::info("indicatorsResponse for total " . count($indicators) . " indicators: " . json_encode($indicatorsResponse));

            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $indicatorName = $indicator['description'] ??
                                 $indicator['definition'] ??
                                 null;

                $currentDataSourceId = $dataSourceId;

                if (!$indicatorCode) continue;

                // =========================================================================
                // STEP 2: PEHLE MYSQL 'indicators' TABLE MEIN ENTRY SAVE KAREIN
                // =========================================================================
                $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQL -> ID: {$indicatorModel->id} | Code: {$indicatorCode} | Name: '{$indicatorName}' | DataSource ID: {$currentDataSourceId}");

                $page = 1;
                $indicatorInsertedRows = 0;

                        

                // do {
                //     $datasetResponse = $this->callMospiApi('get_data', [
                //         'dataset' => $datasetSource,
                //         'filters' => [
                //             'page'           => (string) $page,
                //             'indicator_code' => $indicatorCode,
                //         ]
                //     ]);

                //     if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                //         Log::warning("API call failed or returned empty for indicator: {$indicatorCode} at page {$page}");
                //         break;
                //     }

                //     $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                //     $records  = $structuredContent['data'] ?? [];
                //     $metaData = $structuredContent['meta_data'] ?? [];

                //         if (!empty($records)) {
                //             foreach ($records as $record) {
                //                 // 1. Case-sensitive aur variations ko handle karte hue State Name extract karein
                //                 $rawStateName = (string) (
                //                     $record['state'] ?? 
                //                     $record['state_ut'] ?? 
                //                     $record['state/UT'] ?? // <-- Aapke data ke hisab se capital UT
                //                     $record['state/ut'] ?? 
                //                     $record['state_name'] ?? 
                //                     ''
                //                 );
                                
                //                 $rawStateName = trim($rawStateName);
                //                 $stateId = StateResolverService::getOrCreateStateId($rawStateName);

                //                 // 2. Sabhi possible combinations ko filter out karne ke liye keys array
                //                 $stateKeysToExclude = ['state', 'state_ut', 'state/UT', 'state/ut', 'state_name', 'state_code'];
                //                 $otherStandardKeys = $standardKeys ?? ['year', 'time_period', 'value'];
                                
                //                 $currentStandardKeys = array_merge($otherStandardKeys, $stateKeysToExclude);
                                
                //                 // 3. Filters se state fields ko alag karein taaki duplicate na ho
                //                 $additionalFilters = array_diff_key($record, array_flip($currentStandardKeys));

                //                 $batchBuffer[] = [
                //                     'data' => [
                //                         'data_source_id'     => $currentDataSourceId,
                //                         'indicator_id'       => $indicatorCode,
                //                         'state_id'           => $stateId,
                //                         'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                //                         'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                //                         'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                //                         'created_at'         => date('Y-m-d H:i:s'),
                //                     ]
                //                 ];

                //                 $indicatorInsertedRows++;

                //                 // Flush when buffer reaches 500
                //                 if (count($batchBuffer) >= $batchSize) {
                //                     $flushBatch();
                //                 }
                //             }
                //         }

                //     $totalPages = $metaData['totalPages'] ?? 1;
                //     $page++;
                // } while ($page <= $totalPages);

                // $flushBatch();

                // $savedIndicatorsCount++;

                // Log::info("Progress update: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows. Total BigQuery rows so far: {$totalSavedRecords}");
            }

        }

        // 
        // else if ($datasetSource === "ASI") {
        //     $indicatorsResponse = $this->callMospiApi('get_indicators', [
        //         'dataset' => $datasetSource
        //     ]);

        //     if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
        //         Log::error("Failed to fetch indicators for dataset {$datasetSource}");
        //         return;
        //     }

        //     $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
        //     $decodedData = json_decode($rawContent, true);
        //     $indicators = $decodedData['indicators'] ?? [];

        //     if (empty($indicators)) { 
        //         Log::info("No indicators found for {$datasetSource}.");
        //         return;
        //     }

        //     $metaDataResponse = $this->callMospiApi('get_metadata', [
        //         'dataset' => $datasetSource
        //     ]);
        //     $metaRawContent  = $metaDataResponse['result']['content'][0]['text'] ?? '{}';
        //     $metaDecodedData = json_decode($metaRawContent, true);

        //     $classificationYears = [];
        //     $nicTypes = [];
        //     $sectorCodes = [];

        //     foreach (($metaDecodedData['api_params'] ?? []) as $params) {
        //         if ($params['name'] === 'classification_year') {
        //             $classificationYears = $params['schema']['enum'] ?? [];
        //         } else if ($params['name'] === 'sector_code') {
        //             $sectorCodes = $params['schema']['enum'] ?? [];
        //         } else if ($params['name'] === 'nic_type') {
        //             $nicTypes = $params['schema']['enum'] ?? [];
        //         }
        //     }

        //     if (empty($classificationYears) || empty($nicTypes) || empty($sectorCodes)) {
        //         Log::warning("Missing metadata filters (Years, NIC, or Sectors) for {$datasetSource}.");
        //     }

        //     $savedIndicatorsCount = 0;
        //     $totalSavedRecords = 0;

        //     foreach ($indicators as $indicator) {
        //         $indicatorCode = $indicator['indicator_code'] ?? null;
        //         $indicatorName = $indicator['indicator_name'] ?? null;
                
        //         if (!$indicatorCode) continue;

        //         $currentDataSourceId = $dataSourceId;

        //         // STEP 2: Save to MySQL Indicators Table
        //         $indicatorModel = Indicator::updateOrCreate(
        //             [
        //                 'data_source_id' => $currentDataSourceId,
        //                 'indicator_code' => (string) $indicatorCode,
        //             ],
        //             [
        //                 'name'      => $indicatorName,
        //                 'is_synced' => false,
        //             ]
        //         );

        //         Log::info("Saved Indicator into MySQL -> ID: {$indicatorModel->id} | Code: {$indicatorCode}");

        //         $indicatorInsertedRows = 0; 

        //         // foreach ($classificationYears as $classification_year) {
        //         //     foreach ($sectorCodes as $sector_code) {
        //         //         foreach ($nicTypes as $nic_type) {
                            
        //         //             // FIX 1: $page ko yahan 1 par reset karna zaroori hai har filter combination ke liye
        //         //             $page = 1; 

        //         //             do {
        //         //                 $datasetResponse = $this->callMospiApi('get_data', [
        //         //                     'dataset' => $datasetSource,
        //         //                     'filters' => [
        //         //                         'page'                => (string) $page,
        //         //                         'indicator_code'      => $indicatorCode,
        //         //                         'classification_year' => $classification_year,
        //         //                         'sector_code'         => $sector_code,
        //         //                         'nic_type'            => $nic_type,
        //         //                     ]
        //         //                 ]);

        //         //                 if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
        //         //                     Log::warning("API call failed for indicator: {$indicatorCode} at page {$page}");
        //         //                     break;
        //         //                 }

        //         //                 $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
        //         //                 $records  = $structuredContent['data'] ?? [];
        //         //                 $metaData = $structuredContent['meta_data'] ?? [];

        //         //                 if (!empty($records)) {
        //         //                     foreach ($records as $record) {
        //         //                         $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
        //         //                         $stateId      = StateResolverService::getOrCreateStateId($rawStateName);
                                        
        //         //                         // Standard keys ko hata kar bache hue filters alag nikalna
        //         //                         $standardKeys = ['state', 'state_ut', 'year', 'time_period', 'value'];
        //         //                         $additionalFilters = array_diff_key($record, array_flip($standardKeys));

        //         //                         // FIX 2: Comment hatakar buffer array me data push karein
        //         //                     $batchBuffer[] = [
        //         //                                         'data' => [ // <--- Yeh key BigQuery ke liye MANDATORY hai
        //         //                                             'data_source_id'     => $currentDataSourceId,
        //         //                                             'indicator_id'       => $indicatorModel->id, 
        //         //                                             'state_id'           => $stateId,
        //         //                                             'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
        //         //                                             'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
        //         //                                             'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
        //         //                                             'created_at'         => date('Y-m-d H:i:s'),
        //         //                                         ]
        //         //                                     ];

        //         //                         $indicatorInsertedRows++;
        //         //                         $totalSavedRecords++;

        //         //                         // Agar batch buffer size limit (jaise 500) tak pahunche toh BigQuery me flush karein
        //         //                         if (count($batchBuffer) >= $batchSize) {
        //         //                             $flushBatch(); // Make sure this closure/method is defined in your class
        //         //                         }
        //         //                     }
        //         //                 }

        //         //                 $totalPages = $metaData['totalPages'] ?? 1;
        //         //                 $page++;
                                
        //         //             } while ($page <= $totalPages); // FIX 3: Apni scaling requirements ke mutabik condition adjust karein
        //         //         }
        //         //     }
        //         // }

        //         // // Remaining buffer rows ko flush karein loop khatam hone par
        //         // if (!empty($batchBuffer)) {
        //         //     $flushBatch(); 
        //         // }

        //         // $savedIndicatorsCount++;
        //         // Log::info("Progress: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows.");
                
        //         // FIX 4: Sabhi indicators ko chalane ke liye end me laga hua 'break;' hata diya gaya hai.
        //     }
        // }
        else if ($datasetSource === "ASI") {
            $indicatorsResponse = $this->callMospiApi('get_indicators', [
                'dataset' => $datasetSource
            ]);

            if (!$indicatorsResponse || !empty($indicatorsResponse['result']['isError'])) {
                Log::error("Failed to fetch indicators for dataset {$datasetSource}");
                return;
            }

            $rawContent  = $indicatorsResponse['result']['content'][0]['text'] ?? '{}';
            $decodedData = json_decode($rawContent, true);
            
            $indicators = $decodedData['indicators'] ?? [];
            

            if (empty($indicators)) { 
                Log::info("No indicators found for {$datasetSource}.");
                return;
            }

            $metaDataResponse = $this->callMospiApi('get_metadata', [
                'dataset' => $datasetSource
            ]);
            $metaRawContent  = $metaDataResponse['result']['content'][0]['text'] ?? '{}';
            $metaDecodedData = json_decode($metaRawContent, true);
            $classificationYears = [];
            $nicTypes = [];
            $sectorCodes = [];

            foreach (($metaDecodedData['api_params'] ?? []) as $params) {
                if ($params['name'] === 'classification_year') {
                    $classificationYears = $params['schema']['enum'] ?? [];
                } else if ($params['name'] === 'sector_code') {
                    $sectorCodes = $params['schema']['enum'] ?? [];
                } else if ($params['name'] === 'nic_type') {
                    $nicTypes = $params['schema']['enum'] ?? [];
                }
            }

            if (empty($classificationYears) || empty($nicTypes) || empty($sectorCodes)) {
                Log::warning("Missing metadata filters (Years, NIC, or Sectors) for {$datasetSource}.");
            }

            $savedIndicatorsCount = 0;
            $totalSavedRecords = 0;

            foreach ($indicators as $indicator) {
                $indicatorCode = $indicator['indicator_code'] ?? null;
                $indicatorName = $indicator['indicator_name'] ?? null;
                
                if (!$indicatorCode) continue;

                $currentDataSourceId = $dataSourceId;

                // STEP 2: Save to MySQL Indicators Table
                $indicatorModel = Indicator::updateOrCreate(
                    [
                        'data_source_id' => $currentDataSourceId,
                        'indicator_code' => (string) $indicatorCode,
                    ],
                    [
                        'name'      => $indicatorName,
                        'is_synced' => false,
                    ]
                );

                Log::info("Saved Indicator into MySQLs -> ID: {$indicatorModel->id} | Code: {$indicatorCode}");

                $indicatorInsertedRows = 0; 

                $standardKeys = ['state', 'state_ut', 'year', 'time_period', 'value'];

                foreach ($classificationYears as $classification_year) {
                    foreach ($sectorCodes as $sector_code) {
                        foreach ($nicTypes as $nic_type) {
                            $combinationKey = [
                                'data_source_id'      => $currentDataSourceId,
                                'indicator_code'      => (string) $indicatorCode,
                                'classification_year' => $classification_year,
                                'sector_code'         => $sector_code,
                                'nic_type'            => $nic_type,
                            ];

                            $existingTracker = \Illuminate\Support\Facades\DB::table('dataset_import_trackers')->where($combinationKey)->first();

                            if ($existingTracker && $existingTracker->status === 'completed') {
                                Log::info("Skipping ASI combination already completed: indicator_code={$indicatorCode} | classification_year={$classification_year} | sector_code={$sector_code} | nic_type={$nic_type}");
                                continue;
                            }

                            if ($existingTracker) {
                                \Illuminate\Support\Facades\DB::table('dataset_import_trackers')
                                    ->where('id', $existingTracker->id)
                                    ->update([
                                        'status'     => 'processing',
                                        'updated_at' => now(),
                                    ]);
                            } else {
                                $trackerId = \Illuminate\Support\Facades\DB::table('dataset_import_trackers')->insertGetId(array_merge($combinationKey, [
                                    'status'       => 'processing',
                                    'fetched_rows' => 0,
                                    'created_at'   => now(),
                                    'updated_at'   => now(),
                                ]));
                            }

                            $page = 1;
                            $combinationInsertedRows = 0;
                            $seenHashes = [];

                            do {
                                $apiUrl = "https://api.mospi.gov.in/api/asi/getASIData";
                                // Use shell_exec with curl to bypass OpenSSL 3.0 legacy renegotiation issues
                                $queryString = http_build_query([
                                    'classification_year' => $classification_year,
                                    'sector_code'         => $sector_code,
                                    'year'                => '2023-24',
                                    'indicator_code'      => $indicatorCode,
                                    'nic_type'            => $nic_type,
                                    'Format'              => 'JSON',
                                    'page'                => $page,
                                ]);
                                
                                $fullUrl = $apiUrl . '?' . $queryString;
                                $command = sprintf('curl -s -k -X GET "%s"', $fullUrl);
                                
                                $retryCount = 0;
                                $maxRetries = 3;
                                $datasetResponse = null;
                                $responseSuccessful = false;

                                while ($retryCount < $maxRetries) {
                                    $output = shell_exec($command);
                                    $datasetResponse = json_decode($output, true);
                                    
                                    if (!empty($datasetResponse)) {
                                        $responseSuccessful = true;
                                        break; // Success, break retry loop
                                    }
                                    
                                    $retryCount++;
                                    Log::warning("API call failed for indicator: {$indicatorCode} at page {$page}. Retry {$retryCount}/{$maxRetries}...");
                                    sleep(2); // Wait 2 seconds before retry
                                }

                                // Add delay to prevent rate limit for subsequent requests
                                sleep(1);

                                if (!$responseSuccessful) {
                                    Log::error("API call permanently failed after {$maxRetries} retries for indicator: {$indicatorCode} at page {$page}");
                                    break;
                                }
                                
                                $records  = $datasetResponse['data'] ?? [];
                                $metaData = $datasetResponse['meta_data'] ?? [];

                                if (!empty($records)) {
                                    foreach ($records as $index => $record) {
                                        $recordYear = (string) ($record['year'] ?? $record['time_period'] ?? '');

                                        if (!$this->isTargetAsiYear($recordYear)) {
                                            continue;
                                        }

                                       
                                        // $recordHash = $this->buildAsiRowHash($record, (string) $indicatorCode, $currentDataSourceId, (string) $classification_year, (string) $sector_code, (string) $nic_type);
                                        // if (isset($seenHashes[$recordHash])) { continue; }
                                        // $seenHashes[$recordHash] = true;

                                       
                                        $uniqueIdString = "{$indicatorCode}_{$currentDataSourceId}_{$classification_year}_{$sector_code}_{$nic_type}_{$page}_{$index}";
                                        $buidInsertId = md5($uniqueIdString);

                                        Log::info("ASI record fetched: indicator_code={$indicatorCode} | classification_year={$classification_year} | sector_code={$sector_code} | nic_type={$nic_type} | record=" . json_encode($record));

                                      
                                        // $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                                        // $stateId      = StateResolverService::getOrCreateStateId($rawStateName);
                                        //
                                        // $additionalFilters = array_diff_key($record, array_flip($standardKeys));
                                        //
                                        // $batchBuffer[] = [
                                        //     'insertId' => $buidInsertId, // BigQuery uses this for deduplication to prevent repeated data
                                        //     'data' => [
                                        //         'data_source_id'     => $currentDataSourceId,
                                        //         'indicator_id'       => $indicatorCode,
                                        //         'state_id'           => $stateId,
                                        //         'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                                        //         'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                                        //         'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                        //         'created_at'         => date('Y-m-d H:i:s'),
                                        //     ]
                                        // ];

                                        $combinationInsertedRows++;
                                        $indicatorInsertedRows++;
                                        $totalSavedRecords++;

                                        // if (count($batchBuffer) >= $batchSize) {
                                        //     $flushBatch();
                                        // }
                                    }
                                }

                                $totalPages = $metaData['totalPages'] ?? 1;
                                $page++;
                            } while ($page <= $totalPages);

                            \Illuminate\Support\Facades\DB::table('dataset_import_trackers')->updateOrInsert(
                                [
                                    'data_source_id'      => $currentDataSourceId,
                                    'indicator_code'      => (string) $indicatorCode,
                                    'classification_year' => $classification_year,
                                    'sector_code'         => $sector_code,
                                    'nic_type'            => $nic_type,
                                ],
                                [
                                    'status'       => 'completed',
                                    'fetched_rows' => $combinationInsertedRows,
                                    'updated_at'   => now(),
                                    'created_at'   => now(),
                                ]
                            );
                        }
                    }
                }

                // BigQuery flush intentionally disabled for ASI count-only mode.
                // if (!empty($batchBuffer)) {
                //     $flushBatch();
                // }

                $indicatorModel->update([
                    'is_synced'      => true,
                    'last_synced_at' => now(),
                ]);

                $savedIndicatorsCount++;
                Log::info("Progress: Processed indicator {$savedIndicatorsCount}/" . count($indicators) . " ({$indicatorCode}). Added {$indicatorInsertedRows} rows.");
            }
        }

        // ==========================================
        // CPI DATASET BLOCK (Pushing to BigQuery)
        // ==========================================
        else if ($datasetSource === "CPI") {
            Log::info("Starting CPI data import into BigQuery.");

            $cpiIndexDataSource = DataSource::firstOrCreate(
                ['title' => 'CPI Index', 'parent_datasource_id' => $dataSourceId],
                ['dataset_id'  => 'CPI_INDEX', 'description' => 'CPI Index Data', 'is_synced'   => false]
            );

            $cpiInflationDataSource = DataSource::firstOrCreate(
                ['title' => 'CPI Inflation', 'parent_datasource_id' => $dataSourceId],
                ['dataset_id'  => 'CPI_INFLATION', 'description' => 'CPI Inflation Data', 'is_synced'   => false]
            );

            $page = 1;
            do {
                $apiUrl = "https://api.mospi.gov.in/api/cpi/getCPIData";
                $queryString = http_build_query([
                    'base_year' => '2024',
                    'year'      => '2026',
                    'limit'     => 20,
                    'page'      => $page,
                ]);

                $fullUrl = $apiUrl . '?' . $queryString;
                Log::info("Calling CPI API Page {$page}: {$fullUrl}");

                // Added timeouts to curl (--connect-timeout 10 -m 30) to prevent hanging
                $command = sprintf('curl -s -k --connect-timeout 10 -m 30 -X GET "%s"', $fullUrl);
                $output = shell_exec($command);

                if (empty($output)) {
                    Log::error("CPI API returned empty response on Page {$page}");
                    break;
                }

                $datasetResponse = json_decode($output, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error("CPI API JSON Decode Error on Page {$page}: " . json_last_error_msg(), [
                        'raw_output' => substr($output, 0, 500) // Log first 500 chars of response
                    ]);
                    break;
                }

                $records  = $datasetResponse['data'] ?? [];
                $metaData = $datasetResponse['meta_data'] ?? [];

                Log::info("CPI API Page {$page} fetched. Records count: " . count($records));

                if (!empty($records)) {
                    foreach ($records as $record) {
                        $divisionName  = (string) ($record['division'] ?? 'Unknown');
                        $code          = (string) ($record['code'] ?? '');
                        $indicatorCode = !empty($code) ? $code : "CPI_" . md5($divisionName);

                        // 1. CPI Index ke liye Indicator Create / Update
                        $cpiIndexIndicator = Indicator::updateOrCreate(
                            [
                                'data_source_id' => $cpiIndexDataSource->id,
                                'indicator_code' => $indicatorCode,
                            ],
                            [
                                'name'      => $divisionName,
                                'is_synced' => false,
                            ]
                        );

                        Log::info("CPI Index Indicator processed", [
                            'action'         => $cpiIndexIndicator->wasRecentlyCreated ? 'CREATED' : 'UPDATED',
                            'indicator_id'   => $cpiIndexIndicator->id,
                            'indicator_code' => $cpiIndexIndicator->indicator_code,
                            'indicator_name' => $cpiIndexIndicator->name,
                            'data_source_id' => $cpiIndexDataSource->id
                        ]);

                        // 2. CPI Inflation ke liye Indicator Create / Update
                        $cpiInflationIndicator = Indicator::updateOrCreate(
                            [
                                'data_source_id' => $cpiInflationDataSource->id,
                                'indicator_code' => $indicatorCode,
                            ],
                            [
                                'name'      => $divisionName,
                                'is_synced' => false,
                            ]
                        );

                        Log::info("CPI Inflation Indicator processed", [
                            'action'         => $cpiInflationIndicator->wasRecentlyCreated ? 'CREATED' : 'UPDATED',
                            'indicator_id'   => $cpiInflationIndicator->id,
                            'indicator_code' => $cpiInflationIndicator->indicator_code,
                            'indicator_name' => $cpiInflationIndicator->name,
                            'data_source_id' => $cpiInflationDataSource->id
                        ]);

                        $rawStateName = (string) ($record['state'] ?? '');
                        $stateId      = StateResolverService::getOrCreateStateId($rawStateName);
                        $yearVal      = (string) ($record['year'] ?? '');

                        $additionalFilters = array_filter([
                            'class'      => $record['class'] ?? null,
                            'group'      => $record['group'] ?? null,
                            'imputation' => $record['imputation'] ?? null,
                            'item'       => $record['item'] ?? null,
                            'month'      => $record['month'] ?? null,
                            'sector'     => $record['sector'] ?? null,
                            'series'     => $record['series'] ?? null,
                            'sub_class'  => $record['sub_class'] ?? null,
                            'base_year'  => $record['base_year'] ?? null,
                        ], fn($val) => $val !== null);

                        // 3. Buffer Index Data for BigQuery
                        if (isset($record['index']) && is_numeric($record['index'])) {
                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $cpiIndexDataSource->id,
                                    'indicator_id'       => (string) $cpiIndexIndicator->id,
                                    'state_id'           => $stateId,
                                    'year'               => $yearVal,
                                    'value'              => (float) $record['index'],
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];
                        }

                        // 4. Buffer Inflation Data for BigQuery
                        if (isset($record['inflation']) && is_numeric($record['inflation'])) {
                            $batchBuffer[] = [
                                'data' => [
                                    'data_source_id'     => $cpiInflationDataSource->id,
                                    'indicator_id'       => (string) $cpiInflationIndicator->id,
                                    'state_id'           => $stateId,
                                    'year'               => $yearVal,
                                    'value'              => (float) $record['inflation'],
                                    'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                                    'created_at'         => date('Y-m-d H:i:s'),
                                ]
                            ];
                        }

                        if (count($batchBuffer) >= $batchSize) {
                            $flushBatch();
                        }
                    }
                } else {
                    Log::warning("No records found in API response for CPI Page {$page}");
                }

                $totalPages = $metaData['totalPages'] ?? 1;
                $page++;

            } while ($page <= $totalPages);

            $flushBatch();
            Log::info("CPI Import into BigQuery completed successfully.");
        }


}

    /**
     * Common Reusable Helper Function for MoSPI MCP API Calls.
     *
     * @param string $methodName
     * @param array $arguments
     * @return array|null
     */
    private function callMospiApi(string $methodName, array $arguments = []): ?array
    {
        try {
            $baseUrl = rtrim(env('MCP_BASE_URL', 'https://mcp.mospi.gov.in'), '/');
            $url = "{$baseUrl}/{$methodName}";

            $payload = [
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => 'tools/call',
                'params'  => [
                    'name'      => $methodName,
                    'arguments' => empty($arguments) ? (object)[] : $arguments,
                ]
            ];

            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'Accept'       => 'text/event-stream, application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error("MoSPI API Error [{$methodName}]: " . $response->body());
                return null;
            }

            $body = $response->body();

            // SSE response parsing
            $parsedJson = null;
            if (preg_match('/data:\s*(\{.*\})/', $body, $matches)) {
                $parsedJson = json_decode($matches[1], true);
            }

            if (!$parsedJson) {
                $parsedJson = json_decode($body, true);
            }

            return $parsedJson;
        } catch (\Exception $e) {
            Log::error("MoSPI API Exception [{$methodName}]: " . $e->getMessage());
            return null;
        }
    }
}