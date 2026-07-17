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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('interest_type')->nullable()->after('status');
            $table->string('property_type')->nullable()->after('interest_type');
            $table->unsignedInteger('budget_min')->nullable()->after('property_type');
            $table->unsignedInteger('budget_max')->nullable()->after('budget_min');
            $table->string('rooms')->nullable()->after('budget_max');
            $table->string('preferred_location')->nullable()->after('rooms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'interest_type',
                'property_type',
                'budget_min',
                'budget_max',
                'rooms',
                'preferred_location',
            ]);
        });
    }
};
