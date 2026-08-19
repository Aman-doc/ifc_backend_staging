<?php

namespace App\Http\Controllers;

use App\Services\BigQueryService;
use Illuminate\Http\Request;

class BigQueryController extends Controller
{
    protected $bigQueryService;

    public function __construct(BigQueryService $bigQueryService)
    {
        $this->bigQueryService = $bigQueryService;
    }

    /**
     * Data Insert Karein
     */
    public function insert(Request $request)
    {
        // .env se dataset aur table fetch karein
        $projectId = config('services.bigquery.project_id');
        $datasetId = config('services.bigquery.dataset');
        $tableId   = config('services.bigquery.table');

        $rowsToInsert = [
            [
                'id'         => 1,
                'name'       => 'Rahul Sharma',
                'email'      => 'rahul@example.com',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id'         => 2,
                'name'       => 'Priya Verma',
                'email'      => 'priya@example.com',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $result = $this->bigQueryService->insertData($datasetId, $tableId, $rowsToInsert);

        return response()->json($result);
    }

    /**
     * Data Fetch/Read Karein
     */
    public function fetch()
    {
        $projectId = config('services.bigquery.project_id');
        $datasetId = config('services.bigquery.dataset');
        $tableId   = config('services.bigquery.table');

        // Dynamic SQL Query
        $query = "SELECT * FROM `{$projectId}.{$datasetId}.{$tableId}` ORDER BY created_at DESC LIMIT 10";

        $data = $this->bigQueryService->runQuery($query);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Data Update Karein
     */
    public function update()
    {
        $projectId = config('services.bigquery.project_id');
        $datasetId = config('services.bigquery.dataset');
        $tableId   = config('services.bigquery.table');

        $query  = "UPDATE `{$projectId}.{$datasetId}.{$tableId}` SET name = 'Rahul Kumar' WHERE id = 1";
        $result = $this->bigQueryService->executeDml($query);

        return response()->json($result);
    }

    /**
     * Data Delete Karein
     */
    public function delete()
    {
        $projectId = config('services.bigquery.project_id');
        $datasetId = config('services.bigquery.dataset');
        $tableId   = config('services.bigquery.table');

        $query  = "DELETE FROM `{$projectId}.{$datasetId}.{$tableId}` WHERE id = 2";
        $result = $this->bigQueryService->executeDml($query);

        return response()->json($result);
    }
}