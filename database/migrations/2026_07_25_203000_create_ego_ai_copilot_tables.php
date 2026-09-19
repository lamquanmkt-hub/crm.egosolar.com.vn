<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_providers')) {
            Schema::create('ai_providers', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('provider_type', 40);
                $table->string('base_url', 500)->nullable();
                $table->string('model', 160);
                $table->string('fast_model', 160)->nullable();
                $table->longText('api_key')->nullable();
                $table->string('organization', 160)->nullable();
                $table->string('project', 160)->nullable();
                $table->unsignedSmallInteger('timeout_seconds')->default(60);
                $table->unsignedInteger('max_output_tokens')->default(1600);
                $table->decimal('temperature', 4, 2)->default(0.20);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->json('extra_config')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['is_active', 'is_default']);
            });
        }

        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
                $table->string('title', 180)->default('Cuộc trò chuyện mới');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'last_message_at']);
            });
        }

        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('role', 20);
                $table->longText('content');
                $table->string('model', 160)->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['conversation_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
                $table->string('request_id', 180)->nullable();
                $table->string('model', 160)->nullable();
                $table->string('status', 30)->default('success');
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->unsignedInteger('latency_ms')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['provider_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_providers');
    }
};
