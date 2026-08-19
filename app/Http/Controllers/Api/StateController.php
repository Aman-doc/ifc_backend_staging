<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\Request;

class StateController extends Controller
{
    // Fetch all states with their aliases (Alphabetical order)
    public function index()
    {
        // dd("sdsadsa");
        $states = State::select('id', 'name', 'code')
            ->where('status', 1)
            ->with(['aliases' => function ($query) {
                $query->select('id', 'state_id', 'raw_name');
            }])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($states);
    }

    // Create new state
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:states,name',
            'code' => 'nullable|string|max:10',
        ]);

        $state = State::create([
            'name' => trim($request->name),
            'code' => $request->code ? trim($request->code) : null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'State created successfully',
            'data'    => $state
        ], 201);
    }

    // Show single state with aliases
    public function show($id)
    {
        $state = State::with('aliases:id,state_id,raw_name')->find($id);

        if (!$state) {
            return response()->json([
                'status'  => false,
                'message' => 'State not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $state
        ], 200);
    }

    // Update state
    public function update(Request $request, $id)
    {
        $state = State::find($id);

        if (!$state) {
            return response()->json([
                'status'  => false,
                'message' => 'State not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:states,name,' . $id,
            'code' => 'nullable|string|max:10',
        ]);

        $state->update([
            'name' => trim($request->name),
            'code' => $request->has('code') ? trim($request->code) : $state->code,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'State updated successfully',
            'data'    => $state->load('aliases:id,state_id,raw_name')
        ], 200);
    }

    // Delete state
    public function destroy($id)
    {
        $state = State::find($id);

        if (!$state) {
            return response()->json([
                'status'  => false,
                'message' => 'State not found'
            ], 404);
        }

        $state->delete();

        return response()->json([
            'status'  => true,
            'message' => 'State deleted successfully'
        ], 200);
    }
}