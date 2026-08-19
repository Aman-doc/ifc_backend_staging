<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\Admin\DataSourceController;
use App\Http\Controllers\Admin\IndicatorDataValueController;
use App\Http\Controllers\Admin\IndicatorChartTypeController;
use App\Http\Controllers\Admin\ChartFieldLabelController;
use App\Http\Controllers\Admin\IndicatorChartController;
use App\Http\Controllers\BigQueryController;
use App\Http\Controllers\MospiController;
use App\Http\Controllers\Admin\SourceController;
use App\Http\Controllers\Admin\ChartTypeController;
use App\Http\Controllers\Admin\ChartController;
use App\Http\Controllers\MospiDebugController;
use App\Http\Controllers\Admin\IndicatorController;

// mosapi check
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return view('admin.login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ==========================================
// ADMIN AUTHENTICATION ROUTES (OPEN)
// ==========================================
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::get('/admin', [AuthController::class, 'showLogin']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ==========================================
// SECURE ADMIN CONTROL HUB (AUTH REQUIRED)
// ==========================================
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard Default Home
    Route::get('/dashboard', function () {
        return view('admin.dashboard'); 
    })->name('dashboard');


    // Themes Routes
    Route::get('/themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::get('/themes/create', [ThemeController::class, 'create'])->name('themes.create');
    Route::post('/themes/store', [ThemeController::class, 'store'])->name('themes.store');
    Route::get('/themes/{id}/edit', [ThemeController::class, 'edit'])->name('themes.edit');
    Route::put('/themes/{id}', [ThemeController::class, 'update'])->name('themes.update');
    Route::delete('/themes/{id}', [ThemeController::class, 'destroy'])->name('themes.destroy');
    
    // Ajax Endpoint
    Route::get('/data-sources/{id}/indicators', [ThemeController::class, 'getIndicatorsByDataSource'])->name('themes.indicators-by-ds');


    // States Routes
    Route::resource('states', StateController::class)->except(['create', 'edit', 'show']);
    Route::post('states/{state}/alias', [StateController::class, 'storeAlias'])->name('states.alias.store');
    Route::delete('states/alias/{alias}', [StateController::class, 'destroyAlias'])->name('states.alias.destroy');
    Route::post('states/merge', [StateController::class, 'mergeStates'])->name('states.merge.submit');
    Route::patch('states/{state}/toggle-status', [StateController::class, 'toggleStatus'])->name('states.toggle-status');

    // Data Sources Routes
    Route::get('/data-sources', [DataSourceController::class, 'index'])->name('data_sources.index');
    Route::post('/data-sources/store', [DataSourceController::class, 'store'])->name('data_sources.store');
    Route::delete('/data-sources/{id}', [DataSourceController::class, 'destroy'])->name('data_sources.destroy');
    
    // Indicator Data Values Routes
    Route::get('/indicator-data', [IndicatorDataValueController::class, 'index'])->name('indicator_data.index');
    Route::post('/indicator-data/import', [IndicatorDataValueController::class, 'import'])->name('indicator_data.import');
    Route::delete('/indicator-data/{id}', [IndicatorDataValueController::class, 'destroy'])->name('indicator_data.destroy');

    // // bigquery routes
    Route::get('/bigquery/insert', [BigQueryController::class, 'insert']);
    Route::get('/bigquery/fetch', [BigQueryController::class, 'fetch']);
    Route::get('/bigquery/update', [BigQueryController::class, 'update']);
    Route::get('/bigquery/delete', [BigQueryController::class, 'delete']);


    // Sources Routes
    Route::get('/sources', [SourceController::class, 'index'])->name('sources.index');
    Route::get('/sources/create', [SourceController::class, 'create'])->name('sources.create');
    Route::post('/sources/store', [SourceController::class, 'store'])->name('sources.store');
    Route::get('/sources/{id}/edit', [SourceController::class, 'edit'])->name('sources.edit');
    Route::put('/sources/{id}', [SourceController::class, 'update'])->name('sources.update');
    Route::delete('/sources/{id}', [SourceController::class, 'destroy'])->name('sources.destroy');

    
    
    // Indicator Data Routes
    Route::get('/indicator-data', [IndicatorDataValueController::class, 'index'])->name('indicator_data.index');
    Route::post('/indicator-data/import', [IndicatorDataValueController::class, 'import'])->name('indicator_data.import');
    
    // MoSPI Sync Routes
    Route::get('/mospi-insert-datasets', [MospiController::class, 'FetchDataSources'])->name('mospi.datasets');
    Route::get('/mospi-insert-indicators', [MospiController::class, 'fetchAllIndicators'])->name('mospi.indicators');
    
    Route::post('/indicators', [IndicatorController::class, 'store'])->name('indicators.store');
    Route::post('/indicators/{indicator}/update-source', [IndicatorController::class, 'updateSource'])->name('indicators.update-source');
    
 
    // // chart type
        Route::resource('chart-types', ChartTypeController::class);

    // 📈 Charts Routes
    Route::get('/charts', [ChartController::class, 'index'])->name('charts.index');
    Route::get('/charts/create', [ChartController::class, 'create'])->name('charts.create');
    Route::post('/charts', [ChartController::class, 'store'])->name('charts.store');
    Route::post('/charts/{chart}/duplicate', [ChartController::class, 'duplicate'])->name('charts.duplicate');

    // {chart} use karein taaki Controller ki (Chart $chart) binding sahi se match ho
    Route::get('/charts/{chart}/edit', [ChartController::class, 'edit'])->name('charts.edit');
    Route::put('/charts/{chart}', [ChartController::class, 'update'])->name('charts.update');
    Route::delete('/charts/{chart}', [ChartController::class, 'destroy'])->name('charts.destroy');
    Route::post('charts/indicator/{id}/update-alias', [ChartController::class, 'updateAlias'])->name('charts.indicator.update-alias');
        


    // indicator
    Route::resource('indicators', IndicatorController::class);
        
    // Get Data from MOSPI API and Save to Database
    // Route::get('/mospi-insert-datasets', [MospiController::class, 'FetchDataSources']);
    // Route::get('/mospi-insert-indicators', [MospiController::class, 'FetchIndicators']);
});



 Route::get('/api-testing', [MospiDebugController::class, 'index']);