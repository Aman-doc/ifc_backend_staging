<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained('indicators')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_indicators');
    }
};