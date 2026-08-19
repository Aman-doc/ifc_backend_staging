<?php

namespace App\Services;

use App\Models\State;
use App\Models\StateAlias;

class StateResolverService
{
    // Memory Cache
    protected static array $cache = [];
    protected static bool $isLoaded = false;

    /**
     * Boot time par saare aliases aur states memory me preload kar leta hai.
     */
    protected static function bootCache(): void
    {
        if (self::$isLoaded) {
            return;
        }

        // 1. Direct State Names Load Karein
        $states = State::select('id', 'name')->get();
        foreach ($states as $state) {
            $cleanName = strtolower(trim($state->name));
            self::$cache[$cleanName] = $state->id;
        }

        // 2. All State Aliases Load Karein
        $aliases = StateAlias::select('state_id', 'raw_name')->get();
        foreach ($aliases as $alias) {
            $cleanAlias = strtolower(trim($alias->raw_name));
            self::$cache[$cleanAlias] = $alias->state_id;
        }

        self::$isLoaded = true;
    }

    /**
     * Raw state name ko master state_id me resolve karta hai.
     */
    public static function getOrCreateStateId(string $rawStateName): int
    {
        $cleanName = trim($rawStateName);
        $keyName   = strtolower($cleanName);

        if (empty($cleanName)) {
            return 0; // Default or Unassigned State ID
        }

        // Preload All Mappings (Runs DB query only ONCE during whole execution)
        self::bootCache();

        // 1. Check in Memory Cache (Fastest Path - 0ms execution time)
        if (isset(self::$cache[$keyName])) {
            return self::$cache[$keyName];
        }

        // 2. Exact match in States table (Fallback if created mid-process)
        $state = State::whereRaw('LOWER(name) = ?', [$keyName])->first();

        if (!$state) {
            // Agar bilkul naya state name hai
            $state = State::create(['name' => $cleanName]);
        }

        // 3. Naya Alias Save karein future ke liye
        StateAlias::firstOrCreate([
            'state_id' => $state->id,
            'raw_name' => $cleanName,
        ]);

        // 4. Update Memory Cache for current execution run
        self::$cache[$keyName] = $state->id;

        return $state->id;
    }
}