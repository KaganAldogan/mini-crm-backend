<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_interest_events', function (Blueprint $table) {
            $table->string('offer_intent', 20)->nullable()->after('type');
            $table->string('offer_timing', 30)->nullable()->after('offer_intent');
        });
    }

    public function down(): void
    {
        Schema::table('property_interest_events', function (Blueprint $table) {
            $table->dropColumn(['offer_intent', 'offer_timing']);
        });
    }
};
