<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_interest_events', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->nullable()->after('notes');
            $table->string('currency', 3)->nullable()->after('amount');
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('property_interest_events', function (Blueprint $table) {
            $table->dropColumn(['amount', 'currency', 'exchange_rate']);
        });
    }
};
