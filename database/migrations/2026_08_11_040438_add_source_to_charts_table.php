<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
    {
        Schema::table('charts', function (Blueprint $table) {
            // null allowed rakha hai taaki purane records crash na ho
            $table->string('source')->nullable()->after('chart_name'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            //
        });
    }
};
