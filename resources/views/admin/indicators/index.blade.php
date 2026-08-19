@extends('admin.layouts.app')

@section('title', 'Indicators & Chart Setup')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">
    
    <!-- Top Row: Title & Quick Info -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Indicator & Source Mapping</h1>
            <p class="text-sm text-gray-500">Manage all indicators, link them to data sources, or add new ones instantly.</p>
        </div>
    </div>

    <!-- Section 1: Quick Create Form -->
    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create New Indicator / Virtual Mapping
        </h2>
        
        <form action="{{ route('admin.indicators.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Indicator Name *</label>
                    <input type="text" name="name" required placeholder="Enter name..." class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500">
                </div>

                {{-- Data Source --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Data Source *</label>
                    <select name="data_source_id" required class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500 bg-white">
                        <option value="">-- Choose Source --</option>
                        @foreach($dataSources as $source)
                            <option value="{{ $source->id }}" {{ isset($selectedSourceId) && $selectedSourceId == $source->id ? 'selected' : '' }}>
                                {{ $source->title ?? $source->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Parent Indicator --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Parent Section / Indicator (Optional)</label>
                    <select name="parent_id" class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500 bg-white">
                        <option value="">-- No Parent (Is Master) --</option>
                        @foreach($parentIndicators as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->name }} (Code: {{ $parent->indicator_code ?? 'None' }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                {{-- Indicator Code --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Indicator Code / Tech Code (Optional for Child Elements)</label>
                    <input type="text" name="indicator_code" placeholder="Leave empty for custom child maps" class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500">
                </div>

                {{-- Alias --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Alias (Custom Short Name)</label>
                    <input type="text" name="alias" placeholder="e.g. Enrolment Chart View" class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-2.5 rounded-xl transition-all text-sm flex items-center justify-center gap-2 shadow-sm">
                        Add Mapped Indicator
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Section 2: Data Source Wise Filter & List Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        
        <!-- Table Filter Header -->
        <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <h3 class="font-semibold text-gray-800">All Registered Indicators</h3>
            
            <!-- Exact State Form Binding Filter -->
            <form method="GET" action="{{ route('admin.indicators.index') }}" id="sourceFilterForm" class="flex items-center gap-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Filter By Source:</span>
                <select name="source_filter" onchange="document.getElementById('sourceFilterForm').submit();" class="text-sm border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-green-500 bg-white shadow-sm font-medium text-gray-700">
                    <option value="">-- View All Sources --</option>
                    @foreach($dataSources as $source)
                        <option value="{{ $source->id }}" {{ isset($selectedSourceId) && $selectedSourceId == $source->id ? 'selected' : '' }}>
                            {{ $source->title ?? $source->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <!-- Dynamic Table Grid -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider border-b border-gray-100">
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Indicator Name</th>
                        <th class="px-6 py-4">Mapped Data Source</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                    @forelse($indicators as $indicator)
                    <tr class="hover:bg-gray-50/80 transition-all">
                        <td class="px-6 py-4 font-mono text-xs text-gray-400">#{{ $indicator->id }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $indicator->name }}
                            @if($indicator->parent_id)
                                <span class="ml-2 px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-600 rounded">
                                    Child of: {{ $indicator->parent->name ?? 'N/A' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                {{ $indicator->dataSource->title ?? ($indicator->dataSource->name ?? 'N/A') }}
                            </span>
                       
                        <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                            {{-- Existing Edit Action --}}
                            <a href="{{ route('admin.indicators.edit', $indicator->id) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-green-600 hover:text-green-700 bg-green-50 hover:bg-green-100/70 px-3 py-1.5 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>

                            {{-- New Secure Delete Action --}}
                            <form action="{{ route('admin.indicators.destroy', $indicator->id) }}" method="POST" 
                                onsubmit="return confirm('{{ $indicator->parent_id === null ? 'Warning: Deleting this parent master indicator will automatically delete all its child chart mappings! Are you sure?' : 'Are you sure you want to delete this custom indicator?' }}');" 
                                class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100/70 px-3 py-1.5 rounded-lg transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-400">
                            No indicators found for the selected filter criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Links Configuration -->
        @if($indicators->hasPages())
        <div class="p-4 border-t border-gray-100 bg-gray-50/30 dynamic-pagination">
            {{ $indicators->appends(request()->query())->links('pagination::tailwind') }}
        </div>
        @endif
    </div>
</div>
@endsection