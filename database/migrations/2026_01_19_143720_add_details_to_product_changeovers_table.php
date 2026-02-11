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
        Schema::table('product_changeovers', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('to_product_id');
            $table->unsignedInteger('good_count')->default(0)->after('batch_number');
            $table->unsignedInteger('reject_count')->default(0)->after('good_count');
            $table->decimal('material_loss_units', 10, 2)->default(0)->after('reject_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_changeovers', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'good_count', 'reject_count', 'material_loss_units']);
        });
    }
};
