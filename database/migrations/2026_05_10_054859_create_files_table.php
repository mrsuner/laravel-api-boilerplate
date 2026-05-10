<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('disk');
            $table->string('path');
            $table->string('client_name');
            $table->string('mime_type');
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('checksum')->nullable();
            $table->string('visibility')->default('private');

            $table->nullableUlidMorphs('uploader', 'idx_files__uploader');

            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable()->index('idx_files__expires_at');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
