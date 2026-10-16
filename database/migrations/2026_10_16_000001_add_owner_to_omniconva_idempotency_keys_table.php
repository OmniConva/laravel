<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('omniconva.idempotency_table', 'omniconva_idempotency_keys'), function (Blueprint $table) {
            // Which request holds a pending claim, so one whose claim was
            // taken over after a stall can't release the claim that replaced it.
            $table->string('owner', 32)->nullable()->after('status');
            // OmniConva's keys are up to 255 characters.
            $table->string('key', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table(config('omniconva.idempotency_table', 'omniconva_idempotency_keys'), function (Blueprint $table) {
            $table->dropColumn('owner');
        });
    }
};
