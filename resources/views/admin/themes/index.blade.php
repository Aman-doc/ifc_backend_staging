@extends('admin.layouts.app')

@section('title', 'Manage Themes')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    {{-- Header --}}
    <div class="p-6 bg-gray-50/50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Themes Management</h1>
            <p class="text-xs text-gray-500 mt-1">Manage themes with multiple Data Sources and Indicators</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1.5 rounded-full">
                Total: {{ $themes->total() }}
            </span>
            <a href="{{ route('admin.themes.create') }}" class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Add New Theme
            </a>
        </div>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 m-6 text-sm text-green-700 rounded-r-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-100 text-gray-700 text-xs uppercase font-semibold tracking-wider border-b border-gray-200">
                    <th class="py-4 px-6 w-16">#ID</th>
                    <th class="py-4 px-6">Theme Info</th>
                    <th class="py-4 px-6">Data Sources Selected</th>
                    <th class="py-4 px-6">Total Indicators Attached</th>
                    <th class="py-4 px-6">Created Date</th>
                    <th class="py-4 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm text-gray-600">
                @forelse($themes as $theme)
                    @php
                        $dsCount = is_array($theme->data_source_ids) ? count($theme->data_source_ids) : 0;
                        $totalIndicators = 0;
                        if (is_array($theme->indicator_ids)) {
                            foreach ($theme->indicator_ids as $list) {
                                $totalIndicators += is_array($list) ? count($list) : 0;
                            }
                        }
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-6 font-semibold text-gray-400">
                           {{ $themes->firstItem() ? ($themes->firstItem() + $loop->index) : ($loop->iteration) }}
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-bold text-gray-900">{{ $theme->name }}</div>
                            @if($theme->description)
                                <div class="text-xs text-gray-500 max-w-xs truncate mt-0.5" title="{{ $theme->description }}">
                                    {{ $theme->description }}
                                </div>
                            @endif
                        </td>
                        <td class="py-4 px-6">
                            <span class="bg-gray-100 text-gray-700 text-xs px-2.5 py-1 rounded-md font-medium border border-gray-200">
                                {{ $dsCount }} Data Source(s)
                            </span>
                        </td>
                        <td class="py-4 px-6">
                            <span class="bg-blue-50 text-blue-700 text-xs px-2.5 py-1 rounded-md font-medium border border-blue-100">
                                {{ $totalIndicators }} Indicators
                            </span>
                        </td>
                        <td class="py-4 px-6 text-xs text-gray-500">
                            {{ $theme->created_at ? $theme->created_at->format('M d, Y') : 'N/A' }}
                        </td>
                        <td class="py-4 px-6 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admin.themes.edit', $theme->id) }}" 
                                   class="text-blue-500 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50 transition-all" title="Edit">
                                    <i class="fa-regular fa-pen-to-square text-base"></i>
                                </a>

                                <form action="{{ route('admin.themes.destroy', $theme->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this theme?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-all" title="Delete">
                                        <i class="fa-regular fa-trash-can text-base"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-gray-400">
                            No themes added yet. Click <strong>"Add New Theme"</strong> to create one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($themes->hasPages())
        <div class="p-4 border-t border-gray-200">
            {{ $themes->links() }}
        </div>
    @endif
</div>
@endsection