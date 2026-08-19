<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChartTypeController extends Controller
{
    public function index()
    {
        $chartTypes = ChartType::latest()->get();
        return view('admin.chart_type.index', compact('chartTypes'));
    }

   public function update(Request $request, $id)
    {
        $chartType = ChartType::findOrFail($id);

        $request->validate([
            'fields'                 => 'nullable|array',
            'fields.*.label'         => 'required_with:fields|string|max:255',
            'fields.*.type'          => 'required_with:fields|in:text,select,radio,checkbox,indicator',
        ]);

        $fieldsDefinition = [];
        $usedKeys = []; // जनरेटेड कीज (keys) को ट्रैक करने के लिए

        if ($request->has('fields') && is_array($request->fields)) {
            foreach ($request->fields as $field) {
                $options = [];
                if (in_array($field['type'], ['select', 'radio', 'checkbox']) && !empty($field['options'])) {
                    $options = array_map('trim', explode(',', $field['options']));
                }

                // 1. बेस स्लग जनरेट करें
                $baseKey = Str::slug($field['label'], '_');
                $finalKey = $baseKey;

                // 2. अगर की (key) पहले से मौजूद है, तो यूनिक काउंटर लगाएं (जैसे: label, label_1, label_2)
                if (isset($usedKeys[$baseKey])) {
                    $usedKeys[$baseKey]++;
                    $finalKey = $baseKey . '_' . $usedKeys[$baseKey];
                } else {
                    $usedKeys[$baseKey] = 0; // पहली बार दिखने पर 0 इंडेक्स सेट करें
                }

                $fieldsDefinition[] = [
                    'key'           => $finalKey, // अब यह हमेशा यूनिक रहेगा
                    'label_name'    => $field['label'],
                    'type'          => $field['type'],
                    'default_value' => $field['type'] === 'indicator' ? null : ($field['default_value'] ?? null),
                    'options'       => $options,
                ];
            }
        }

        $chartType->update([
            'fields_definition' => $fieldsDefinition,
        ]);

        return redirect()->back()->with('success', 'Chart Type fields updated successfully!');
    }

    
}