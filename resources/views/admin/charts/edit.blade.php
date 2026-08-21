@extends('admin.layouts.app')

@section('title', 'Edit Chart')

@push('styles')
<!-- Tom Select CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<style>
    /* Tom Select Modern Customization */
    .ts-wrapper.multi .ts-control {
        border-color: #e5e7eb !important;
        border-radius: 0.5rem !important;
        padding: 6px 8px !important;
        min-height: 38px !important;
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 6px !important;
        box-shadow: none !important;
    }
    .ts-wrapper.multi .ts-control > div {
        cursor: move !important; /* Visual handle indication */
        background-color: #f1f5f9 !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        color: #334155 !important;
        padding: 2px 24px 2px 8px !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        margin: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    /* Tom Select Remove/Cross Button Fix */
    .ts-wrapper.multi .ts-control .remove {
        border-left: 1px solid #cbd5e1 !important;
        border-top-right-radius: 5px !important;
        border-bottom-right-radius: 5px !important;
        color: #ef4444 !important;
        padding: 0 6px !important;
        margin-left: 6px !important;
    }
    .ts-wrapper.multi .ts-control .remove:hover {
        background: rgba(239, 68, 68, 0.05) !important;
        color: #b91c1c !important;
    }
    /* Dragging Active State styling rules */
    .ts-control .ts-dragging {
        opacity: 0.4 !important;
        background: #cbd5e1 !important;
    }

    input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
    input[type="color"]::-webkit-color-swatch { border: none; border-radius: 4px; }
</style>
@endpush

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">✏️ Edit Chart: {{ $chart->chart_name }}</h1>
            <p class="text-sm text-gray-500">Indicator: <b>{{ $indicator->name }}</b> | Code: <code>{{ $indicator->indicator_code }}</code></p>
        </div>
        <a href="{{ route('admin.charts.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-xl">
            ← Back to List
        </a>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 max-w-5xl">
        <form action="{{ route('admin.charts.update', $chart->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Grid Structure updated to support the new Source text field cleanly -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-6">
                <!-- Chart Name -->
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">Chart Name <span class="text-red-500">*</span></label>
                    <input type="text" name="chart_name" value="{{ old('chart_name', $chart->chart_name) }}" required
                           class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                </div>

                <!-- ADDED FIELD: Data Source Text Field -->
                <div class="md:col-span-4">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">Data Source Text</label>
                    <input type="text" name="source" value="{{ old('source', $chart->source) }}" placeholder="e.g. NFHS-5, NITI Aayog"
                           class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                </div>

                <!-- Chart Type -->
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">Chart Type <span class="text-red-500">*</span></label>
                    <select id="chartTypeSelect" name="chart_type_id" required onchange="renderDynamicFields(this)"
                           class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                        <option value="">-- Choose Chart Type --</option>
                        @foreach($chartTypes as $type)
                            <option value="{{ $type->id }}" 
                                    {{ $chart->chart_type_id == $type->id ? 'selected' : '' }} 
                                    data-fields='@json($type->fields_definition)'>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Display Order -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">Order</label>
                    <input type="number" name="display_order" value="{{ old('display_order', $chart->display_order) }}" min="0"
                           class="w-full text-sm text-gray-700 p-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-green-500">
                </div>
            </div>

            <!-- Dynamic Form Fields -->
            <div id="dynamicFieldsContainer" class="space-y-4 mb-6"></div>

            <div class="flex justify-end border-t pt-4">
                <button type="submit" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-xl shadow-sm">
                    Update Chart Configuration
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<!-- Sortable.js required explicitly before TomSelect scripts load for drag_drop integration -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<!-- Tom Select JS Core + Plugins -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<script>
    // Safe standard rendering using raw curly brackets
    const bqFilterData = {!! json_encode($bqFilters['filter_data'] ?? []) !!};
    const bqFilterKeys = {!! json_encode($bqFilters['keys'] ?? []) !!};
    const savedConfig  = {!! json_encode($chart->field_config ?? []) !!}; 
    const colorPalette = ['#36BAB9', '#0D5F7A', '#8CCDB5', '#F6BD16', '#E8684A', '#9254DE', '#FF9D4D', '#269A99'];
    
    const tomInstances = {};

    document.addEventListener('DOMContentLoaded', function() {
        const selectEl = document.getElementById('chartTypeSelect');
        if (selectEl && selectEl.value) {
            renderDynamicFields(selectEl);
        }
    });

    function renderDynamicFields(selectElement) {
        const container = document.getElementById('dynamicFieldsContainer');
        
        Object.keys(tomInstances).forEach(key => {
            if (tomInstances[key]) {
                tomInstances[key].destroy();
                delete tomInstances[key];
            }
        });
        
        container.innerHTML = '';

        const selectedOption = selectElement.options[selectElement.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        const fields = JSON.parse(selectedOption.getAttribute('data-fields') || '[]');

        fields.forEach(field => {
            const fieldWrapper = document.createElement('div');
            fieldWrapper.className = 'p-4 border border-gray-100 bg-gray-50 rounded-xl space-y-3 mb-4';

            let inputHtml = '';
            const key = field.key;
            const label = field.label_name;
            const type = field.type;
            const savedFieldData = savedConfig[key] || null;

            if (type === 'text') {
                const val = (savedFieldData && typeof savedFieldData === 'string') ? savedFieldData : (field.default_value || '');
                inputHtml = `<input type="text" name="config[${key}]" value="${val}" class="w-full text-sm p-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-green-500">`;
            } else if (type === 'select') {
                const options = field.options || [];
                const val = (savedFieldData && typeof savedFieldData === 'string') ? savedFieldData : (field.default_value || '');
                let optionsHtml = options.map(opt => `<option value="${opt}" ${opt === val ? 'selected' : ''}>${opt}</option>`).join('');
                inputHtml = `<select name="config[${key}]" class="w-full text-sm p-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-green-500">${optionsHtml}</select>`;
            } else if (type === 'radio') {
                const options = field.options || [];
                const val = (savedFieldData && typeof savedFieldData === 'string') ? savedFieldData : (field.default_value || '');
                inputHtml = options.map(opt => `
                    <label class="inline-flex items-center gap-1.5 mr-4 text-xs font-medium text-gray-700">
                        <input type="radio" name="config[${key}]" value="${opt}" ${opt === val ? 'checked' : ''}> ${opt}
                    </label>
                `).join('');
            } else if (type === 'checkbox') {
                const options = field.options || [];
                const savedValues = Array.isArray(savedFieldData) ? savedFieldData : [];
                inputHtml = options.map(opt => {
                    const isChecked = savedValues.includes(opt) ? 'checked' : '';
                    return `
                        <label class="inline-flex items-center gap-1.5 mr-4 text-xs font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" name="config[${key}][]" value="${opt}" ${isChecked} class="rounded text-green-600 focus:ring-green-500">
                            ${opt}
                        </label>
                    `;
                }).join('');
            }           
         // Script ke andar jahan else if (type === 'indicator') shuru hota hai, wahan se lekar
            // fieldWrapper.innerHTML wale pehle tak ka hissa isse replace kijiye:
            else if (type === 'indicator') {
                // FORCE ATTRIBUTE FALLBACK FOR DATA MATCHING
                const savedIndicatorKey = savedFieldData ? (savedFieldData.indicator_key || '') : '';
                const savedDefaultFirst = savedFieldData ? (savedFieldData.default_first_value == '1' || savedFieldData.default_first_value === true) : false;
                const savedFilter       = savedFieldData ? (savedFieldData.filter == '1' || savedFieldData.filter === true) : false;
                const savedMultiple     = savedFieldData ? (savedFieldData.multiple_select == '1' || savedFieldData.multiple_select === true) : false;
                const savedHide         = savedFieldData ? (savedFieldData.hide == '1' || savedFieldData.hide === true) : false;

                let keyOptionsHtml = '<option value="">Select Option...</option>';
                bqFilterKeys.forEach(k => {
                    // Force lowercase clean check to prevent case mismatches
                    const selected = String(k).toLowerCase().trim() === String(savedIndicatorKey).toLowerCase().trim() ? 'selected' : '';
                    keyOptionsHtml += `<option value="${k}" ${selected}>${k.toUpperCase()}</option>`;
                });

                inputHtml = `
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="w-64">
                                <select name="config[${key}][indicator_key]" onchange="handleIndicatorKeyChange(this, '${key}')"
                                        class="w-full text-sm p-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-green-500">
                                    ${keyOptionsHtml}
                                </select>
                            </div>

                            <div class="flex-1 min-w-[300px]">
                                <select id="values_select_${key}" name="config[${key}][values][]" multiple autocomplete="off" class="w-full text-sm">
                                </select>
                            </div>

                            <div class="flex items-center gap-3 text-xs font-semibold sm:ml-auto">
                                <label class="inline-flex items-center gap-1 cursor-pointer text-teal-700">
                                    <input type="checkbox" name="config[${key}][default_first_value]" value="1" ${savedDefaultFirst ? 'checked' : ''} class="rounded text-teal-600 focus:ring-teal-500">
                                    Default First Value
                                </label>
                                <span class="text-gray-300">|</span>
                                <label class="inline-flex items-center gap-1 cursor-pointer text-blue-600">
                                    <input type="checkbox" name="config[${key}][filter]" value="1" ${savedFilter ? 'checked' : ''} class="rounded text-blue-600 focus:ring-blue-500">
                                    Filter
                                </label>
                                <span class="text-gray-300">|</span>
                                <label class="inline-flex items-center gap-1 cursor-pointer text-purple-600">
                                    <input type="checkbox" name="config[${key}][multiple_select]" value="1" ${savedMultiple ? 'checked' : ''} class="rounded text-purple-600 focus:ring-purple-500">
                                    Multiple
                                </label>
                                <span class="text-gray-300">|</span>

                                <!-- ADDED: Hide Checkbox -->
                                <label class="inline-flex items-center gap-1 cursor-pointer text-rose-600">
                                    <input type="checkbox" name="config[${key}][hide]" value="1" ${savedHide ? 'checked' : ''} class="rounded text-rose-600 focus:ring-rose-500">
                                    Hide
                                </label>
                            </div>
                        </div>

                        <div id="color_section_${key}" class="hidden border-t pt-3 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Filter Values & Colors:</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" onclick="generateMissingColors('${key}')" class="text-xs text-green-600 hover:text-green-800 font-medium">⚡ Auto-fill Colors</button>
                                    <span class="text-gray-300">|</span>
                                    <button type="button" onclick="clearAllColors('${key}')" class="text-xs text-red-500 hover:text-red-700 font-medium">Clear Colors</button>
                                </div>
                            </div>
                            <div id="colors_container_${key}" class="flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                `;
            }

            fieldWrapper.innerHTML = `
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">${label} <span class="text-gray-400 font-normal">(${type})</span></label>
                ${inputHtml}
            `;

            container.appendChild(fieldWrapper);

            if (type === 'indicator') {
                const selectKeyEl = fieldWrapper.querySelector(`select[name="config[${key}][indicator_key]"]`);
                if (selectKeyEl && selectKeyEl.value) {
                    handleIndicatorKeyChange(selectKeyEl, key, savedFieldData);
                }
            }
        });
    }

    function handleIndicatorKeyChange(selectEl, fieldKey, savedFieldData = null) {
        const selectedKey = selectEl.value;
        const selectElement = document.getElementById(`values_select_${fieldKey}`);
        const colorSection = document.getElementById(`color_section_${fieldKey}`);
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);

        if (tomInstances[fieldKey]) {
            tomInstances[fieldKey].destroy();
            delete tomInstances[fieldKey];
        }

        selectElement.innerHTML = '';
        colorsContainer.innerHTML = '';

        if (!selectedKey || !bqFilterData[selectedKey]) {
            colorSection.classList.add('hidden');
            return;
        }

        const allAvailableValues = bqFilterData[selectedKey];
        if (allAvailableValues.length === 0) {
            colorSection.classList.add('hidden');
            return;
        }

        colorSection.classList.remove('hidden');

        // Shifting to robust string cleaning map
        let activeSelectedValues = (savedFieldData && Array.isArray(savedFieldData.values)) 
                                    ? savedFieldData.values.map(v => String(v).trim()) 
                                    : allAvailableValues.map(v => String(v).trim());

        const savedColors = (savedFieldData && savedFieldData.colors && typeof savedFieldData.colors === 'object') 
                            ? savedFieldData.colors 
                            : {};

        // Normalizing incoming dynamic items logic
        const normalizedAvailable = allAvailableValues.map(v => String(v).trim());

        activeSelectedValues.forEach(val => {
            const matchIdx = normalizedAvailable.indexOf(val);
            if (matchIdx !== -1) {
                const originalVal = allAvailableValues[matchIdx];
                selectElement.appendChild(new Option(originalVal, originalVal, true, true));
            }
        });

        allAvailableValues.forEach(val => {
            const cleanVal = String(val).trim();
            if (!activeSelectedValues.includes(cleanVal)) {
                selectElement.appendChild(new Option(val, val, false, false));
            }
        });

        tomInstances[fieldKey] = new TomSelect(selectElement, {
            plugins: ['remove_button', 'drag_drop'],
            placeholder: 'Search and select values...',
            create: false,
            persist: false,
            onChange: function(values) {
                const itemsArray = Array.isArray(values) ? values : (values ? values.split(',') : []);
                syncColors(fieldKey, itemsArray, savedColors);
            }
        });

        const hasSavedColors = Object.keys(savedColors).length > 0;
        syncColorsWithSelectedValues(fieldKey, tomInstances[fieldKey].items, savedColors, hasSavedColors);
    }

    function syncColorsWithSelectedValues(fieldKey, currentItems, savedColors, hasSavedColors) {
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        if (!colorsContainer) return;

        colorsContainer.innerHTML = '';

        currentItems.forEach((val, index) => {
            let colorHex = '#FFFFFF';
            const cleanVal = String(val).trim();

            // Check direct hex or loose trimmed hex checks
            if (savedColors && savedColors[val]) {
                colorHex = savedColors[val];
            } else if (savedColors && savedColors[cleanVal]) {
                colorHex = savedColors[cleanVal];
            } else if (!hasSavedColors) {
                colorHex = colorPalette[index % colorPalette.length];
            }

            const card = document.createElement('div');
            card.className = 'color-card-item flex items-center gap-2 bg-white border border-gray-200 p-1.5 px-3 rounded-lg shadow-sm text-xs text-gray-700 mb-2';
            card.setAttribute('data-val', val);

            const shortVal = val.length > 25 ? val.substring(0, 22) + '...' : val;

            card.innerHTML = `
                <span class="font-medium truncate max-w-[150px]" title="${val}">${shortVal}</span>
                <input type="color" name="config[${fieldKey}][colors][${val}]" value="${colorHex}" 
                       class="w-6 h-6 border-0 p-0 cursor-pointer rounded bg-transparent">
                <input type="text" id="hex_${fieldKey}_${index}" value="${colorHex}" onchange="updateColorPicker(this, '${fieldKey}', \`${val}\`)"
                       class="w-16 text-[11px] p-0.5 text-center border border-gray-200 rounded uppercase font-mono">
            `;

            const picker = card.querySelector('input[type="color"]');
            const txt = card.querySelector('input[type="text"]');
            picker.addEventListener('input', function() {
                txt.value = this.value.toUpperCase();
            });

            colorsContainer.appendChild(card);
        });
    }

    function syncColors(fieldKey, currentItems, savedColors) {
        const currentUIColors = {};
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        if(colorsContainer) {
            colorsContainer.querySelectorAll('.color-card-item').forEach((card, idx) => {
                const val = card.getAttribute('data-val');
                const picker = card.querySelector('input[type="color"]');
                if (val && picker) {
                    currentUIColors[val] = picker.value;
                }
            });
        }
        
        const mergedColors = Object.assign({}, savedColors, currentUIColors);
        syncColorsWithSelectedValues(fieldKey, currentItems, mergedColors, true);
    }

    function updateColorPicker(textInput, fieldKey, val) {
        let hex = textInput.value.trim();
        if(!hex.startsWith('#')) hex = '#' + hex;
        if(/^#[0-9A-F]{6}$/i.test(hex)) {
            const card = textInput.closest('.color-card-item');
            const picker = card.querySelector('input[type="color"]');
            if(picker) picker.value = hex;
            textInput.value = hex.toUpperCase();
        }
    }

    function generateMissingColors(fieldKey) {
        if (!tomInstances[fieldKey]) return;
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        if (!colorsContainer) return;

        colorsContainer.querySelectorAll('.color-card-item').forEach((card, idx) => {
            const picker = card.querySelector('input[type="color"]');
            const txt = card.querySelector('input[type="text"]');
            const randomColor = colorPalette[idx % colorPalette.length];
            if (picker && txt) {
                picker.value = randomColor;
                txt.value = randomColor.toUpperCase();
            }
        });
    }

    function clearAllColors(fieldKey) {
        const colorsContainer = document.getElementById(`colors_container_${fieldKey}`);
        if (!colorsContainer) return;
        colorsContainer.querySelectorAll('.color-card-item').forEach(card => {
            const picker = card.querySelector('input[type="color"]');
            const txt = card.querySelector('input[type="text"]');
            if (picker && txt) {
                picker.value = '#FFFFFF';
                txt.value = '#FFFFFF';
            }
        });
    }
</script>
@endpush