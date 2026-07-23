<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->unsignedBigInteger('deposit_amount')->nullable()->after('notes');
            $table->string('deposit_currency', 3)->default('TRY')->after('deposit_amount');
            $table->date('deposit_paid_at')->nullable()->after('deposit_currency');
            $table->decimal('deposit_exchange_rate', 12, 4)->nullable()->after('deposit_paid_at');
            $table->decimal('deposit_current_rate', 12, 4)->nullable()->after('deposit_exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'deposit_currency',
                'deposit_paid_at',
                'deposit_exchange_rate',
                'deposit_current_rate',
            ]);
        });
    }
};
