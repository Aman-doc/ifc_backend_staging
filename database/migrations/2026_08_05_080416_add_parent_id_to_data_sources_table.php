<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // Foreign Key column with exact name parent_datasource_id
            $table->unsignedBigInteger('parent_datasource_id')->nullable()->after('id');

            $table->foreign('parent_datasource_id')
                  ->references('id')
                  ->on('data_sources')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropForeign(['parent_datasource_id']);
            $table->dropColumn('parent_datasource_id');
        });
    }
};