<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            
            // Foreign Keys
            $table->foreignId('data_source_id')->constrained('data_sources')->onDelete('cascade');
            $table->foreignId('theme_id')->nullable()->constrained('themes')->onDelete('set null');
            
            // Indicator Identifiers
            $table->string('indicator_code')->nullable(); // Unique code e.g. 'UR', 'LFPR'
            $table->text('name');                        // Indicator Name
            $table->boolean('is_synced')->default(false);
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // Indexing for performance
            $table->index(['data_source_id', 'theme_id']);
            $table->index(['data_source_id', 'indicator_code']);
            $table->index('is_synced');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};