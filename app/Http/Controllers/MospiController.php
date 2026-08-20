<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\Indicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessSourceDataImportJob;



class MospiController extends Controller
{
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
            // dd($body); // Debugging line to inspect the raw response body

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

    /**
     * Step 1: Datasets Fetch & Upsert (No Duplicate)
     */
    public function FetchDataSources()
    {
        try {
            $parsedJson = $this->callMospiApi('list_datasets');

            if (!$parsedJson) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to retrieve data from the MoSPI API service.'
                ], 400);
            }

            // Check if API returned an execution error
            if (isset($parsedJson['result']['isError']) && $parsedJson['result']['isError'] === true) {
                $errorText = $parsedJson['result']['content'][0]['text'] ?? 'Unknown API execution error.';
                return response()->json([
                    'status'   => 'error',
                    'message'  => 'MoSPI API returned an error response.',
                    'details'  => $errorText,
                    'raw'      => $parsedJson
                ], 422);
            }

            // Extract datasets list
            $datasetsList = $parsedJson['result']['structuredContent']['datasets'] ?? [];

            if (empty($datasetsList)) {
                $textData = $parsedJson['result']['content'][0]['text'] ?? '{}';
                $decodedText = json_decode($textData, true);
                $datasetsList = $decodedText['datasets'] ?? [];
            }

            $savedRecords = [];
            $createdCount = 0;
            $updatedCount = 0;

            foreach ($datasetsList as $datasetCode => $details) {
                $existingRecord = DataSource::where('dataset_id', $datasetCode)->first();

                if ($existingRecord) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }

                $dataSource = DataSource::updateOrCreate(
                    [
                        'dataset_id' => $datasetCode
                    ],
                    [
                        'title'          => $details['name'] ?? $datasetCode,
                        'description'    => $details['description'] ?? null,
                        'is_synced'      => true,
                        'last_synced_at' => now(),
                    ]
                );

                $savedRecords[] = [
                    'id'         => $dataSource->id,
                    'dataset_id' => $dataSource->dataset_id,
                    'title'      => $dataSource->title,
                    'status'     => $existingRecord ? 'updated' : 'created'
                ];
            }

            return response()->json([
                'status'        => 'success',
                'message'       => "Data sources synchronized successfully. Created: {$createdCount}, Updated: {$updatedCount}.",
                'total_records' => count($savedRecords),
                'saved_data'    => $savedRecords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'exception',
                'message' => 'An unexpected server error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 2: Indicators Fetch & Upsert (Fixed Argument Name for MoSPI FastMCP)
     */
    public function fetchIndicators(Request $request, string $datasetId)
    {
        Log::info("Import request received for dataset: {$datasetId}");
        //dd("Import request received for dataset: {$datasetId}"); // Debugging line
        try {
            // 1. Retrieve the DataSource record dynamically
            // $dataSource = DataSource::where('dataset_id', $datasetId)->first();
            $dataSource = DataSource::where('dataset_id',NSS77)->first();

            if (!$dataSource) {
                Log::error("Data source not found for dataset_id: {$datasetId}");
                return response()->json([
                    'status'  => 'error',
                    'message' => "Data source '{$datasetId}' not found in data_sources table."
                ], 404);
            }

            // 2. Dispatch the job passing the specific DataSource model
            ProcessSourceDataImportJob::dispatch($dataSource);

            Log::info("ProcessBigQueryImportJob dispatched for dataset: {$datasetId}");

            return response()->json([
                'status'     => 'success',
                'message'    => "Import job for '{$datasetId}' dispatched to background queue!",
                'queue_type' => config('queue.default'),
                'dataset_id' => $dataSource->dataset_id
            ], 200);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch import job for dataset {$datasetId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function fetchAllIndicators()
    {
        $dataSources = DataSource::whereIn('dataset_id', $this->simpleSources)->get();

        if ($dataSources->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No active data sources found.'
            ], 404);
        }

        $dispatched = [];
        foreach ($dataSources as $dataSource) {
            ProcessSourceDataImportJob::dispatch($dataSource);
            $dispatched[] = $dataSource->dataset_id;
        }
        

        return response()->json([
            'status'     => 'Success',
            'message'    => 'Import jobs dispatched for all data sources.',
            'datasets'   => $dispatched,
        ], 200);
    }

    private $simpleSources =  [
        // "PLFS",  //done on local
        // "CPI",
        // "IIP",
        // "ASI",
        // "NAS",
        // "WPI",
        // "ENERGY",
        // "AISHE",
        // "ASUSE",
        // "GENDER",
        // "NFHS",
        // "ENVSTATS",
        // "RBI",
        // "NSS77",
        // "NSS78",
        //    "NSS76",
        //    "NSS75E",
        //    "NSS79",
            "CPIALRL",
        //    "HCES",
        //    "TUS",
            // "EC",
        //    "UDISE",
        //    "MNRE",
        //    "NSS80"
    ];


    
}