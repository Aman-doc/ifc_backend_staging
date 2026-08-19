<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\State;
use App\Models\StateAlias;
use Illuminate\Support\Facades\DB;

class CleanDuplicateStates extends Command
{
    protected $signature = 'states:clean-duplicates';
    protected $description = 'Clean duplicate state names and map them to state_aliases';

    public function handle()
    {
        $this->info("Cleaning and Merging Duplicate States...");

        $mappings = [
            'Andaman and Nicobar Islands' => ['A & N Islands', 'Andaman & Nicobar Islands', 'A&N Islands'],
            'Dadra and Nagar Haveli'      => ['Dadra & Nagar Haveli', 'Dadra & Nagar Haveli and Daman & Diu'],
            'Daman and Diu'               => ['Daman & Diu'],
            'Jammu and Kashmir'           => ['Jammu & Kashmir', 'J&K'],
            'Andhra Pradesh'            => ['Andhra Pr', 'AP', 'A.P.'],
            'Odisha'                    => ['Orissa'],
            'Puducherry'                => ['Pondicherry'],
        ];

        DB::transaction(function () use ($mappings) {
            foreach ($mappings as $masterName => $variants) {
                // 1. Get or Create Master State
                $masterState = State::firstOrCreate(['name' => $masterName]);

                foreach ($variants as $variant) {
                    // Check if variant exists as a main state entry in 'states' table
                    $duplicateState = State::where('name', $variant)
                        ->where('id', '!=', $masterState->id)
                        ->first();

                    if ($duplicateState) {
                        // Delete duplicate entry from states table
                        $duplicateState->delete();
                    }

                    // Save or Update Alias safely by matching ONLY 'raw_name' (prevents Unique Constraint Error)
                    StateAlias::updateOrCreate(
                        ['raw_name' => $variant],
                        ['state_id' => $masterState->id]
                    );
                }

                // Master Name ko bhi safely updateOrCreate karein
                StateAlias::updateOrCreate(
                    ['raw_name' => $masterName],
                    ['state_id' => $masterState->id]
                );
            }
        });

        $this->info("States cleaned and Aliases created successfully!");
    }
}