<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Indicator;
use App\Services\StateResolverService;

class FetchAsiCombinationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes max for a micro-job
    public int $tries = 3;

    protected $trackerId;
    protected $dataSourceId;
    protected $datasetSource;
    protected $indicatorCode;
    protected $classificationYear;
    protected $sectorCode;
    protected $nicType;

    public function __construct(
        $trackerId,
        $dataSourceId,
        $datasetSource,
        $indicatorCode,
        $classificationYear,
        $sectorCode,
        $nicType
    ) {
        $this->trackerId = $trackerId;
        $this->dataSourceId = $dataSourceId;
        $this->datasetSource = $datasetSource;
        $this->indicatorCode = $indicatorCode;
        $this->classificationYear = $classificationYear;
        $this->sectorCode = $sectorCode;
        $this->nicType = $nicType;
    }

    public function handle(): void
    {
        Log::info("Starting Micro-Job: Tracker ID {$this->trackerId} | Indicator {$this->indicatorCode} | Year {$this->classificationYear} | Sector {$this->sectorCode} | NIC {$this->nicType}");

        // Mark as processing
        DB::table('dataset_import_trackers')->where('id', $this->trackerId)->update([
            'status' => 'processing',
            'updated_at' => now(),
        ]);

        $batchSize = 2000;
        $indicatorInsertedRows = 0;
        $standardKeys = ['state', 'state_ut', 'year', 'time_period', 'value'];
        $page = 1;

        try {
            do {
                $datasetResponse = $this->callMospiApi('get_data', [
                    'dataset' => $this->datasetSource,
                    'filters' => [
                        'page'                => (string) $page,
                        'indicator_code'      => $this->indicatorCode,
                        'classification_year' => $this->classificationYear,
                        'sector_code'         => $this->sectorCode,
                        'nic_type'            => $this->nicType,
                    ]
                ]);
                
                // Add delay to prevent rate limit (1 second)
                sleep(1);

                if (!$datasetResponse || !empty($datasetResponse['result']['isError'])) {
                    Log::warning("API call failed for tracker {$this->trackerId} at page {$page}");
                    break;
                }

                $structuredContent = $datasetResponse['result']['structuredContent'] ?? [];
                $records  = $structuredContent['data'] ?? [];
                $metaData = $structuredContent['meta_data'] ?? [];
                
                if (!empty($records)) {
                    foreach ($records as $record) {
                        // Formatting logics are intentionally commented for exact format match as requested
                        // $rawStateName = (string) ($record['state'] ?? $record['state_ut'] ?? '');
                        // $stateId      = StateResolverService::getOrCreateStateId($rawStateName);
                        
                        // $additionalFilters = array_diff_key($record, array_flip($standardKeys));

                        // $batchBuffer[] = [
                        //     'data' => [ 
                        //         'data_source_id'     => $this->dataSourceId,
                        //         'indicator_id'       => $this->indicatorCode, 
                        //         'state_id'           => $stateId,
                        //         'year'               => (string) ($record['year'] ?? $record['time_period'] ?? ''),
                        //         'value'              => is_numeric($record['value'] ?? null) ? (float) $record['value'] : null,
                        //         'additional_filters' => !empty($additionalFilters) ? json_encode($additionalFilters) : null,
                        //         'created_at'         => date('Y-m-d H:i:s'),
                        //     ]
                        // ];

                        $indicatorInsertedRows++;
                    }
                }

                $totalPages = $metaData['totalPages'] ?? 1;
                $page++;
                
            } while ($page <= $totalPages);

            // Update tracker with success and fetched rows
            DB::table('dataset_import_trackers')->where('id', $this->trackerId)->update([
                'status' => 'completed',
                'fetched_rows' => $indicatorInsertedRows,
                'updated_at' => now(),
            ]);

            Log::info("Micro-Job Completed: Tracker ID {$this->trackerId} fetched {$indicatorInsertedRows} rows.");

        } catch (\Exception $e) {
            DB::table('dataset_import_trackers')->where('id', $this->trackerId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);
            Log::error("Micro-Job Failed: Tracker ID {$this->trackerId}. Error: " . $e->getMessage());
            throw $e; // Retry
        }
    }

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
                ->retry(3, 2000)
                ->timeout(60)
                ->connectTimeout(30)
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
