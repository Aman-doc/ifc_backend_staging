<?php

namespace App\Helpers;

use App\Services\BigQueryService;
use Google\Cloud\BigQuery\BigQueryClient;

class BigQueryHelper
{

    // api 
    public static function runQuery(string $sql, array $bindings = []): array
    {
        // Real path resolver
        $keyFilePath = config('services.bigquery.key_file');
        
        // Agar path me '${STORAGE_PATH}' exact text aa raha ho, to resolve kar dein
        if (str_contains($keyFilePath, '${STORAGE_PATH}')) {
            $keyFilePath = str_replace('${STORAGE_PATH}', storage_path(), $keyFilePath);
        }

        $bigQuery = new BigQueryClient([
            'keyFilePath' => $keyFilePath ?: storage_path('app/project-ifc-bigquery.json'),
            'projectId'   => config('services.bigquery.project_id', 'project-14502015-6044-481f-b5f'),
        ]);

        $queryConfig = $bigQuery->query($sql);

        if (!empty($bindings)) {
            $queryConfig->parameters($bindings);
        }

        $queryResults = $bigQuery->runQuery($queryConfig);

        $data = [];
        foreach ($queryResults as $row) {
            $data[] = $row;
        }

        return $data;
    }
        // end

    protected static function getService(): BigQueryService
    {
        return app(BigQueryService::class);
    }

    /**
     * Common Insert Function
     * 
     * @param string $tableName
     * @param array $rows [[ 'col1' => 'val1' ], ...]
     * @return array
     */
    public static function insert(string $tableName, array $rows): array
    {
        $datasetId = config('services.bigquery.dataset');
        return self::getService()->insertData($datasetId, $tableName, $rows);
    }

    /**
     * Common Fetch Data Function
     * 
     * @param string $tableName
     * @param array $conditions e.g. ['id' => 1, 'status' => 'active']
     * @param array $columns Default '*'
     * @param int|null $limit
     * @return array
     */
    public static function fetch(string $tableName, array $conditions = [], array $columns = ['*'], ?int $limit = null): array
    {
        $projectId = config('services.bigquery.project_id');
        $datasetId = config('services.bigquery.dataset');
        
        $cols = implode(', ', $columns);
        $query = "SELECT {$cols} FROM `{$projectId}.{$datasetId}.{$tableName}`";

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $val) {
                $escapedVal = is_numeric($val) ? $val : "'" . addslashes($val) . "'";
                $whereClause[] = "{$field} = {$escapedVal}";
            }
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }

        if ($limit) {
            $query .= " LIMIT {$limit}";
        }

        return self::getService()->runQuery($query);
    }

    /**
     * Common Fetch Single Row By ID
     */
    public static function findById(string $tableName, $id, string $idColumn = 'id'): ?array
    {
        $data = self::fetch($tableName, [$idColumn => $id], ['*'], 1);
        return $data[0] ?? null;
    }

    /**
     * Common Update Function
     * 
     * @param string $tableName
     * @param array $data ['name' => 'New Name']
     * @param array $conditions ['id' => 1]
     * @return array
     */
    public static function update(string $tableName, array $data, array $conditions): array
    {
        $projectId = config('services.bigquery.project_id');
        $datasetId = config('services.bigquery.dataset');

        $setClause = [];
        foreach ($data as $col => $val) {
            $escapedVal = is_null($val) ? 'NULL' : (is_numeric($val) ? $val : "'" . addslashes($val) . "'");
            $setClause[] = "{$col} = {$escapedVal}";
        }

        $whereClause = [];
        foreach ($conditions as $col => $val) {
            $escapedVal = is_numeric($val) ? $val : "'" . addslashes($val) . "'";
            $whereClause[] = "{$col} = {$escapedVal}";
        }

        $query = "UPDATE `{$projectId}.{$datasetId}.{$tableName}` SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);

        return self::getService()->executeDml($query);
    }

    /**
     * Common Delete Function By Conditions or ID
     */
    public static function delete(string $tableName, array $conditions): array
    {
        $projectId = config('services.bigquery.project_id');
        $datasetId = config('services.bigquery.dataset');

        $whereClause = [];
        foreach ($conditions as $col => $val) {
            $escapedVal = is_numeric($val) ? $val : "'" . addslashes($val) . "'";
            $whereClause[] = "{$col} = {$escapedVal}";
        }

        $query = "DELETE FROM `{$projectId}.{$datasetId}.{$tableName}` WHERE " . implode(' AND ', $whereClause);

        return self::getService()->executeDml($query);
    }
}