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
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('report_type', ['shift', 'daily_oee', 'downtime', 'production'])->default('daily_oee');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'shift_end'])->default('daily');
            $table->time('send_time')->default('08:00:00');
            $table->json('recipients'); // Array of email addresses
            $table->foreignId('plant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('line_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
