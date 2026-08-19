@extends('admin.layouts.app')

@section('title', 'Edit Source')

@section('content')
<div class="max-w-5xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
    <div class="flex justify-between items-center pb-5 border-b border-gray-100">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Edit Source</h1>
            <p class="text-xs text-gray-500 mt-0.5">Update Data Sources and their selected Indicators</p>
        </div>
        <a href="{{ route('admin.sources.index') }}" class="px-4 py-2 text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all">
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

    <form action="{{ route('admin.sources.update', $source->id) }}" method="POST" class="mt-6">
        @csrf
        @method('PUT')

        {{-- Basic Information --}}
        <div class="grid grid-cols-1 gap-5 mb-6">
            <div>
                <label for="title" class="block text-xs font-semibold text-gray-700 uppercase mb-2">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="title" value="{{ old('title', $source->title) }}" required placeholder="Enter source title" 
                       class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold text-gray-700 uppercase mb-2">Description</label>
                <textarea name="description" id="description" rows="3" placeholder="Write description here..." 
                          class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">{{ old('description', $source->description) }}</textarea>
            </div>
        </div>

        @php
            // Saved Data Sources
            $savedDataSources = old('data_source_ids', $source->data_source_ids ?? []);
            if (!is_array($savedDataSources)) { $savedDataSources = []; }

            // Saved Indicators (Key-Value Array format)
            $savedIndicators = old('indicators', $source->indicator_ids ?? []);
            if (!is_array($savedIndicators)) { $savedIndicators = []; }
        @endphp

        {{-- Multiple Data Sources & Indicators Selection --}}
        <div class="mb-6">
            <label class="block text-xs font-semibold text-gray-700 uppercase mb-3">
                Select Data Sources & Indicators <span class="text-red-500">*</span>
            </label>

            <div class="space-y-4">
                @foreach($dataSources as $ds)
                    @php
                        $isDsChecked = in_array((string)$ds->id, array_map('strval', $savedDataSources));
                        // Access specific indicators for this Data Source
                        $dsIndicators = $savedIndicators[$ds->id] ?? $savedIndicators[(string)$ds->id] ?? [];
                        if (!is_array($dsIndicators)) { $dsIndicators = []; }
                    @endphp
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        {{-- Data Source Checkbox --}}
                        <div class="bg-gray-50 p-3.5 border-b border-gray-200 flex items-center justify-between">
                            <label class="flex items-center gap-3 cursor-pointer select-none">
                                <input type="checkbox" name="data_source_ids[]" value="{{ $ds->id }}" 
                                       onchange="toggleIndicators({{ $ds->id }}, this.checked)"
                                       class="ds-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                                       {{ $isDsChecked ? 'checked' : '' }}>
                                <span class="text-sm font-bold text-gray-800">
                                    {{ $ds->title ?? $ds->dataset_id }}
                                </span>
                            </label>
                            <span class="text-[11px] font-mono text-gray-400 bg-gray-200 px-2 py-0.5 rounded">ID: {{ $ds->dataset_id }}</span>
                        </div>

                        {{-- Child Indicators Container --}}
                        <div id="ds-indicators-{{ $ds->id }}" class="p-4 {{ $isDsChecked ? '' : 'hidden' }}">
                            <p class="text-[11px] font-semibold text-gray-500 uppercase mb-2">Select Indicators for {{ $ds->title ?? $ds->dataset_id }}:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @forelse($ds->indicators as $ind)
                                    @php
                                        $isIndChecked = in_array((string)$ind->id, array_map('strval', $dsIndicators));
                                    @endphp
                                    <label class="flex items-center justify-between cursor-pointer text-xs text-gray-700 hover:bg-gray-50 p-2 border border-gray-100 rounded-lg transition-all">
                                        <span class="flex items-center gap-2">
                                            <input type="checkbox" name="indicators[{{ $ds->id }}][]" value="{{ $ind->id }}" 
                                                   class="indicator-cb-{{ $ds->id }} rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
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

        {{-- Action Buttons --}}
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ route('admin.sources.index') }}" class="px-5 py-2.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-all">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all shadow-sm">
                Update Source
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