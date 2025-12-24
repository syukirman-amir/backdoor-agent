<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('app_id')->unique();           // unik: vps-prod-01-payment
            $table->string('app_name');
            $table->string('hostname');
            $table->string('ip_address');
            $table->text('public_key');
            $table->text('old_public_key')->nullable();
            $table->json('tech_stack')->nullable();
            $table->enum('status', ['pending', 'approved', 'revoked'])->default('pending');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('key_rotated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['hostname', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};