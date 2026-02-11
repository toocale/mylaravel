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
        Schema::create('production_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who started
            $table->foreignId('shift_id')->nullable()->constrained()->onDelete('set null'); // Optional link to shift template
            $table->string('user_group')->nullable(); // The group name at time of start
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->json('metadata')->nullable(); // Extra info like notes, etc.
            $table->timestamps();
            
            // Index for quick lookup of active shifts
            $table->index(['machine_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_shifts');
    }
};
