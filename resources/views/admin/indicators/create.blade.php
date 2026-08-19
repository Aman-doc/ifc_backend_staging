@extends('admin.layouts.app')

@section('title', 'Indicators & Chart Setup')

@section('content')
<div class="max-w-4xl mx-auto my-6 p-6 bg-white rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ $parentIndicator ? 'Create Virtual Section for: ' . $parentIndicator->name : 'Create New Indicator' }}
        </h2>
        <a href="{{ route('admin.charts.index') }}" class="text-sm bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
            Back to Dashboard
        </a>
    </div>

    @if(session('error'))
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <form action="{{ route('admin.indicators.store') }}" method="POST" class="space-y-4">
        @csrf

        {{-- Hidden Parent ID reference for Virtual sections --}}
        @if($parentIndicator)
            <input type="hidden" name="parent_id" value="{{ $parentIndicator->id }}">
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Data Source Dropdown --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data Source *</label>
                <select name="data_source_id" class="w-full rounded border-gray-300 shadow-sm" required {{ $parentIndicator ? 'disabled' : '' }}>
                    <option value="">-- Select Source --</option>
                    @foreach($dataSources as $source)
                        <option value="{{ $source->id }}" 
                            {{ (old('data_source_id') == $source->id || ($parentIndicator && $parentIndicator->data_source_id == $source->id)) ? 'selected' : '' }}>
                            {{ $source->name }}
                        </option>
                    @endforeach
                </select>
                @if($parentIndicator)
                    {{-- Hidden input since disabled select items aren't submitted in request --}}
                    <input type="hidden" name="data_source_id" value="{{ $parentIndicator->data_source_id }}">
                @endif
                @error('data_source_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Theme Dropdown --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Theme (Optional)</label>
                <select name="theme_id" class="w-full rounded border-gray-300 shadow-sm">
                    <option value="">-- Select Theme --</option>
                    @foreach($themes as $theme)
                        <option value="{{ $theme->id }}" 
                            {{ (old('theme_id') == $theme->id || ($parentIndicator && $parentIndicator->theme_id == $theme->id)) ? 'selected' : '' }}>
                            {{ $theme->name }}
                        </option>
                    @endforeach
                </select>
                @error('theme_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Indicator Code --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Indicator Code / ID *</label>
                <input type="text" name="indicator_code" value="{{ old('indicator_code', $parentIndicator ? $parentIndicator->indicator_code : '') }}" 
                    class="w-full rounded border-gray-300 shadow-sm" placeholder="e.g., 350 or UNIV_COUNT" required>
                @error('indicator_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Custom Alias --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Custom Display Alias (Display Name)</label>
                <input type="text" name="alias" value="{{ old('alias') }}" 
                    class="w-full rounded border-gray-300 shadow-sm" placeholder="e.g., Total Active Universities">
                @error('alias') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Indicator Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Official Indicator Name *</label>
            <input type="text" name="name" value="{{ old('name', $parentIndicator ? $parentIndicator->name : '') }}" 
                class="w-full rounded border-gray-300 shadow-sm" placeholder="e.g., Number of Universities in States" required>
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-4">
            <button type="submit" class="w-full bg-indigo-600 text-white font-semibold py-2 rounded hover:bg-indigo-700 transition shadow">
                🚀 {{ $parentIndicator ? 'Create Duplicate Section' : 'Save Indicator' }}
            </button>
        </div>
    </form>
</div>
@endsection