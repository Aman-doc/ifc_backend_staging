@extends('admin.layouts.app')

@section('title', 'Indicators & Chart Setup')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-7xl">

    <!-- Page Title Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 pb-4 border-b border-gray-200/80">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 tracking-tight flex items-center gap-2">
                <span>📈</span> Indicators & Chart Configurations
            </h1>
            <p class="text-sm text-gray-500 mt-1">Manage created charts, edit settings, or create new charts for indicators.</p>
        </div>
    </div>

    <!-- Theme Filter Dropdown Section -->
    <div class="mb-8 bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm">
        <form method="GET" action="{{ route('admin.charts.index') }}" class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="grow max-w-md">
                <label for="theme_id" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                    Filter By Theme
                </label>
                <div class="relative">
                    <select name="theme_id" id="theme_id" onchange="this.form.submit()" 
                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 p-2.5 transition-all">
                        <option value="">-- All Themes --</option>
                        @foreach($themes as $theme)
                            <option value="{{ $theme->id }}" {{ request('theme_id') == $theme->id ? 'selected' : '' }}>
                                {{ $theme->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if(request('theme_id'))
                <div class="self-end pb-1">
                    <a href="{{ route('admin.charts.index') }}" 
                       class="inline-flex items-center gap-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear Filter
                    </a>
                </div>
            @endif
        </form>
    </div>

    <!-- Flash Success Message -->
    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-sm flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    <!-- Main Indicators Loop -->
    <div class="space-y-8">
        @forelse($indicators as $indicator)
            <div class="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden">
                
                <!-- TOP HEADER: Indicator Info, Source Text & Indicator Theme Order -->
                <div class="bg-slate-50/80 p-5 border-b border-gray-200/80 flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="grow">
                        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                            <span class="text-xs font-bold text-gray-400">#{{ $indicator->id }}</span>
                            
                            <!-- DB Master Source Badge -->
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                <svg class="w-3.5 h-3.5 mr-1 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s8-1.79-8-4"></path>
                                </svg>
                                {{ $indicator->dataSource->title ?? 'N/A' }}
                            </span>

                            <span class="text-xs font-medium text-gray-400">
                                Code: <code class="text-gray-700 font-mono bg-white px-2 py-0.5 rounded border border-gray-200">{{ $indicator->indicator_code ?? 'N/A' }}</code>
                            </span>
                        </div>

                        <!-- Full Indicator Title -->
                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $indicator->name }}
                        </h2>

                        <!-- DYNAMIC THEME-SPECIFIC SOURCE FIELD & DISPLAY ORDER FIELD -->
                        <div class="mt-4 pt-3 border-t border-gray-200/60 max-w-2xl">
                            @if(request('theme_id'))
                                @php
                                    $selectedThemeId = request('theme_id');
                                    $themeData = $indicator->source[$selectedThemeId] ?? [];

                                    // Extract Source Text & Display Order (Backward Compatible)
                                    $sourceText = is_array($themeData) ? ($themeData['text'] ?? '') : $themeData;
                                    $displayOrder = is_array($themeData) ? ($themeData['order'] ?? 0) : 0;
                                @endphp

                                <form action="{{ route('admin.indicators.update-source', $indicator->id) }}" method="POST" class="flex items-end gap-3 flex-wrap sm:flex-nowrap">
                                    @csrf
                                    <input type="hidden" name="theme_id" value="{{ $selectedThemeId }}">
                                    
                                    <!-- 1. Source Text Field -->
                                    <div class="grow min-w-[240px]">
                                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                                            Source for Current Selected Theme
                                        </label>
                                        <input 
                                            type="text" 
                                            name="source_text" 
                                            value="{{ $sourceText }}" 
                                            placeholder="e.g. Census 2011, Ministry of Finance..." 
                                            class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 p-2 transition-all shadow-inner"
                                        >
                                    </div>

                                    <!-- 2. Display Order Field (PAAS ME ADD KIYA GAYA H) -->
                                    <div class="w-24 shrink-0">
                                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                                            Theme Order
                                        </label>
                                        <input 
                                            type="number" 
                                            name="display_order" 
                                            value="{{ $displayOrder }}" 
                                            min="0" 
                                            placeholder="0" 
                                            class="w-full bg-white border border-gray-200 text-gray-800 text-xs text-center font-bold rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 p-2 transition-all shadow-inner"
                                        >
                                    </div>

                                    <!-- Submit Button -->
                                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-all shadow-sm shrink-0">
                                        Save Settings
                                    </button>
                                </form>
                            @else
                                <p class="text-[11px] text-amber-600 font-medium italic">
                                    ⚠️ Select a theme above to add or update dynamic source information and theme order.
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- Top Right Action: Create New Chart -->
                    <div class="shrink-0 md:pt-1">
                        <a href="{{ route('admin.charts.create', ['indicator_id' => $indicator->id]) }}" 
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl transition-all shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Create Chart
                        </a>
                    </div>
                </div>

                <!-- BOTTOM BODY: Configured Chart Cards Grid -->
                <div class="p-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4 flex items-center gap-1.5">
                        <span>📊</span> Configured Charts ({{ $indicator->charts->count() }})
                    </h3>

                    @if($indicator->charts->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($indicator->charts as $chart)
                                <div class="bg-gray-50/50 hover:bg-white rounded-xl border border-gray-200 p-4 transition-all duration-200 hover:shadow-md flex flex-col justify-between space-y-4">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-100 rounded text-[11px] font-bold uppercase">
                                                {{ $chart->chartType->name ?? 'Chart' }}
                                            </span>
                                            <span class="text-[11px] text-gray-400 font-medium">
                                                Order: <b class="text-gray-600">{{ $chart->display_order }}</b>
                                            </span>
                                        </div>
                                        <h4 class="text-base font-bold text-gray-800 line-clamp-2">
                                            {{ $chart->chart_name }}
                                        </h4>
                                    </div>

                                    <!-- Chart Actions Footer (Clean & Non-overlapping) -->
                                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                                        <span class="text-[11px] text-gray-400">ID: #{{ $chart->id }}</span>
                                        <div class="flex items-center gap-2">

                                            <!-- Edit / Delete Buttons for Chart -->
                                            <a href="{{ route('admin.charts.edit', $chart->id) }}" 
                                               class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/80 rounded-md text-xs font-semibold transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                Edit
                                            </a>
                                            <form action="{{ route('admin.charts.destroy', $chart->id) }}" method="POST" onsubmit="return confirm('Are you sure?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/80 rounded-md text-xs font-semibold transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Delete
                                                </button>
                                            </form>

                                        </div>
                                    </div>

                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-6 bg-gray-50 border border-dashed border-gray-200 rounded-xl text-center">
                            <p class="text-xs font-medium text-gray-400">No charts created yet for this indicator.</p>
                        </div>
                    @endif
                </div>

            </div>
        @empty
            <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-gray-200 p-6">
                <p class="text-sm font-medium text-gray-400">No indicators found for the selected theme.</p>
            </div>
        @endforelse
    </div>

</div>
@endsection