<?php

use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\DataSourceController;
use App\Http\Controllers\Api\IndicatorDataController;
use App\Http\Controllers\Api\McpListDatasetsController;
use App\Http\Controllers\MospiDebugController;

 
Route::get('/get-indicator-data', [IndicatorDataController::class, 'getIndicatorData']);
   
// API Routes  
Route::apiResource('themes', ThemeController::class);
Route::get('theme-indicators', [ThemeController::class, 'getThemeWithIndicators']);
Route::get('theme-data-sources', [ThemeController::class, 'theme_data_sources']);


Route::apiResource('states', StateController::class);
Route::apiResource('data-sources', DataSourceController::class);

// New Indicator Data Routes
Route::get('indicator-data', [IndicatorDataController::class, 'index']);
Route::get('indicator-data/available-filters', [IndicatorDataController::class, 'getFilters']);
Route::get('indicator-data/{id}', [IndicatorDataController::class, 'getIndicatorData']);

//  
Route::get('mcp-list-datasets', [McpListDatasetsController::class, 'index']);
Route::get('get-indicators', [McpListDatasetsController::class, 'get_indicators']);


Route::get('mcp-api-test', [MospiDebugController::class, 'index']);
Route::get('mcp-api-test-get-datasets', [MospiDebugController::class, 'getDatasets']);
Route::get('mcp-api-test-get-getMetadata', [MospiDebugController::class, 'getMetadata']);
Route::get('mcp-api-test-get-getData', [MospiDebugController::class, 'getData']);
Route::get('mcp-api-test-get-indicators', [MospiDebugController::class, 'get_indicators']);


// Route::get('/debug/dataset/{datasetId}', [MospiDebugController::class, 'inspectDatasetData']);




