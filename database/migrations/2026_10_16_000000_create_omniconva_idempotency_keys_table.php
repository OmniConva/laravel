<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('omniconva.idempotency_table', 'omniconva_idempotency_keys'), function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->string('status', 16);
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('omniconva.idempotency_table', 'omniconva_idempotency_keys'));
    }
};
