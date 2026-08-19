@extends('admin.layouts.app')

@section('title', 'Create Theme')

@section('content')
<div class="max-w-5xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
    <div class="flex justify-between items-center pb-5 border-b border-gray-100">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Create New Theme</h1>
            <p class="text-xs text-gray-500 mt-0.5">Select Data Sources and choose their relevant Indicators</p>
        </div>
        <a href="{{ route('admin.themes.index') }}" class="px-4 py-2 text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 p-4 my-4 text-sm text-red-700 rounded-r-lg">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.themes.store') }}" method="POST" class="mt-6">
        @csrf

        {{-- Theme Name --}}
        <div class="mb-5">
            <label for="theme_name" class="block text-xs font-semibold text-gray-700 uppercase mb-2">Theme Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="theme_name" value="{{ old('name') }}" required placeholder="e.g. Climate Action, Forest Cover" 
                   class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition-all">
        </div>

        {{-- Theme Description --}}
        <div class="mb-6">
            <label for="theme_description" class="block text-xs font-semibold text-gray-700 uppercase mb-2">Description</label>
            <textarea name="description" id="theme_description" rows="3" placeholder="Brief details about this theme..." 
                      class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition-all">{{ old('description') }}</textarea>
        </div>

        {{-- Data Sources & Nested Indicators --}}
        <div class="mb-6">
            <label class="block text-xs font-semibold text-gray-700 uppercase mb-3">
                Select Data Sources & Indicators <span class="text-red-500">*</span>
            </label>

            <div class="space-y-4">
                @foreach($dataSources as $ds)
                    @php
                        $oldDataSources = old('data_source_ids', []);
                        $isDsChecked = in_array((string)$ds->id, array_map('strval', $oldDataSources));
                    @endphp
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        {{-- Data Source Checkbox --}}
                        <div class="bg-gray-50 p-3.5 border-b border-gray-200 flex items-center justify-between">
                            <label class="flex items-center gap-3 cursor-pointer select-none">
                                <input type="checkbox" name="data_source_ids[]" value="{{ $ds->id }}" 
                                       onchange="toggleIndicators({{ $ds->id }}, this.checked)"
                                       class="rounded border-gray-300 text-green-600 focus:ring-green-500 w-4 h-4"
                                       {{ $isDsChecked ? 'checked' : '' }}>
                                <span class="text-sm font-bold text-gray-800">
                                    {{ $ds->title ?? $ds->dataset_id }}
                                </span>
                            </label>
                            <span class="text-[11px] font-mono text-gray-400 bg-gray-200 px-2 py-0.5 rounded">ID: {{ $ds->dataset_id }}</span>
                        </div>

                        {{-- Indicators Checkboxes --}}
                        <div id="ds-indicators-{{ $ds->id }}" class="p-4 {{ $isDsChecked ? '' : 'hidden' }}">
                            <p class="text-[11px] font-semibold text-gray-500 uppercase mb-2">Select Indicators for {{ $ds->title ?? $ds->dataset_id }}:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @forelse($ds->indicators as $ind)
                                    @php
                                        $oldIndicators = old("indicators.{$ds->id}", []);
                                        $isIndChecked = in_array((string)$ind->id, array_map('strval', $oldIndicators));
                                    @endphp
                                    <label class="flex items-center justify-between cursor-pointer text-xs text-gray-700 hover:bg-gray-50 p-2 border border-gray-100 rounded-lg transition-all">
                                        <span class="flex items-center gap-2">
                                            <input type="checkbox" name="indicators[{{ $ds->id }}][]" value="{{ $ind->id }}" 
                                                   class="indicator-cb-{{ $ds->id }} rounded border-gray-300 text-green-600 focus:ring-green-500 w-3.5 h-3.5"
                                                   {{ $isIndChecked ? 'checked' : '' }}>
                                            <span>{{ $ind->name }}</span>
                                        </span>
                                        @if($ind->indicator_code)
                                            <span class="text-[10px] text-gray-400 font-mono">({{ $ind->indicator_code }})</span>
                                        @endif
                                    </label>
                                @empty
                                    <p class="text-xs text-gray-400 italic col-span-2">No indicators available for this Data Source.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ route('admin.themes.index') }}" class="px-5 py-2.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-all">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-all shadow-sm">
                Save Theme
            </button>
        </div>
    </form>
</div>

<script>
    function toggleIndicators(dsId, isChecked) {
        const container = document.getElementById(`ds-indicators-${dsId}`);
        if (isChecked) {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
            const checkboxes = document.querySelectorAll(`.indicator-cb-${dsId}`);
            checkboxes.forEach(cb => cb.checked = false);
        }
    }
</script>
@endsection