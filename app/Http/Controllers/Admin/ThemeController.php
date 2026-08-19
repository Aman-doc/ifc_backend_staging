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
            'description'              => 'nullable|string',
            'data_source_ids'   => 'required|array|min:1',
            'data_source_ids.*' => 'exists:data_sources,id',
            'indicators'        => 'nullable|array',
        ]);

        // Nested JSON Mapping Structure: {"data_source_id": ["indicator_id_1", "indicator_id_2"]}
        $formattedIndicators = [];

        if ($request->has('indicators') && $request->has('data_source_ids')) {
            foreach ($request->data_source_ids as $dsId) {
                if (isset($request->indicators[$dsId]) && is_array($request->indicators[$dsId])) {
                    $formattedIndicators[(string)$dsId] = array_values(array_map('strval', $request->indicators[$dsId]));
                }
            }
        }

        Theme::create([
            'name'            => $request->name,
            'description'     => $request->description,
            'data_source_ids' => array_values(array_map('strval', $request->data_source_ids)),
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
            'description'              => 'nullable|string',
            'data_source_ids'   => 'required|array|min:1',
            'data_source_ids.*' => 'exists:data_sources,id',
            'indicators'        => 'nullable|array',
        ]);

        $formattedIndicators = [];

        if ($request->has('indicators') && $request->has('data_source_ids')) {
            foreach ($request->data_source_ids as $dsId) {
                if (isset($request->indicators[$dsId]) && is_array($request->indicators[$dsId])) {
                    $formattedIndicators[(string)$dsId] = array_values(array_map('strval', $request->indicators[$dsId]));
                }
            }
        }

        $theme->update([
            'name'            => $request->name,
            'description'     => $request->description,
            'data_source_ids' => array_values(array_map('strval', $request->data_source_ids)),
            'indicator_ids'   => $formattedIndicators,
        ]);

        return redirect()->route('admin.themes.index')->with('success', 'Theme updated successfully.');
    }

    public function destroy($id)
    {
        $theme = Theme::findOrFail($id);
        $theme->delete();

        return redirect()->route('admin.themes.index')->with('success', 'Theme deleted successfully.');
    }
}