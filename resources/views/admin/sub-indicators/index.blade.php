@extends('admin.layouts.app')

@section('title', 'Manage Sub Indicators')

@section('content')
<div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sub Indicators Management</h1>
            <p class="text-sm text-gray-500">Manage original sub-indicators and assign custom Alias Names for display.</p>
        </div>
    </div>

    <!-- Success Flash Message -->
    @if(session('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-xl bg-green-50 border border-green-200">
            {{ session('success') }}
        </div>
    @endif

    <!-- Search & Filter Card -->
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('admin.sub-indicators.index') }}" class="flex gap-4">
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Search by Original Name or Alias..." 
                   class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded-xl text-sm font-medium hover:bg-green-700 transition-all">
                Search
            </button>
        </form>
    </div>

    <!-- Sub Indicators Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-600 font-semibold">
                        <th class="p-4">#</th>
                        <th class="p-4">Parent Indicator</th>
                        <th class="p-4">Original Name</th>
                        <th class="p-4">Alias / Custom Name</th>
                        <th class="p-4">Sector / Survey</th>
                        <th class="p-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($subIndicators as $key => $item)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="p-4 text-gray-500">{{ $subIndicators->firstItem() + $key }}</td>
                            <td class="p-4 font-medium text-gray-900">
                                {{ $item->indicator->name ?? 'N/A' }}
                                <br><span class="text-xs text-gray-400">Code: {{ $item->indicator->indicator_code ?? '-' }}</span>
                            </td>
                            <td class="p-4 text-gray-700 max-w-xs break-words">
                                {{ $item->name }}
                            </td>
                            
                            {{-- Edit Form per Row --}}
                            <form action="{{ route('admin.sub-indicators.update', $item->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <td class="p-4">
                                    <input type="text" name="alias_name" value="{{ old('alias_name', $item->alias_name) }}" 
                                           placeholder="Enter Alias Name..." 
                                           class="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:outline-none">
                                </td>
                                <td class="p-4 text-xs text-gray-500">
                                    <span class="inline-block bg-gray-100 px-2 py-1 rounded">{{ $item->sector ?? 'N/A' }}</span>
                                    <span class="inline-block bg-blue-50 text-blue-600 px-2 py-1 rounded mt-1">{{ $item->survey ?? 'N/A' }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition-all shadow-sm">
                                        Save
                                    </button>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-gray-400">No Sub Indicators found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-gray-100">
            {{ $subIndicators->links() }}
        </div>
    </div>
</div>
@endsection