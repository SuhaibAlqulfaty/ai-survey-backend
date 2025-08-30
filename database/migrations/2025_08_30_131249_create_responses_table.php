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
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->onDelete('cascade');
            $table->string('user_id')->nullable(); // CDP user ID or anonymous session
            $table->string('session_id')->nullable();
            $table->json('responses'); // Question responses
            $table->integer('nps_score')->nullable();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->integer('completion_time')->nullable(); // in seconds
            $table->text('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('metadata')->nullable(); // Additional data from CDP
            $table->timestamps();
            
            // Indexes for analytics
            $table->index(['survey_id', 'created_at']);
            $table->index('user_id');
            $table->index('nps_score');
            $table->index('sentiment');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
