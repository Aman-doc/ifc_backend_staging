<?php

namespace App\Http\Controllers\Api; // 1. Namespace me \Api add karein

use App\Http\Controllers\Controller; // 2. Base Controller import karein
use Illuminate\Http\Request;
use App\Helpers\BigQueryHelper;
use Illuminate\Support\Facades\Log;

class IndicatorDataController extends Controller
{
    /**
     * BigQuery Se Indicator Records Fetch Karne Ki API
     */
    public function getIndicatorData(Request $request)
    {

        $projectId = config('services.bigquery.project_id');
        $bqDataset = config('services.bigquery.dataset');
        $bqTable   = config('services.bigquery.table');


        // Full table path generate karein backticks (`) ke saath
        $fullTableName = "`{$projectId}.{$bqDataset}.{$bqTable}`";
        Log::info("FUll TABLE NAME: {$fullTableName}");

        // 1. Request Inputs & Default Parameters Validation
        $datasetId     = $request->input('dataset_id'); // e.g. 'AISHE', 'PLFS'
        $indicatorCode = $request->input('indicator_code');       // e.g. '1', 'IND_59'
        $stateIds      = $request->input('state_ids');            // Single int ya Array [1, 2, 10]
        $year          = $request->input('year');                 // e.g. '2021-22'
        $limit         = (int) $request->input('limit', 100);     // Default 100 rows limit

        if (!$indicatorCode) {
            return response()->json([
                'status'  => 'error',
                'message' => 'indicator_code parameter is required.'
            ], 422);
        }

        // State IDs ko array format me ensure karein
        if (!is_null($stateIds) && !is_array($stateIds)) {
            $stateIds = [$stateIds];
        }

       
        // 2. Dynamic Base SQL Query
        $sql = "
            SELECT 
                data_source_id,
                dataset_id,
                indicator_code,
                indicator_name,
                state_id,
                raw_state_name,
                year,
                value,
                additional_filters,
                created_at
            FROM {$fullTableName}
            WHERE dataset_id = @dataset_id
              AND indicator_code = @indicator_code
        ";

        $bindings = [
            'dataset_id'     => $datasetId,
            'indicator_code' => (string) $indicatorCode,
        ];

        // 3. Dynamic Filters Apply Karein

        // Filter A: Multiple / Single State Filter
        if (!empty($stateIds)) {
            $sql .= " AND state_id IN UNNEST(@state_ids)";
            $bindings['state_ids'] = array_map('intval', $stateIds);
        }

        // Filter B: Year Filter
        if (!empty($year)) {
            $sql .= " AND year = @year";
            $bindings['year'] = (string) $year;
        }

        // Ordering & Limits
        $sql .= " ORDER BY state_id ASC, year DESC LIMIT " . $limit;

        try {
            // 4. Execute Query using BigQueryHelper
            $results = BigQueryHelper::runQuery($sql, $bindings);

            // 5. Decode JSON additional_filters for easy frontend usage
            $formattedData = array_map(function ($row) {
                if (!empty($row['additional_filters']) && is_string($row['additional_filters'])) {
                    $row['additional_filters'] = json_decode($row['additional_filters'], true);
                }
                return $row;
            }, $results);

            return response()->json([
                'status'       => 'success',
                'total_records'=> count($formattedData),
                'filters_used' => [
                    'dataset_id'     => $datasetId,
                    'indicator_code' => $indicatorCode,
                    'state_ids'      => $stateIds,
                    'year'           => $year,
                ],
                'data'         => $formattedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch data from BigQuery: ' . $e->getMessage()
            ], 500);
        }
    }
}