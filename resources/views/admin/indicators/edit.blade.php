@extends('admin.layouts.app')

@section('title', 'Edit Indicator Mapping')

@section('content')
<div class="p-6 max-w-4xl mx-auto space-y-6">
    
    <!-- Header Block -->
    <div class="flex justify-between items-center bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Modify Indicator Mappings</h1>
            <p class="text-xs text-gray-500 mt-1">Update tech codes, map parent hierarchies or rename aliases for frontend dashboard charts.</p>
        </div>
        <a href="{{ route('admin.indicators.index') }}" class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-xl transition-all">
            ← Back to List
        </a>
    </div>

    <!-- Main Edit Form Card -->
    <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
        <form action="{{ route('admin.indicators.update', $indicator->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Block 1: Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Official Indicator Name *</label>
                    <input type="text" name="name" value="{{ old('name', $indicator->name) }}" required 
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent @error('name') border-red-500 focus:ring-red-500 @enderror">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Display Alias Name (Chart/Frontend Label)</label>
                    <input type="text" name="alias" value="{{ old('alias', $indicator->alias) }}" placeholder="Leave blank to use official name"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent @error('alias') border-red-500 focus:ring-red-500 @enderror">
                    @error('alias') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Block 2: Tech Identifiers & Hierarchy -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Technical Indicator Code *</label>
                    <input type="text" name="indicator_code" value="{{ old('indicator_code', $indicator->indicator_code) }}" placeholder="e.g., 5 or ENROLL_CODE"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-green-500 focus:border-transparent @error('indicator_code') border-red-500 focus:ring-red-500 @enderror">
                    <p class="text-xs text-gray-400 mt-1">This code matches row conditions inside dataset values maps.</p>
                    @error('indicator_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Parent Section Association</label>
                    <select name="parent_id" 
                        {{ $hasChildren ? 'disabled' : '' }}
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white {{ $hasChildren ? 'opacity-60 bg-gray-50 cursor-not-allowed' : '' }}">
                        
                        @if($hasChildren)
                            <option value="">-- Standalone Master (Locked: Has Child Charts) --</option>
                        @else
                            <option value="">-- Standalone Master Section --</option>
                            @foreach($parentIndicators as $parent)
                                <option value="{{ $parent->id }}" {{ old('parent_id', $indicator->parent_id) == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }} (Code: {{ $parent->indicator_code ?? 'NULL' }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                    
                    @if($hasChildren)
                        <p class="text-xs text-blue-600 font-medium mt-1">ℹ️ This mapping contains active sub-charts. You cannot assign it a parent hierarchy.</p>
                    @else
                        <p class="text-xs text-gray-400 mt-1">Select a master indicator section if this row represents a breakdown series.</p>
                    @endif
                    @error('parent_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Block 3: Context Groupings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Mapped Data Source *</label>
                    <select name="data_source_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white">
                        @foreach($dataSources as $source)
                            <option value="{{ $source->id }}" {{ old('data_source_id', $indicator->data_source_id) == $source->id ? 'selected' : '' }}>
                                {{ $source->title ?? $source->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('data_source_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Dashboard Theme Category (Optional)</label>
                    <select name="theme_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white">
                        <option value="">-- No Specific Theme --</option>
                        @foreach($themes as $theme)
                            <option value="{{ $theme->id }}" {{ old('theme_id', $indicator->theme_id) == $theme->id ? 'selected' : '' }}>
                                {{ $theme->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin.indicators.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 text-sm transition-all">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl text-sm transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    Save Structural Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection