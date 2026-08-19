<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->onDelete('cascade');
            $table->foreignId('chart_type_id')->constrained()->onDelete('cascade');
            $table->string('chart_name');
            $table->integer('display_order')->default(0); // Sequence / Order set karne ke liye
            $table->json('field_config'); // Mapped key, values, filters & color mapping
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charts');
    }
};