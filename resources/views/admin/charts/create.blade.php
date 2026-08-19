@extends('admin.layouts.app')

@section('title', 'Create Chart')

@push('styles')
<style>
    /* Color picker swatch styling */
    input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
    input[type="color"]::-webkit-color-swatch { border: none; border-radius: 4px; }

    /* WordPress-like Drag & Drop Styling for Value Cards */
    .color-card-item {
        cursor: grab !important;
        transition: all 0.2s ease;
    }
    .color-card-item:active {
        cursor: grabbing !important;
        background-color: #f1f5f9 !important;
    }
    /* Placeholder card during drag operation */
    .sortable-placeholder {
        background-color: #f8fafc !important;
        border: 2px dashed #cbd5e1 !important;
        border-radius: 0.75rem !important;
        height: 54px;
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">📈 Create Chart for: {{ $indicator->name }}</h1>
            <p class="text-sm text-gray-500">Data Source: <b>{{ $indicator->dataSource->title ?? 'N/A' }}</b> | Code: <code>{{ $indicator->indicator_code }}</code></p>
        </div>
        <a href="{{ route('admin.charts.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-xl">
            ← Back to List
        </a>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 max-w-5xl">
        <form action="{{ route('admin.charts.store') }}" method="POST">
            @csrf
            <input type="hidden" name="indicator_id" value="{{ $indicator->id }}">

            <!-- Chart Header Info (Updated Layout grid structure) -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-6">
                <!-- Chart Name -->
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">
                        Chart Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="chart_name" placeholder="e.g. Grouped Bar Chart" required
                           class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                </div>

                <!-- NEW FIELD: Source Text Field -->
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">
                        Data Source Text
                    </label>
                    <input type="text" name="source" placeholder="e.g. NFHS-5, NITI Aayog" 
                           class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                </div>

                <!-- Chart Type -->
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">
                        Chart Type <span class="text-red-500">*</span>
                    </label>
                    <select id="chartTypeSelect" name="chart_type_id" required onchange="renderDynamicFields(this)"
                            class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                        <option value="">-- Choose Chart Type --</option>
                        @foreach($chartTypes as $type)
                            <option value="{{ $type->id }}" data-fields='@json($type->fields_definition)'>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Display Order -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">Order</label>
                    <input type="number" name="display_order" value="0" min="0"
                           class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                </div>
            </div>

            <!-- Dynamic Form Fields Container -->
            <div id="dynamicFieldsContainer" class="space-y-4 mb-6"></div>

            <div class="flex justify-end border-t pt-4">
                <button type="submit" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-xl shadow-sm">
                    Save Chart Configuration
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
    const bqFilterData = @json($bqFilters['filter_data'] ?? []);
    const bqFilterKeys = @json($bqFilters['keys'] ?? []);
    const colorPalette = ['#36BAB9', '#0D5F7A', '#8CCDB5', '#F6BD16', '#E8684A', '#9254DE', '#FF9D4D', '#269A99'];

    function renderDynamicFields(selectElement) {
        const container = document.getElementById('dynamicFieldsContainer');
        container.innerHTML = '';

        const selectedOption = selectElement.options[selectElement.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        const fields = JSON.parse(selectedOption.getAttribute('data-fields') || '[]');

        fields.forEach(field => {
            const fieldWrapper = document.createElement('div');
            fieldWrapper.className = 'p-5 border border-gray-100 bg-gray-50 rounded-xl space-y-4';

            let inputHtml = '';
            const key = field.key;
            const label = field.label_name;
            const type = field.type;
            const defaultVal = field.default_value || '';

            if (type === 'text') {
                inputHtml = `<input type="text" name="config[${key}]" value="${defaultVal}" class="w-full text-sm p-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">`;
            } else if (type === 'select') {
                const options = field.options || [];
                let optionsHtml = options.map(opt => `<option value="${opt}" ${opt === defaultVal ? 'selected' : ''}>${opt}</option>`).join('');
                inputHtml = `<select name="config[${key}]" class="w-full text-sm p-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">${optionsHtml}</select>`;
            } else if (type === 'radio') {
                const options = field.options || [];
                inputHtml = options.map(opt => `
                    <label class="inline-flex items-center gap-1.5 mr-4 text-xs font-medium text-gray-700">
                        <input type="radio" name="config[${key}]" value="${opt}" ${opt === defaultVal ? 'checked' : ''}> ${opt}
                    </label>
                `).join('');
            } else if (type === 'checkbox') {
                const options = field.options || [];
                inputHtml = options.map(opt => `
                    <label class="inline-flex items-center gap-1.5 mr-4 text-xs font-medium text-gray-700">
                        <input type="checkbox" name="config[${key}][]" value="${opt}"> ${opt}
                    </label>
                `).join('');
            } else if (type === 'indicator') {
                let keyOptionsHtml = '<option value="">-- Select Key --</option>';
                bqFilterKeys.forEach(k => {
                    keyOptionsHtml += `<option value="${k}">${k.toUpperCase()}</option>`;
                });

                inputHtml = `
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="w-64">
                                <select name="config[${key}][indicator_key]" onchange="handleIndicatorKeyChange(this, '${key}')"
                                        class="w-full text-sm p-2.5 bg-white border border-gray-200 rounded-xl focus:outline-none focus:border-green-500 font-medium">
                                    ${keyOptionsHtml}
                                </select>
                            </div>

                            <div class="flex items-center gap-3 text-xs font-semibold ml-auto bg-white p-2 rounded-xl border border-gray-100 shadow-sm">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer text-teal-700">
                                    <input type="checkbox" name="config[${key}][default_first_value]" value="1" class="rounded text-teal-600 focus:ring-teal-500">
                                    Default First Value
                                </label>
                                <span class="text-gray-200">|</span>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer text-blue-600">
                                    <input type="checkbox" name="config[${key}][filter]" value="1" checked class="rounded text-blue-600 focus:ring-blue-500">
                                    Filter
                                </label>
                                <span class="text-gray-200">|</span>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer text-purple-600">
                                    <input type="checkbox" name="config[${key}][multiple_select]" value="1" checked class="rounded text-purple-600 focus:ring-purple-500">
                                    Multiple
                                </label>
                            </div>
                        </div>
                        <div id="hidden_inputs_store_${key}"></div>
                        <div id="color_section_${key}" class="hidden border-t border-gray-200/60 pt-4 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase text-gray-500 tracking-wider flex items-center gap-1.5">
                                    📋 Manage Values & Order
                                </span>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="generateColorsForField('${key}')" class="text-xs text-white font-medium bg-green-600 hover:bg-green-700 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1 shadow-sm">
                                        🎨 Add Colors
                                    </button>
                                    <button type="button" onclick="clearAllColors('${key}')" class="text-xs text-red-600 hover:text-red-700 font-medium bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors">
                                        🗑️ Clear Colors
                                    </button>
                                </div>
                            </div>
                            <div id="colors_container_${key}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 py-1"></div>
                        </div>
                    </div>
                `;
            }

            fieldWrapper.innerHTML = `
                <label class="block text-xs font-bold uppercase text-gray-700 tracking-wide">${label} <span class="text-gray-400 font-normal">(${type})</span></label>
                ${inputHtml}
            `;
            container.appendChild(fieldWrapper);
        });
    }

    let selectedKeyMap = {};

    function handleIndicatorKeyChange(selectEl, fieldKey) {
        const selectedKey = selectEl.value;
        const colorSection = document.getElementById('color_section_' + fieldKey);
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);

        colorsContainer.innerHTML = '';

        if (!selectedKey || !bqFilterData[selectedKey]) {
            colorSection.classList.add('hidden');
            delete selectedKeyMap[fieldKey];
            syncHiddenInputsAndOrder(fieldKey);
            return;
        }

        selectedKeyMap[fieldKey] = selectedKey;
        colorSection.classList.remove('hidden');

        const values = bqFilterData[selectedKey];
        values.forEach((val, idx) => {
            const defaultColor = colorPalette[idx % colorPalette.length];
            createValueCard(fieldKey, val, defaultColor);
        });

        syncHiddenInputsAndOrder(fieldKey);

        new Sortable(colorsContainer, {
            animation: 180,
            ghostClass: 'sortable-placeholder',
            onEnd: function () {
                syncHiddenInputsAndOrder(fieldKey);
            }
        });
    }

    function createValueCard(fieldKey, val, colorHex = null) {
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        const safeValId = val.replace(/[^a-zA-Z0-9]/g, '_');
        const cardId = `color_card_${fieldKey}_${safeValId}`;

        if (document.getElementById(cardId)) return;

        const card = document.createElement('div');
        card.id = cardId;
        card.setAttribute('data-val', val);
        card.className = 'color-card-item flex items-center justify-between p-3 bg-white border border-gray-200 rounded-xl shadow-sm hover:border-gray-300';
        
        let colorHtml = '';
        if (colorHex) {
            colorHtml = `
                <input type="color" value="${colorHex}" 
                       onchange="updateColorText('${fieldKey}', '${safeValId}', this.value)"
                       class="w-5 h-5 rounded cursor-pointer border-0 p-0 bg-transparent">
                <input type="text" id="hex_${fieldKey}_${safeValId}" name="config[${fieldKey}][colors][${val}]" value="${colorHex}" 
                       onchange="updateColorPicker('${fieldKey}', '${safeValId}', this.value)"
                       class="w-16 px-1 py-0.5 text-[11px] text-center border border-gray-200 rounded-md font-mono uppercase focus:outline-none focus:border-green-500">
            `;
        }

        card.innerHTML = `
            <div class="flex items-center gap-2 overflow-hidden mr-2 select-none">
                <svg class="w-4 h-4 text-gray-400 flex-shrink-0 cursor-grab" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span class="text-xs font-semibold text-gray-700 truncate" title="${val}">${val}</span>
            </div>
            <div id="color_wrapper_${fieldKey}_${safeValId}" class="color-wrapper flex items-center gap-1.5 flex-shrink-0">
                ${colorHtml}
                <button type="button" onclick="removeCardValue('${fieldKey}', '${safeValId}')" class="text-gray-400 hover:text-red-500 font-bold text-base pl-1 transition-colors">&times;</button>
            </div>
        `;
        colorsContainer.appendChild(card);
    }

    function generateColorsForField(fieldKey) {
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        if (!colorsContainer) return;

        const cards = colorsContainer.querySelectorAll('.color-card-item');
        cards.forEach((card, idx) => {
            const val = card.getAttribute('data-val');
            const safeValId = val.replace(/[^a-zA-Z0-9]/g, '_');
            const wrapper = document.getElementById(`color_wrapper_${fieldKey}_${safeValId}`);
            
            if (wrapper) {
                const assignedColor = colorPalette[idx % colorPalette.length];
                wrapper.innerHTML = `
                    <input type="color" value="${assignedColor}" 
                           onchange="updateColorText('${fieldKey}', '${safeValId}', this.value)"
                           class="w-5 h-5 rounded cursor-pointer border-0 p-0 bg-transparent">
                    <input type="text" id="hex_${fieldKey}_${safeValId}" name="config[${fieldKey}][colors][${val}]" value="${assignedColor}" 
                           onchange="updateColorPicker('${fieldKey}', '${safeValId}', this.value)"
                           class="w-16 px-1 py-0.5 text-[11px] text-center border border-gray-200 rounded-md font-mono uppercase focus:outline-none focus:border-green-500">
                    <button type="button" onclick="removeCardValue('${fieldKey}', '${safeValId}')" class="text-gray-400 hover:text-red-500 font-bold text-base pl-1 transition-colors">&times;</button>
                `;
            }
        });
    }

    function clearAllColors(fieldKey) {
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        if (!colorsContainer) return;

        const cards = colorsContainer.querySelectorAll('.color-card-item');
        cards.forEach(card => {
            const val = card.getAttribute('data-val');
            const safeValId = val.replace(/[^a-zA-Z0-9]/g, '_');
            const wrapper = document.getElementById(`color_wrapper_${fieldKey}_${safeValId}`);
            
            if (wrapper) {
                wrapper.innerHTML = `
                    <button type="button" onclick="removeCardValue('${fieldKey}', '${safeValId}')" class="text-gray-400 hover:text-red-500 font-bold text-base pl-1 transition-colors">&times;</button>
                `;
            }
        });
    }

    function syncHiddenInputsAndOrder(fieldKey) {
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        const hiddenStore = document.getElementById(`hidden_inputs_store_${fieldKey}`);
        if (!hiddenStore) return;
        
        hiddenStore.innerHTML = '';

        if (colorsContainer) {
            colorsContainer.querySelectorAll('.color-card-item').forEach(card => {
                const val = card.getAttribute('data-val');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `config[${fieldKey}][values][]`;
                input.value = val;
                hiddenStore.appendChild(input);
            });
        }
    }

    function removeCardValue(fieldKey, safeValId) {
        const card = document.getElementById(`color_card_${fieldKey}_${safeValId}`);
        if (card) {
            card.remove();
            syncHiddenInputsAndOrder(fieldKey);
        }
    }

    function updateColorText(fieldKey, safeValId, hex) {
        const hexInput = document.getElementById(`hex_${fieldKey}_${safeValId}`);
        if (hexInput) hexInput.value = hex.toUpperCase();
    }

    function updateColorPicker(fieldKey, safeValId, hex) {
        const card = document.getElementById(`color_card_${fieldKey}_${safeValId}`);
        if (card) {
            const colorPicker = card.querySelector('input[type="color"]');
            if (colorPicker) colorPicker.value = hex;
        }
    }
</script>
@endpush