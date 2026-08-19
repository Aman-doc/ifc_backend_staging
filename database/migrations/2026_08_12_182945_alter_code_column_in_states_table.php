<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
        {
           // 1. Live server par agar koi NULL ya khali code hai, toh pehle use safe string de dete hain
                DB::table('states')
                    ->whereNull('code')
                    ->orWhere('code', '')
                    ->update(['code' => DB::raw('UPPER(LEFT(REPLACE(name, " ", "_"), 10))')]);

                // 2. Ab column modify karein (nullable rakhna sabse safe option hai live migration ke liye)
                Schema::table('states', function (Blueprint $table) {
                    $table->string('code', 100)->nullable()->change(); 
                });
        }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('states', function (Blueprint $table) {
            //
        });
    }
};
