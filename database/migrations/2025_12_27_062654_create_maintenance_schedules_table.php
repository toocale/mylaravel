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
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->enum('maintenance_type', ['daily', 'weekly', 'monthly', 'quarterly', 'annual', 'conditional'])->default('monthly');
            $table->integer('frequency_days')->nullable()->comment('Frequency in days');
            $table->integer('frequency_hours')->nullable()->comment('Frequency in operating hours');
            $table->integer('frequency_cycles')->nullable()->comment('Frequency in production cycles');
            $table->timestamp('last_performed_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('estimated_duration_minutes')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['machine_id', 'next_due_at']);
            $table->index(['is_overdue', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
