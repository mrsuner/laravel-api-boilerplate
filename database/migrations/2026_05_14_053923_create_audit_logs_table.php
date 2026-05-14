<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->name('fk_audit_logs__user_id__users');

            $table->string('event', 100);

            $table->nullableUlidMorphs('auditable', 'idx_audit_logs__auditable');

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id', 64)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'idx_audit_logs__user_id_created_at');
            $table->index(['event', 'created_at'], 'idx_audit_logs__event_created_at');
            $table->index('created_at', 'idx_audit_logs__created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
