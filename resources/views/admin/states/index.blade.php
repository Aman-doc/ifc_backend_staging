@extends('admin.layouts.app')

@section('title', 'Merge & Manage States')

@section('content')

<div class="p-6">

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl shadow-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Side: Merge Form (Only Two Select Boxes) -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sticky top-6">
                <h2 class="text-lg font-bold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-code-merge text-blue-600"></i> Merge States
                </h2>
                <p class="text-xs text-gray-500 mb-5">Duplicate entries ko select karke unhe sahi master state me merge karein.</p>
                
                <form action="{{ route('admin.states.merge.submit') }}" method="POST" class="space-y-5">
                    @csrf
                    
                    <!-- Select Box 1: Master State (Only Visible States) -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Choose Master State (Sahi Name)</label>
                        <select name="master_state_id" id="master_state_id" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-gray-50/50" required>
                            <option value="">-- Select Valid State --</option>
                            @foreach($states->where('status', 1) as $state)
                                <option value="{{ $state->id }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Select Box 2: Duplicate Target States (Multiple Select) -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Select Duplicate States to Merge</label>
                        <select name="duplicate_state_ids[]" id="duplicate_state_ids" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none min-h-[180px] bg-gray-50/50" multiple required>
                            @foreach($states as $state)
                                <option value="{{ $state->id }}">{{ $state->name }} (ID: {{ $state->id }})</option>
                            @endforeach
                        </select>
                        <small class="text-[11px] text-gray-400 mt-1.5 block leading-relaxed">
                            <i class="fa-solid fa-info-circle"></i> Ctrl (Win) ya Command (Mac) daba kar multiple entries select kar sakte hain.
                        </small>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-xl transition-all shadow-sm mt-4 flex items-center justify-center gap-2" onclick="return confirm('Are you sure? All related records across tables will be updated with the master state ID.')">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Merge & Process Data
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Side: Clean States List with Hide Option -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-map text-green-600"></i> States Master List
                    </h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <th class="p-4 w-16 text-center">#</th>
                                <th class="p-4">State Name</th>
                                <th class="p-4 w-32 text-center">Code</th>
                                <th class="p-4 text-center w-36">Visibility Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($states as $index => $state)
                                <tr class="hover:bg-gray-50/50 transition-all {{ $state->status == 0 ? 'bg-gray-50/40 opacity-70' : '' }}">
                                    <td class="p-4 font-medium text-gray-400 text-center">{{ $index + 1 }}</td>
                                    
                                    <!-- State Name -->
                                    <td class="p-4">
                                        <div class="font-bold text-gray-800 flex items-center gap-2">
                                            {{ $state->name }}
                                            @if($state->status == 0)
                                                <span class="text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded font-normal">Hidden</span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- State Code -->
                                    <td class="p-4 text-center font-mono text-xs text-gray-500">
                                        {{ $state->code ?? '—' }}
                                    </td>

                                    <!-- Hide / Show Action Button -->
                                    <td class="p-4 text-center whitespace-nowrap">
                                        <form action="{{ route('admin.states.update', $state->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <!-- Hidden values to toggle status field -->
                                            <input type="hidden" name="name" value="{{ $state->name }}">
                                            <input type="hidden" name="status" value="{{ $state->status == 1 ? 0 : 1 }}">
                                            
                                            @if($state->status == 1)
                                                <button type="submit" class="inline-flex items-center gap-1.5 bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 text-xs font-semibold px-3 py-1.5 rounded-xl transition-all shadow-sm">
                                                    <i class="fa-solid fa-eye-slash"></i> Hide State
                                                </button>
                                            @else
                                                <button type="submit" class="inline-flex items-center gap-1.5 bg-green-50 text-green-600 hover:bg-green-100 border border-green-200 text-xs font-semibold px-3 py-1.5 rounded-xl transition-all shadow-sm">
                                                    <i class="fa-solid fa-eye"></i> Make Visible
                                                </button>
                                            @endif
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-12 text-center text-gray-400">
                                        <i class="fa-solid fa-folder-open text-3xl mb-2 block text-gray-300"></i>
                                        No States Found in Database.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- JS Constraint: Master state select karne par multi-select se disable ho jaye -->
<script>
document.getElementById('master_state_id').addEventListener('change', function() {
    let masterId = this.value;
    let duplicateSelect = document.getElementById('duplicate_state_ids');
    
    for (let option of duplicateSelect.options) {
        if (option.value === masterId) {
            option.disabled = true;
            option.selected = false;
        } else {
            option.disabled = false;
        }
    }
});
</script>
@endsection