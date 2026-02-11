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
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_schedule_id')->nullable()->constrained('maintenance_schedules')->onDelete('set null');
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->foreignId('performed_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('performed_at');
            $table->string('task_description');
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->json('parts_replaced')->nullable()->comment('JSON array of replaced parts');
            $table->decimal('cost', 10, 2)->nullable();
            $table->timestamp('next_scheduled_at')->nullable();
            $table->timestamps();
            
            $table->index(['machine_id', 'performed_at']);
            $table->index('performed_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
