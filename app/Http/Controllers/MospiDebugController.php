<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\Indicator;
use App\Services\StateResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MospiDebugController extends Controller
{
    // api-testing
    public function index(){
        return "Api testing";
    }


    

    private function callMospiApi(string $methodName, array $arguments = []): ?array
    {
        try {
            $baseUrl = rtrim(env('MCP_BASE_URL'), '/');
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

            return is_array($parsedJson) ? $parsedJson : null;

        } catch (\Exception $e) {
            Log::error("MoSPI API Exception [{$methodName}]: " . $e->getMessage());
            return null;
        }
    }



    public function getDatasets() 
    {
        $data = $this->callMospiApi('list_datasets');

        if (!$data) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch datasets from MoSPI API.'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $data
        ], 200);
    }

    /**
     * Fetch metadata for a specific dataset (e.g., 'CPI')
     */
    public function getMetadata(Request $request)
    {
        // 1. Get dataset from query param or fallback to 'CPI'
        $dataset = $request->get('dataset');

        if (empty($dataset)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'dataset parameter is required'
            ], 400);
        }

        // 2. Call MoSPI MCP API with method 'get_metadata' and dataset argument
        $response = $this->callMospiApi('get_metadata', [
            
            'dataset' => $dataset, 
            'indicator_code' => 1,
        ]);

        if (!$response) {
            return response()->json([
                'status'  => 'error',
                'message' => "Failed to fetch metadata for dataset: {$dataset}"
            ], 500);
        }

        // 3. Return successful JSON response
        return response()->json([
            'status'  => 'success',
            'dataset' => $dataset,
            'data'    => $response
        ], 200);
    }

    // get indicator
     /**
     * Fetch metadata for a specific dataset (e.g., 'CPI')
     */
    public function get_indicators(Request $request)
    {
        // 1. Get dataset from query param or fallback to 'CPI'
        $dataset = $request->get('dataset');

        if (empty($dataset)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'dataset parameter is required'
            ], 400);
        }

        // 2. Call MoSPI MCP API with method 'get_metadata' and dataset argument
        $response = $this->callMospiApi('get_indicators', [
            'dataset' => $dataset
        ]);

        if (!$response) {
            return response()->json([
                'status'  => 'error',
                'message' => "Failed to fetch metadata for dataset: {$dataset}"
            ], 500);
        }

        // 3. Return successful JSON response
        return response()->json([
            'status'  => 'success',
            'dataset' => $dataset,
            'data'    => $response
        ], 200);
    }


    // get data
    public function getData(Request $request)
    {
        // 1. Dataset parameter (Default to 'CPI')
        $dataset = $request->get('dataset');

        // 2. Build arguments array from Request inputs
        // (Aap metadata response ke according filters accept kar sakte hain)
      $filters = array_filter([
        'base_year' => $request->filled('base_year') ? (int) $request->get('base_year') : 2010,
        'series'    => $request->get('series', 'Back'),
    ]);
        $arguments = [
            'dataset' => $dataset,
            'filters' => $filters,
        ];

        // 3. Call MoSPI API using helper
        $response = $this->callMospiApi('get_data', $arguments);

        if (!$response) {
            return response()->json([
                'status'  => 'error',
                'message' => "Failed to fetch data for dataset: {$dataset}"
            ], 500);
        }

        // 4. Return Data
        return response()->json([
            'status' => 'success',
            'params' => $arguments,
            'data'   => $response
        ], 200);
    }


    /*
    //  "CPI": {
                        "name": "Consumer Price Index",
                        "description": "Hierarchical commodity structure (Groups and Items) with base years 2010/2012/2024. Tracks consumer inflation across 600+ items organized into food, fuel, housing, clothing, and miscellaneous categories. Supports state-level analysis at group level and All-India analysis at item level.",
                        "use_for": "Retail inflation, price indices, cost of living, commodity price trends"
                    },
*/



    

    


}