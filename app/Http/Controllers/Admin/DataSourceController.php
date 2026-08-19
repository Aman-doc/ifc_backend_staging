<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{ 
    public function index()
    {
        $dataSources = DataSource::orderBy('title', 'asc')->paginate(10);
        return view('admin.data_sources.index', compact('dataSources'));
    }
    
    public function store(Request $request)
    {
        // Unique rule verification dynamic structure mapping ke hisab se setup ki hai
        $request->validate([
            'dataset_id'  => 'required|string|max:255|unique:data_sources,dataset_id,' . $request->id,
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Model create ya existing id update logic mapping
        DataSource::updateOrCreate(
            ['id' => $request->id],
            [
                'dataset_id'  => $request->dataset_id,
                'title'       => $request->title,
                'description' => $request->description,
            ]
        );

        $message = $request->id ? 'Data Source updated successfully!' : 'Data Source created successfully!';
        return redirect()->back()->with('success', $message);
    }

    
    public function destroy($id)
    {
        $dataSource = DataSource::findOrFail($id);
        $dataSource->delete();

        return redirect()->back()->with('success', 'Data Source deleted successfully!');
    }
}