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
        Schema::table('machines', function (Blueprint $table) {
            $table->renameColumn('default_ideal_cycle_time', 'default_ideal_rate');
        });

        Schema::table('machine_product_configs', function (Blueprint $table) {
            $table->renameColumn('ideal_cycle_time', 'ideal_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machine_product_configs', function (Blueprint $table) {
            $table->renameColumn('ideal_rate', 'ideal_cycle_time');
        });

        Schema::table('machines', function (Blueprint $table) {
            $table->renameColumn('default_ideal_rate', 'default_ideal_cycle_time');
        });
    }
};
