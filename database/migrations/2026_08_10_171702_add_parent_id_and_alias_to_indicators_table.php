<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            // Self-referencing parent_id add karein virtual indicators ke liye
            $table->foreignId('parent_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('indicators')
                  ->onDelete('cascade');

            // Alias add karein custom section name display karne ke liye
            $table->string('alias')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'alias']);
        });
    }
};