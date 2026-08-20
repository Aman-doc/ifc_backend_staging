<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubIndicator;
use Illuminate\Http\Request;

class SubindicatorController extends Controller
{
    public function index(Request $request)
    {
        $query = SubIndicator::with('indicator');

        // Search Filter (by Name or Alias)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('alias_name', 'LIKE', "%{$search}%");
            });
        }

        $subIndicators = $query->latest()->paginate(15);

        return view('admin.sub-indicators.index', compact('subIndicators'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'alias_name' => 'nullable|string|max:255',
        ]);

        $subIndicator = SubIndicator::findOrFail($id);
        $subIndicator->update([
            'alias_name' => $request->alias_name,
        ]);

        return redirect()->back()->with('success', 'Alias Name updated successfully!');
    }
}