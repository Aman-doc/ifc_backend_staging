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
        Schema::table('states', function (Blueprint $table) {
            // code column ke baad status add hoga, default 1 (Active) rahega
            $table->tinyInteger('status')->default(1)->after('code')->comment('1 = Active, 0 = Inactive');
        });
    }

    public function down()
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    
};
