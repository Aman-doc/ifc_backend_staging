<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\DataSource;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index()
    {
        $sources = Source::latest()->paginate(10);
        return view('admin.sources.index', compact('sources'));
    }

    public function create()
    {
        $dataSources = DataSource::with('indicators')->get();
        return view('admin.sources.create', compact('dataSources'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'             => 'required|string|max:255|unique:sources,title',
            'description'       => 'nullable|string',
            'data_source_ids'   => 'required|array|min:1',
            'data_source_ids.*' => 'exists:data_sources,id',
            'indicators'        => 'nullable|array',
        ]);

        // Key-Value Mapping Format: {"data_source_id": ["indicator_id_1", "indicator_id_2"]}
        $formattedIndicators = [];

        if ($request->has('indicators') && $request->has('data_source_ids')) {
            foreach ($request->data_source_ids as $dsId) {
                if (isset($request->indicators[$dsId]) && is_array($request->indicators[$dsId])) {
                    $formattedIndicators[(string)$dsId] = array_values(array_map('strval', $request->indicators[$dsId]));
                }
            }
        }

        Source::create([
            'title'           => $request->title,
            'description'     => $request->description,
            'data_source_ids' => array_values(array_map('strval', $request->data_source_ids)),
            'indicator_ids'   => $formattedIndicators,
        ]);

        return redirect()->route('admin.sources.index')->with('success', 'Source created successfully!');
    }

    public function edit($id)
    {
        $source = Source::findOrFail($id);
        $dataSources = DataSource::with('indicators')->get();
        return view('admin.sources.edit', compact('source', 'dataSources'));
    }

    public function update(Request $request, $id)
    {
        $source = Source::findOrFail($id);

        $request->validate([
            'title'             => 'required|string|max:255|unique:sources,title,' . $id,
            'description'       => 'nullable|string',
            'data_source_ids'   => 'required|array|min:1',
            'data_source_ids.*' => 'exists:data_sources,id',
            'indicators'        => 'nullable|array',
        ]);

        // Key-Value Mapping Format
        $formattedIndicators = [];

        if ($request->has('indicators') && $request->has('data_source_ids')) {
            foreach ($request->data_source_ids as $dsId) {
                if (isset($request->indicators[$dsId]) && is_array($request->indicators[$dsId])) {
                    $formattedIndicators[(string)$dsId] = array_values(array_map('strval', $request->indicators[$dsId]));
                }
            }
        }

        $source->update([
            'title'           => $request->title,
            'description'     => $request->description,
            'data_source_ids' => array_values(array_map('strval', $request->data_source_ids)),
            'indicator_ids'   => $formattedIndicators,
        ]);

        return redirect()->route('admin.sources.index')->with('success', 'Source updated successfully!');
    }

    public function destroy($id)
    {
        $source = Source::findOrFail($id);
        $source->delete();

        return redirect()->route('admin.sources.index')->with('success', 'Source deleted successfully!');
    }
}