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
        Schema::create('spare_part_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_part_id')->constrained('spare_parts')->onDelete('cascade');
            $table->foreignId('maintenance_log_id')->nullable()->constrained('maintenance_logs')->onDelete('set null');
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->foreignId('used_by_user_id')->constrained('users')->onDelete('cascade');
            $table->integer('quantity_used');
            $table->decimal('cost_at_use', 10, 2)->nullable()->comment('Unit cost at time of usage');
            $table->timestamp('used_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['spare_part_id', 'used_at']);
            $table->index(['machine_id', 'used_at']);
            $table->index('maintenance_log_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spare_part_usages');
    }
};
