<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_indicators', function (Blueprint $table) {
            // Existing 'name' column ko TEXT me convert karna agar naam lamba ho
            $table->text('name')->change();
            
            // Admin ke customized/alias name ke liye nullable column
            $table->text('alias_name')->nullable()->after('name');
            
            // Metadata filters (sector, survey) tracking ke liye
            $table->string('sector', 150)->nullable()->after('alias_name');
            $table->string('survey', 100)->nullable()->after('sector');
        });
    }

    public function down(): void
    {
        Schema::table('sub_indicators', function (Blueprint $table) {
            $table->dropColumn(['alias_name', 'sector', 'survey']);
            $table->string('name', 255)->change();
        });
    }
};