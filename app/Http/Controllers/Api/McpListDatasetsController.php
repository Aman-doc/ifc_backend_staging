<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class McpListDatasetsController extends Controller
{
    /**
     * Raw index endpoint if you just want the raw JSON-RPC response.
     */
    public function index()
    {
        $data = $this->callJsonRpcApi();
        
        return response()->json($data);
    }

    /**
     * Helper method to call the MCP SSE API and extract JSON.
     */
    private function callJsonRpcApi(): array
    {
        $client = new Client(['timeout' => 30.0]);

        $payload = [
            'jsonrpc' => '2.0',
            'id'      => uniqid(),
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'list_datasets',
                'arguments' => (object)[]
            ]
        ];

        try {
            $response = $client->post('https://mcp.mospi.gov.in/sse', [
                'json'    => $payload,
                'headers' => [
                    'Accept'       => 'text/event-stream',
                    'Content-Type' => 'application/json',
                ]
            ]);

            $streamBody = $response->getBody()->getContents();

            // Extract JSON payload from SSE line starting with "data:"
            if (preg_match('/data:\s*(\{.*\})/', $streamBody, $matches)) {
                return json_decode($matches[1], true) ?? [];
            }

            return ['raw_body' => $streamBody];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

  public function get_indicators(Request $request)
    {
         $id = $request->input('id');
          $data = $this->callJsonRpcApi($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Dataset ID is required.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Show dataset with ID: ' . $id,
            'data' => $data
        ]);
    }


}