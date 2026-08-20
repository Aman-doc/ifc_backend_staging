<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Text column 'name' ke prefix (255 chars) par Composite Unique Index
        DB::statement('ALTER TABLE sub_indicators ADD UNIQUE unique_indicator_sub_indicator (indicator_id, name(255))');
    }

    public function down(): void
    {
        Schema::table('sub_indicators', function (Blueprint $table) {
            $table->dropUnique('unique_indicator_sub_indicator');
        });
    }
};