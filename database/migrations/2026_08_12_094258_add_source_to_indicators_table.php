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
        Schema::table('indicators', function (Blueprint $table) {
            // text field hoga jisme JSON data store hoga
            $table->json('source')->nullable()->after('alias'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            //
        });
    }
};
