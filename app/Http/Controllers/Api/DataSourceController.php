<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{
    // Fetch all data sources
    public function index()
    {
        $sources = DataSource::select('id', 'name')->latest()->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data Sources fetched successfully',
            'data'    => $sources
        ], 200);
    }

    // Create new data source
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:data_sources,name',
        ]);

        $source = DataSource::create([
            'name' => trim($request->name),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Data Source created successfully',
            'data'    => $source
        ], 201);
    }

    // Show single data source
    public function show($id)
    {
        $source = DataSource::find($id);

        if (!$source) {
            return response()->json([
                'status'  => false,
                'message' => 'Data Source not found'
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $source
        ], 200);
    }

    // Update data source
    public function update(Request $request, $id)
    {
        $source = DataSource::find($id);

        if (!$source) {
            return response()->json([
                'status'  => false,
                'message' => 'Data Source not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:data_sources,name,' . $id,
        ]);

        $source->update(['name' => trim($request->name)]);

        return response()->json([
            'status'  => true,
            'message' => 'Data Source updated successfully',
            'data'    => $source
        ], 200);
    }

    // Delete data source
    public function destroy($id)
    {
        $source = DataSource::find($id);

        if (!$source) {
            return response()->json([
                'status'  => false,
                'message' => 'Data Source not found'
            ], 404);
        }

        $source->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Data Source deleted successfully'
        ], 200);
    }
}