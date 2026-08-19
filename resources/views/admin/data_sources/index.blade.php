@extends('admin.layouts.app')

@section('title', 'Data Source')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    {{-- Header --}}
    <div class="p-6 bg-gray-50/50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Data Sources Management</h1>
            <p class="text-xs text-gray-500 mt-1">Manage data providers and source organizations</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1.5 rounded-full">
                Total: {{ $dataSources->total() }}
            </span>
            {{-- Add New Button removed --}}
        </div>
    </div>

    {{-- Success Alert --}}
    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 m-6 text-sm text-green-700 rounded-r-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 p-4 m-6 text-sm text-red-700 rounded-r-lg">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-100 text-gray-700 text-xs uppercase font-semibold tracking-wider border-b border-gray-200">
                    <th class="py-4 px-6 w-16">#ID</th>
                    <th class="py-4 px-6">Dataset ID</th>
                    <th class="py-4 px-6">Title</th>
                    <th class="py-4 px-6">Description</th>
                    <th class="py-4 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm text-gray-600">
                @forelse($dataSources as $source)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-6 font-semibold text-gray-400">
                            #{{ $dataSources->firstItem() ? ($dataSources->firstItem() + $loop->index) : $loop->iteration }}
                        </td>
                        <td class="py-4 px-6 font-medium text-gray-800">
                            <span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded-md text-xs font-mono border border-gray-200">
                                {{ $source->dataset_id }}
                            </span>
                        </td>
                        <td class="py-4 px-6 font-semibold text-gray-900">
                            {{ $source->title }}
                        </td>
                        <td class="py-4 px-6 max-w-sm text-gray-500 text-xs leading-relaxed">
                            {{ $source->description ?? 'No description added' }}
                        </td>
                        <td class="py-4 px-6 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="editSource({{ $source->id }}, '{{ addslashes($source->dataset_id) }}', '{{ addslashes($source->title) }}', '{{ addslashes($source->description ?? '') }}')" 
                                        class="text-blue-500 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50 transition-all" title="Edit">
                                    <i class="fa-regular fa-pen-to-square text-base"></i>
                                </button>

                                <form action="{{ route('admin.data_sources.destroy', $source->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this source?');">
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
                            No data sources available.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($dataSources->hasPages())
        <div class="p-4 border-t border-gray-200">
            {{ $dataSources->links() }}
        </div>
    @endif
</div>

{{-- Edit Modal Only --}}
<div id="sourceModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm hidden items-center justify-center z-50 transition-all">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 w-full max-w-md p-6 m-4">
        <div class="flex justify-between items-center pb-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-800">Edit Data Source</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="{{ route('admin.data_sources.store') }}" method="POST" class="mt-4">
            @csrf
            {{-- ID is strictly required for update --}}
            <input type="hidden" name="id" id="source_id" required>

            <div class="mb-4">
                <label for="dataset_id" class="block text-xs font-semibold text-gray-700 uppercase mb-2">Dataset ID</label>
                <input type="text" name="dataset_id" id="dataset_id" required placeholder="e.g. PLFS, CPI, ASI" 
                       class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition-all">
            </div>

            <div class="mb-4">
                <label for="source_title" class="block text-xs font-semibold text-gray-700 uppercase mb-2">Title</label>
                <input type="text" name="title" id="source_title" required placeholder="e.g. Periodic Labour Force Survey" 
                       class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition-all">
            </div>

            <div class="mb-5">
                <label for="source_description" class="block text-xs font-semibold text-gray-700 uppercase mb-2">Description (Optional)</label>
                <textarea name="description" id="source_description" rows="3" placeholder="Short detail about this data provider..." 
                          class="w-full px-4 py-2.5 text-sm rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition-all"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-all">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-all">
                    Update Source
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function editSource(id, datasetId, title, description) {
        document.getElementById('source_id').value = id;
        document.getElementById('dataset_id').value = datasetId;
        document.getElementById('source_title').value = title;
        document.getElementById('source_description').value = description;
        
        document.getElementById('sourceModal').classList.remove('hidden');
        document.getElementById('sourceModal').classList.add('flex');
    }

    function closeModal() {
        document.getElementById('sourceModal').classList.add('hidden');
        document.getElementById('sourceModal').classList.remove('flex');
    }
</script>
@endsection