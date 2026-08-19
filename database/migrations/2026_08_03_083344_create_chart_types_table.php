<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., Grouped Bar, Pie Chart
            $table->string('slug')->unique(); // e.g., grouped_bar, pie_chart
            $table->json('fields_definition'); // Dynamic fields mapping array
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_types');
    }
};