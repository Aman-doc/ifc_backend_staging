<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->onDelete('cascade');
            $table->string('raw_name', 150)->unique(); // Sheet name mapping ke liye fast unique index
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_aliases');
    }
};