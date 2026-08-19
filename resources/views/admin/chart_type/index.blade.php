@extends('admin.layouts.app')

@section('title', 'Manage Chart Types')

@section('content')
<div class="container mx-auto px-4 py-6">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">📊 Chart Types Setup</h1>
            <p class="text-sm text-gray-500">Configure dynamic custom fields for pre-defined system chart types.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- Table List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-left border-collapse text-sm text-gray-600">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 border-b">
                <tr>
                    <th class="p-4 w-16">#ID</th>
                    <th class="p-4">Chart Type Name</th>
                    <th class="p-4">Slug</th>
                    <th class="p-4">Configured Fields</th>
                    <th class="p-4 text-center w-32">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($chartTypes as $index => $type)
                    <tr class="hover:bg-gray-50">
                        <td class="p-4">{{ $index + 1 }}</td>
                        <td class="p-4 font-bold text-gray-800">{{ $type->name }}</td>
                        <td class="p-4 text-xs text-gray-400 font-mono">{{ $type->slug }}</td>
                        <td class="p-4">
                            <div class="flex flex-wrap gap-2">
                                @forelse($type->fields_definition ?? [] as $field)
                                    <span class="bg-gray-100 text-gray-800 px-2.5 py-1 rounded-lg text-xs font-medium border border-gray-200">
                                        <b>{{ $field['label_name'] }}</b> 
                                        <span class="text-xs text-green-600">({{ strtoupper($field['type'] ?? 'TEXT') }})</span>
                                    </span>
                                @empty
                                    <span class="text-xs text-amber-500 font-medium">No fields configured</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <button onclick='openEditModal({{ $type->id }}, @json($type->name), @json($type->fields_definition))' class="px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 font-medium text-xs rounded-lg transition-all">Configure Fields</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-gray-400">No chart types found in database. Please import them via Excel/Seeder.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for Edit Chart Type Fields -->
<div id="chartTypeModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl p-6 w-full max-w-3xl shadow-xl border border-gray-100 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-gray-800">Configure Fields</h3>
                <p id="modalSubTitle" class="text-xs text-gray-400 font-medium"></p>
            </div>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>

        <form id="chartTypeForm" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <div class="flex justify-between items-center mb-3">
                    <label class="block text-xs font-semibold uppercase text-gray-500">DYNAMIC CUSTOM FIELDS (ACF BUILDER)</label>
                    <button type="button" onclick="addFieldRow()" class="px-3 py-1.5 bg-green-50 text-green-600 font-semibold text-xs rounded-lg hover:bg-green-100">
                        + Add Custom Field
                    </button>
                </div>

                <div id="labelsContainer" class="space-y-3">
                    <!-- Dynamic ACF Rows Added by JS -->
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 border-t pt-4">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-xl">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-xl shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    let fieldIndex = 0;

    function addFieldRow(data = {}) {
        const container = document.getElementById('labelsContainer');
        const div = document.createElement('div');
        div.className = 'p-4 border border-gray-200 rounded-xl bg-gray-50 space-y-3 field-row relative';
        
        const label = data.label_name || '';
        const type = data.type || 'text';
        const defaultValue = data.default_value || '';
        const options = data.options ? data.options.join(', ') : '';

        const showOptions = ['select', 'radio', 'checkbox'].includes(type);
        const isIndicator = type === 'indicator';

        div.innerHTML = `
            <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold text-lg">&times;</button>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Field Label Name</label>
                    <input type="text" name="fields[${fieldIndex}][label]" value="${label}" required placeholder="e.g. Label, Group, Filter"
                           class="w-full text-sm p-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-green-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Field Type</label>
                    <select name="fields[${fieldIndex}][type]" onchange="handleTypeChange(this)" class="w-full text-sm p-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-green-500">
                        <option value="text" ${type === 'text' ? 'selected' : ''}>Text Input</option>
                        <option value="select" ${type === 'select' ? 'selected' : ''}>Select Box Dropdown</option>
                        <option value="radio" ${type === 'radio' ? 'selected' : ''}>Radio Button</option>
                        <option value="checkbox" ${type === 'checkbox' ? 'selected' : ''}>Checkbox</option>
                        <option value="indicator" ${type === 'indicator' ? 'selected' : ''}>Indicator Field</option>
                    </select>
                </div>

                <div class="default-val-container ${isIndicator ? 'hidden' : ''}">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Default Value (Optional)</label>
                    <input type="text" name="fields[${fieldIndex}][default_value]" value="${defaultValue}" placeholder="Default value"
                           class="w-full text-sm p-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-green-500">
                </div>

                <div class="indicator-note ${isIndicator ? '' : 'hidden'} text-xs text-emerald-600 font-medium py-2">
                    ⚡ Indicator keys & values will be fetched dynamically while creating charts.
                </div>
            </div>

            <div class="options-container ${showOptions ? '' : 'hidden'}">
                <label class="block text-xs font-medium text-gray-600 mb-1">Select Options (Comma Separated)</label>
                <input type="text" name="fields[${fieldIndex}][options]" value="${options}" placeholder="e.g. Option 1, Option 2, Option 3"
                       class="w-full text-sm p-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-green-500">
            </div>
        `;
        
        container.appendChild(div);
        fieldIndex++;
    }

    function handleTypeChange(selectElement) {
        const parentRow = selectElement.closest('.field-row');
        const optionsDiv = parentRow.querySelector('.options-container');
        const optionsInput = optionsDiv.querySelector('input');
        const defaultValDiv = parentRow.querySelector('.default-val-container');
        const indicatorNote = parentRow.querySelector('.indicator-note');
        
        const val = selectElement.value;

        if (['select', 'radio', 'checkbox'].includes(val)) {
            optionsDiv.classList.remove('hidden');
            optionsInput.setAttribute('required', 'required'); // ऑप्शन्स ज़रूरी करें
        } else {
            optionsDiv.classList.add('hidden');
            optionsInput.removeAttribute('required');
        }

        if (val === 'indicator') {
            defaultValDiv.classList.add('hidden');
            indicatorNote.classList.remove('hidden');
        } else {
            defaultValDiv.classList.remove('hidden');
            indicatorNote.classList.add('hidden');
        }
}


   function openEditModal(id, name, fields) {
    document.getElementById('modalTitle').innerText = 'Configure Fields: ' + name;
    document.getElementById('modalSubTitle').innerText = 'Editing dynamic fields configuration for ' + name;
    document.getElementById('chartTypeForm').action = `/admin/chart-types/${id}`;
    
    const container = document.getElementById('labelsContainer');
    container.innerHTML = '';
    fieldIndex = 0;
    
    // Only add rows if existing fields exist
    if (fields && fields.length > 0) {
        fields.forEach(f => {
            addFieldRow(f);
        });
    }

    document.getElementById('chartTypeModal').classList.remove('hidden');
    document.getElementById('chartTypeModal').classList.add('flex');
}
    function closeModal() {
        document.getElementById('chartTypeModal').classList.remove('flex');
        document.getElementById('chartTypeModal').classList.add('hidden');
    }
</script>
@endsection