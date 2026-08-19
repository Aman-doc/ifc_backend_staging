<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\State;
use App\Models\StateAlias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Added DB Facade Import
use App\Services\BigQueryService;
use Illuminate\Support\Facades\Log;

class StateController extends Controller
{
    //    Merge state

       public function mergeStates(Request $request, BigQueryService $bigQueryService)
    {
        // 1. Validation
        $request->validate([
            'master_state_id'       => 'required|exists:states,id',
            'duplicate_state_ids'   => 'required|array',
            'duplicate_state_ids.*' => 'exists:states,id|different:master_state_id',
        ]);

        $masterId     = (int) $request->master_state_id;
        $duplicateIds = array_map('intval', $request->duplicate_state_ids);

        // Process start log
        Log::info('Merge States Process Started', [
            'master_state_id'     => $masterId,
            'duplicate_state_ids' => $duplicateIds,
            'user_id'             => auth()->id() ?? 'system'
        ]);

        try {
            // 2. Local Database Transaction
            DB::transaction(function () use ($masterId, $duplicateIds) {
                
                $duplicateStates = State::whereIn('id', $duplicateIds)->get();

                foreach ($duplicateStates as $dupState) {
                    // Duplicate State ka name as Alias save karna (agar pehle se na ho)
                    StateAlias::firstOrCreate(
                        ['raw_name' => trim($dupState->name)],
                        ['state_id' => $masterId]
                    );

                    // Duplicate State ke purane saare Aliases ko bhi Master State par shift karna
                    StateAlias::where('state_id', $dupState->id)
                        ->update(['state_id' => $masterId]);
                }

                // Merge ke baad duplicates ko hamesha permanently delete karein
                State::whereIn('id', $duplicateIds)->delete();
            });

            Log::info("Local database merge completed and duplicates deleted for Master State ID: {$masterId}");

            // 3. Cloud Sync: BigQuery table me data update karna
            $affectedRows = $bigQueryService->updateMergedStates($masterId, $duplicateIds);

            Log::info("BigQuery Sync Completed Successfully", [
                'master_state_id' => $masterId,
                'affected_rows'   => $affectedRows
            ]);

            return redirect()->back()->with('success', 'States successfully merged and synced with BigQuery!');

        } catch (\Exception $e) {
            Log::error('Merge States Process Failed', [
                'error_message' => $e->getMessage(),
                'master_id'     => $masterId,
                'duplicate_ids' => $duplicateIds,
                'file'          => $e->getFile(),
                'line'          => $e->getLine()
            ]);

            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    // Index Page: All States with Aliases
    public function index()
    {
        $states = State::with('aliases')->orderBy('name', 'asc')->get();
        return view('admin.states.index', compact('states'));
    }

    // Main State Create karna
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:states,name',
            'code' => 'nullable|string|max:10',
        ]);

        State::create([
            'name' => trim($request->name),
            'code' => $request->code ? strtoupper(trim($request->code)) : null,
        ]);

        return redirect()->back()->with('success', 'Main State created successfully!');
    }

    // Main State Update karna
    // Main State Update karna (or Hide/Unhide toggle)
        public function update(Request $request, State $state)
        {
            // 1. Agar request me 'status' ya 'is_hidden' bhej rahe ho
            if ($request->has('status')) {
                $state->update([
                    'status' => $request->status,
                ]);

                $message = $request->status == 0 ? 'State hidden successfully!' : 'State unhidden successfully!';
                return redirect()->back()->with('success', $message);
            }

            // 2. Agar table row direct click kar ke status flip/toggle karna ho
            if ($request->has('toggle_status')) {
                $newStatus = $state->status == 1 ? 0 : 1;
                $state->update(['status' => $newStatus]);

                return redirect()->back()->with('success', 'State visibility status updated!');
            }

            // 3. Normal State Name/Code Edit Update
            $request->validate([
                'name' => 'required|string|max:255|unique:states,name,' . $state->id,
                'code' => 'nullable|string|max:10',
            ]);

            $state->update([
                'name' => trim($request->name),
                'code' => $request->code ? strtoupper(trim($request->code)) : null,
            ]);

            return redirect()->back()->with('success', 'State updated successfully!');
        }

    // Main State Delete karna
    public function destroy(State $state)
    {
        $state->delete(); // Cascading delete aliases automatically if set in migration
        return redirect()->back()->with('success', 'State deleted successfully!');
    }

    public function toggleStatus(State $state)
    {
        // Status Flip (1 hai to 0, 0 hai to 1)
        $newStatus = ($state->status ?? 1) == 1 ? 0 : 1;
        
        $state->update([
            'status' => $newStatus
        ]);

        $statusLabel = $newStatus == 0 ? 'Hidden' : 'Visible';
        return redirect()->back()->with('success', "State is now {$statusLabel}!");
    }

    // // Alias Add karna
    // public function storeAlias(Request $request, State $state)
    // {
    //     $request->validate([
    //         'raw_name' => 'required|string|max:255|unique:state_aliases,raw_name',
    //     ]);

    //     $state->aliases()->create([
    //         'raw_name' => trim($request->raw_name),
    //     ]);

    //     return redirect()->back()->with('success', 'Alias added successfully!');
    // }

    // // Alias Delete karna
    // public function destroyAlias(StateAlias $alias)
    // {
    //     $alias->delete();
    //     return redirect()->back()->with('success', 'Alias deleted successfully!');
    // }

    
}