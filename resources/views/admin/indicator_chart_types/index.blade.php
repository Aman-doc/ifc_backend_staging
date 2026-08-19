@extends('admin.layouts.app')

@section('title', 'Indicator Chart Types')

@section('content')
<div class="container mx-auto px-4 py-6">
    
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Manage Indicator Chart Types
            </h1>
            <p class="text-sm text-gray-500">Hold <kbd class="bg-gray-100 border px-1 rounded text-xs">Ctrl</kbd> or <kbd class="bg-gray-100 border px-1 rounded text-xs">Cmd</kbd> to select multiple chart types.</p>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-xl shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- Indicators Table Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="p-4 w-12">#</th>
                        <th class="p-4 w-1/4">Indicator Name</th>
                        <th class="p-4">Select Chart Types</th>
                        <th class="p-4 text-center w-32">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($indicators as $indicator)
                        <tr class="hover:bg-gray-50 transition-all">
                            <td class="p-4 font-medium text-gray-600">{{ $loop->iteration }}</td>
                            <td class="p-4">
                                <span class="font-semibold text-gray-800">{{ $indicator->name }}</span>
                                @if($indicator->dataSource)
                                    <span class="block text-xs text-gray-400">Source: {{ $indicator->dataSource->name ?? 'N/A' }}</span>
                                @endif
                            </td>
                            
                            <!-- Update Form for Each Row -->
                            <form action="{{ route('admin.indicator_chart_type.update', $indicator->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                
                                <td class="p-4">
                                    @php
                                        $selectedCharts = is_array($indicator->chart_type) 
                                            ? $indicator->chart_type 
                                            : json_decode($indicator->chart_type ?? '[]', true);
                                    @endphp

                                    <select name="chart_types[]" 
                                            multiple 
                                            class="w-full h-28 p-2 border border-gray-300 rounded-xl text-xs focus:ring-green-500 focus:border-green-500 bg-white">
                                        @foreach($chartTypes as $value => $label)
                                            <option value="{{ $value }}" {{ in_array($value, $selectedCharts ?? []) ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                
                                <td class="p-4 text-center align-middle">
                                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl text-xs transition-all shadow-sm">
                                        Save Types
                                    </button>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-400">No indicators found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection