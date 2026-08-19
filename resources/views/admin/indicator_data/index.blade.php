@extends('admin.layouts.app')

@section('title', 'Indicator Data')

@section('content')
<div class="container mx-auto px-4 py-6">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Indicator Data Values</h1>
            <p class="text-sm text-gray-500">Upload Excel/CSV sheets and manage indicator records.</p>
        </div>
    </div>

    <!-- Alert Messages for Normal Requests -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Dynamic Alert Box for Sync Results -->
    <div id="sync-alert" class="hidden mb-4 p-4 rounded-xl shadow-sm border"></div>

    <!-- MoSPI Sync Action Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">MoSPI API Live Sync</h2>
        <p class="text-sm text-gray-500 mb-4">Click below buttons to pull live Datasets and Indicators from MoSPI MCP API.</p>

        <div class="flex flex-wrap items-center gap-4">
            <!-- Sync Data Sources Button -->
            <button type="button" id="btn-sync-datasets" onclick="syncMospiData('datasets')" 
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-all shadow-sm flex items-center gap-2">
                <svg id="icon-datasets" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span id="text-datasets">Sync Data Sources</span>
            </button>

            <!-- Sync Indicators Button -->
            <button type="button" id="btn-sync-indicators" onclick="syncMospiData('indicators')" 
                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl transition-all shadow-sm flex items-center gap-2">
                <svg id="icon-indicators" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span id="text-indicators">Sync Indicators</span>
            </button>
        </div>
    </div>

    <!-- Excel Import Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Import Excel / CSV File</h2>
        <form action="{{ route('admin.indicator_data.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row items-center gap-4">
            @csrf
            <div class="w-full md:w-1/2">
                <input type="file" name="file" required accept=".xlsx, .xls, .csv"
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 border border-gray-200 rounded-xl cursor-pointer">
            </div>
            <button type="submit" class="w-full md:w-auto px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl transition-all shadow-sm">
                Upload & Import
            </button>
        </form>
    </div>

</div>

<!-- JavaScript for Sync Handling -->
<script>
function syncMospiData(type) {
    const isDatasets = type === 'datasets';
    const btn = document.getElementById(isDatasets ? 'btn-sync-datasets' : 'btn-sync-indicators');
    const textSpan = document.getElementById(isDatasets ? 'text-datasets' : 'text-indicators');
    const alertBox = document.getElementById('sync-alert');

    const url = isDatasets ? "{{ route('admin.mospi.datasets') }}" : "{{ route('admin.mospi.indicators') }}";
    const originalText = isDatasets ? 'Sync Data Sources' : 'Sync Indicators';

    // UI Loading State
    btn.disabled = true;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    textSpan.innerText = 'Syncing... Please wait';

    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        alertBox.classList.remove('hidden', 'bg-red-100', 'border-red-500', 'text-red-700', 'bg-green-100', 'border-green-500', 'text-green-700');

        if (data.status === 'success') {
            alertBox.classList.add('bg-green-100', 'border-l-4', 'border-green-500', 'text-green-800');
            alertBox.innerHTML = `<strong>Success!</strong> ${data.message}`;
        } else {
            alertBox.classList.add('bg-red-100', 'border-l-4', 'border-red-500', 'text-red-700');
            alertBox.innerHTML = `<strong>Error!</strong> ${data.message || 'Failed to sync data.'}`;
        }
    })
    .catch(error => {
        alertBox.classList.remove('hidden');
        alertBox.classList.add('bg-red-100', 'border-l-4', 'border-red-500', 'text-red-700');
        alertBox.innerHTML = `<strong>Error!</strong> Something went wrong: ${error.message}`;
    })
    .finally(() => {
        // Restore UI State
        btn.disabled = false;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
        textSpan.innerText = originalText;
    });
}
</script>
@endsection