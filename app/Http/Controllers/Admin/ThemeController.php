<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\DataSource;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index()
    {
        $themes = Theme::latest()->paginate(10);
        return view('admin.themes.index', compact('themes'));
    }

    public function create()
    {
        $dataSources = DataSource::with('indicators')->get();
        return view('admin.themes.create', compact('dataSources'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'data_source_ids'   => 'required|array|min:1',
            'data_source_ids.*' => 'exists:data_sources,id',
            'orders'            => 'nullable|array',
            'indicators'        => 'nullable|array',
        ]);

        $selectedDsIds = $request->input('data_source_ids', []);
        $orders = $request->input('orders', []);

        // Selected Data Sources ko Sequence / Order ke mutabiq Sort karna
        usort($selectedDsIds, function ($a, $b) use ($orders) {
            $orderA = isset($orders[$a]) ? (int)$orders[$a] : 999;
            $orderB = isset($orders[$b]) ? (int)$orders[$b] : 999;
            return $orderA <=> $orderB;
        });

        // Formatted Data Source IDs String Array
        $orderedDsIds = array_values(array_map('strval', $selectedDsIds));

        // Nested Indicators Mapping Structure maintain karna
        $formattedIndicators = [];

        if ($request->has('indicators')) {
            foreach ($orderedDsIds as $dsId) {
                if (isset($request->indicators[$dsId]) && is_array($request->indicators[$dsId])) {
                    $formattedIndicators[$dsId] = array_values(array_map('strval', $request->indicators[$dsId]));
                }
            }
        }

        // dd($orderedDsIds);
        Theme::create([
            'name'            => $request->name,
            'description'     => $request->description,
            'data_source_ids' => $orderedDsIds, // Custom Order ke sath JSON save hoga
            'indicator_ids'   => $formattedIndicators,
            'created_by'      => auth()->id() ?? null,
        ]);

        return redirect()->route('admin.themes.index')->with('success', 'Theme created successfully.');
    }

    public function edit($id)
    {
        $theme = Theme::findOrFail($id);
        $dataSources = DataSource::with('indicators')->get();
        return view('admin.themes.edit', compact('theme', 'dataSources'));
    }

    public function update(Request $request, $id)
    {
        $theme = Theme::findOrFail($id);

        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'data_source_ids'   => 'required|array|min:1',
            'data_source_ids.*' => 'exists:data_sources,id',
            'orders'            => 'nullable|array',
            'indicators'        => 'nullable|array',
        ]);

        $selectedDsIds = $request->input('data_source_ids', []);
        $orders = $request->input('orders', []);

        // Order input values ke basis par numeric sorting
        usort($selectedDsIds, function ($a, $b) use ($orders) {
            $valA = (isset($orders[$a]) && $orders[$a] !== null && $orders[$a] !== '') ? (int)$orders[$a] : 9999;
            $valB = (isset($orders[$b]) && $orders[$b] !== null && $orders[$b] !== '') ? (int)$orders[$b] : 9999;
            return $valA <=> $valB;
        });

        // Fresh sequential re-indexing
        $orderedDsIds = array_values(array_map('strval', $selectedDsIds));

        $formattedIndicators = [];
        if ($request->has('indicators')) {
            foreach ($orderedDsIds as $dsId) {
                if (isset($request->indicators[$dsId]) && is_array($request->indicators[$dsId])) {
                    $formattedIndicators[(string)$dsId] = array_values(array_map('strval', $request->indicators[$dsId]));
                }
            }
        }

        // Explicit update call
        $theme->fill([
            'name'            => $request->name,
            'description'     => $request->description,
            'data_source_ids' => $orderedDsIds,
            'indicator_ids'   => $formattedIndicators,
        ]);

        $theme->save();

        return redirect()->route('admin.themes.index')->with('success', 'Theme updated successfully.');
    }

    public function destroy($id)
    {
        $theme = Theme::findOrFail($id);
        $theme->delete();

        return redirect()->route('admin.themes.index')->with('success', 'Theme deleted successfully.');
    }
}