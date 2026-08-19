@extends('admin.layouts.app')

@section('title', 'Sources')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    {{-- Header --}}
    <div class="p-6 bg-gray-50/50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Sources Management</h1>
            <p class="text-xs text-gray-500 mt-1">Manage all user created sources</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1.5 rounded-full">
                Total: {{ $sources->total() }}
            </span>
            <a href="{{ route('admin.sources.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Add New Source
            </a>
        </div>
    </div>

    {{-- Success Alert --}}
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
                    <th class="py-4 px-6">Title</th>
                    <th class="py-4 px-6">Data Sources</th>
                    <th class="py-4 px-6">Description</th>
                    <th class="py-4 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm text-gray-600">
                @forelse($sources as $source)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-6 font-semibold text-gray-400">
                            #{{ $sources->firstItem() ? ($sources->firstItem() + $loop->index) : $loop->iteration }}
                        </td>
                        <td class="py-4 px-6 font-semibold text-gray-900">
                            {{ $source->title }}
                        </td>
                        {{-- Render Multiple Data Sources Badges --}}
                        <td class="py-4 px-6 text-gray-700">
                            <div class="flex flex-wrap gap-1">
                                @forelse($source->data_sources as $ds)
                                    <span class="bg-gray-100 text-gray-800 text-xs px-2.5 py-1 rounded-md font-medium border border-gray-200">
                                        {{ $ds->title ?? $ds->dataset_id }}
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400 italic">None</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="py-4 px-6 max-w-sm text-gray-500 text-xs leading-relaxed">
                            {{ $source->description ?? 'No description added' }}
                        </td>
                        <td class="py-4 px-6 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admin.sources.edit', $source->id) }}" 
                                   class="text-blue-500 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50 transition-all" title="Edit">
                                    <i class="fa-regular fa-pen-to-square text-base"></i>
                                </a>

                                <form action="{{ route('admin.sources.destroy', $source->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this source?');">
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
                        <td colspan="5" class="py-12 text-center text-gray-400">
                            No sources added yet. Click <strong>"Add New Source"</strong> to create one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sources->hasPages())
        <div class="p-4 border-t border-gray-200">
            {{ $sources->links() }}
        </div>
    @endif
</div>
@endsection