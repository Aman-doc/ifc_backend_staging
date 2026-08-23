<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\DataSource;
use App\Models\Indicator;
use Illuminate\Http\Request;

class IndicatorController extends Controller
{
    public function index(Request $request)
{
    // 1. Saare data sources dropdown ke liye load karein
    $dataSources = DataSource::orderBy('title')->get();
    
    // 2. Active filter fetch karein request se
    $selectedSourceId = $request->input('source_filter');

    // 3. Query base initialization
    $query = Indicator::with(['dataSource', 'parent']);

    // 4. Strict Dynamic Filtering: Agar user ne koi data source select kiya hai
    if (!empty($selectedSourceId)) {
        $query->where('data_source_id', $selectedSourceId);
        
        // Form mein sirf isi selected source ke main indicators dikhane ke liye (For Parent Dropdown)
        $parentIndicators = Indicator::where('data_source_id', $selectedSourceId)
                                     ->whereNull('parent_id')
                                     ->orderBy('name')
                                     ->get();
    } else {
        // Agar koi filter select nahi hai, toh safe side ke liye saare master records ya empty rakhe
        $parentIndicators = Indicator::whereNull('parent_id')->orderBy('name')->get();
    }

    // 5. Paginate with Query String persistence ताकि next page par filter na tute
    $indicators = $query->orderBy('id', 'desc')->paginate(15)->appends(['source_filter' => $selectedSourceId]);

    return view('admin.indicators.index', compact('indicators', 'dataSources', 'parentIndicators', 'selectedSourceId'));
}

   public function store(Request $request)
{
    $request->validate([
        'name'            => 'required|string|max:255',
        'data_source_id'  => 'required|exists:data_sources,id',
        'parent_id'       => 'nullable|exists:indicators,id',
        'alias'            => 'nullable|string',
        'theme_id'        => 'nullable|exists:themes,id',
        'indicator_code'  => 'nullable|string|max:100', 
    ]);

    Indicator::create([
        'name'             => $request->name,
        'data_source_id'   => $request->data_source_id,
        'parent_id'        => $request->parent_id,
        'alias'            => $request->alias,
        'theme_id'         => $request->theme_id,
        // Agar parent select kiya hai toh null rakhenge (kyunki parent code se chalega), 
        // nahi toh agar master hai toh khud ka code save hoga
        'indicator_code'   => $request->parent_id ? null : $request->indicator_code,
        'is_synced'        => false,
    ]);

    return redirect()->back()->with('success', 'Custom Indicator mapped successfully!');
}

public function update(Request $request, $id)
{
    // dd($request->all());
    $indicator = Indicator::findOrFail($id);

    $request->validate([
        'name'            => 'required|string|max:255',
        'data_source_id'  => 'required|exists:data_sources,id',
        'parent_id'       => 'nullable|exists:indicators,id',
        'alias'           => 'nullable|string',
        'theme_id'        => 'nullable|exists:themes,id',
        'indicator_code'  => 'nullable|string|max:100',
    ]);

    $indicator->update([
        'name'            => $request->name,
        'data_source_id'  => $request->data_source_id,
        'parent_id'       => $request->parent_id,
        'alias'           => $request->alias,
        'theme_id'        => $request->theme_id,
        'indicator_code'  => $request->parent_id ? null : $request->indicator_code,
    ]);

    return redirect()->route('admin.indicators.index')->with('success', 'Indicator updated with fallback routing!');
}

   public function edit($id)
    {
        $indicator = Indicator::findOrFail($id);
        $dataSources = DataSource::orderBy('title')->get();
        $themes = Theme::orderBy('name')->get();
        
        // Check if this indicator is already acting as a parent to other child charts
        $hasChildren = Indicator::where('parent_id', $id)->exists();

        if ($hasChildren) {
            // If it is already a parent, it cannot become a child of another section
            $parentIndicators = collect();
        } else {
            // Otherwise, it can select any master section except itself
            $parentIndicators = Indicator::whereNull('parent_id')
                                        ->where('id', '!=', $id)
                                        ->orderBy('name')
                                        ->get();
        }

        return view('admin.indicators.edit', compact('indicator', 'dataSources', 'themes', 'parentIndicators', 'hasChildren'));
    }

   public function destroy($id)
    {
        $indicator = Indicator::findOrFail($id);

        // If this is a parent indicator, fetch and delete all its child indicators/charts first
        if ($indicator->parent_id === null) {
            // Option A: Delete child records automatically
            Indicator::where('parent_id', $indicator->id)->delete();
        }

        $indicator->delete();

        return redirect()->back()->with('success', 'Indicator and its associated child chart mappings deleted successfully!');
    }


    public function updateSource(Request $request, Indicator $indicator)
    {
        $request->validate([
            'theme_id' => 'required|integer',
            'source_text' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0'
        ]);

        $themeId = $request->theme_id;

        // Existing JSON array fetch karein
        $currentData = $indicator->source ?? []; 

        if (!is_array($currentData)) {
            $currentData = json_decode($currentData, true) ?? [];
        }

        // Purane data ko preserve karte hue update karein
        $existingThemeData = $currentData[$themeId] ?? [];

        // Agar purana data plain string tha (backward compatibility for old saved values)
        if (is_string($existingThemeData)) {
            $existingThemeData = ['text' => $existingThemeData, 'order' => 0];
        }

        // New values update
        $currentData[$themeId] = [
            'text' => $request->filled('source_text') ? $request->source_text : ($existingThemeData['text'] ?? ''),
            'order' => $request->filled('display_order') ? (int) $request->display_order : ($existingThemeData['order'] ?? 0),
        ];

        // Model save
        $indicator->source = $currentData;
        $indicator->save();

        return redirect()->back()->with('success', 'Theme Source & Order saved successfully!');
    }
   
   
   
   
}