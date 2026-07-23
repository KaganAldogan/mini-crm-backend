<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('lease_end_reminder_enabled')->default(true)->after('customer_id');
            $table->unsignedSmallInteger('lease_end_reminder_days')->default(30)->after('lease_end_reminder_enabled');
            $table->boolean('lease_end_reminder_email')->default(false)->after('lease_end_reminder_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'lease_end_reminder_enabled',
                'lease_end_reminder_days',
                'lease_end_reminder_email',
            ]);
        });
    }
};
