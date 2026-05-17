<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->name('fk_user_devices__user_id__users');

            // Logical OS family the device runs on (ios/android/web).
            $table->string('platform', 20);

            // Push transport the token belongs to (fcm/expo/apns). Kept
            // provider-agnostic so a single table serves every service.
            $table->string('provider', 20);

            // The opaque push token issued by the provider. Globally unique:
            // re-registering an existing token transfers it to the new owner
            // so a recycled device never receives another user's pushes.
            $table->string('push_token');

            // Client-supplied stable device identifier (vendor id, install id).
            // Lets a client update its own row without first knowing the token.
            $table->string('device_id')->nullable();

            $table->string('device_name')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->unique('push_token', 'uq_user_devices__push_token');
            $table->index(['user_id', 'provider'], 'idx_user_devices__user_id_provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
