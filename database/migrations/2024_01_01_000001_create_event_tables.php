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
        // Event Logs Table
        Schema::create('event_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type', 255)->index();
            $table->string('aggregate_type', 100)->index();
            $table->uuid('aggregate_id')->index();
            $table->uuid('correlation_id')->index();
            $table->uuid('causation_id')->nullable();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->uuid('company_id')->nullable()->index();
            $table->longText('payload');
            $table->longText('metadata')->nullable();
            $table->timestamp('published_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->boolean('is_processed')->default(false)->index();
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['published_at', 'is_processed']);
            $table->index(['correlation_id', 'published_at']);
        });

        // Idempotent Events Table (for deduplication)
        Schema::create('idempotent_events', function (Blueprint $table) {
            $table->string('idempotency_key', 255)->primary();
            $table->uuid('event_id')->index();
            $table->timestamp('created_at')->index();
            $table->timestamp('expires_at')->index();
        });

        // Sagas Table
        Schema::create('sagas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 255)->index();
            $table->string('state', 50)->index();
            $table->uuid('correlation_id')->nullable()->index();
            $table->longText('data')->nullable();
            $table->integer('step')->default(0);
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at')->index();
            $table->timestamp('completed_at')->nullable();

            $table->index(['type', 'state']);
            $table->index(['created_at', 'state']);
        });

        // Saga Events Table (audit trail)
        Schema::create('saga_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('saga_id')->index();
            $table->uuid('event_id')->index();
            $table->string('step_name', 255);
            $table->string('action', 50); // 'started', 'completed', 'compensated', 'failed'
            $table->text('context')->nullable();
            $table->timestamp('created_at')->index();

            $table->foreign('saga_id')->references('id')->on('sagas')->cascadeOnDelete();
        });

        // Dead Letter Messages Table
        Schema::create('dead_letter_messages', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->uuid('event_id')->nullable()->index();
            $table->string('event_type', 255)->nullable()->index();
            $table->string('aggregate_type', 100)->nullable()->index();
            $table->uuid('aggregate_id')->nullable()->index();
            $table->longText('payload');
            $table->longText('headers')->nullable();
            $table->integer('retry_count')->default(0)->index();
            $table->timestamp('created_at')->index();
            $table->timestamp('requeued_at')->nullable();

            $table->index(['event_type', 'created_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        // Event Handler Status Table
        Schema::create('event_handler_status', function (Blueprint $table) {
            $table->id();
            $table->string('handler_name', 255)->unique();
            $table->uuid('last_processed_event_id')->nullable();
            $table->timestamp('last_processed_at')->nullable();
            $table->integer('processed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['last_processed_at']);
        });

        // Event Metrics Table (for monitoring)
        Schema::create('event_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name', 255)->index();
            $table->string('event_type', 255)->nullable();
            $table->integer('count')->default(0);
            $table->integer('duration_ms')->nullable();
            $table->decimal('latency_ms', 10, 2)->nullable();
            $table->string('status', 50)->nullable();
            $table->date('date')->index();
            $table->timestamp('created_at');

            $table->unique(['metric_name', 'event_type', 'date']);
            $table->index(['event_type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_metrics');
        Schema::dropIfExists('event_handler_status');
        Schema::dropIfExists('dead_letter_messages');
        Schema::dropIfExists('saga_events');
        Schema::dropIfExists('sagas');
        Schema::dropIfExists('idempotent_events');
        Schema::dropIfExists('event_logs');
    }
};
