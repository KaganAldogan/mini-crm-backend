<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->foreignUuid('landlord_customer_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customers', 'uid')
                ->nullOnDelete();
            $table->boolean('managed_by_agency')
                ->default(true)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('landlord_customer_id');
            $table->dropColumn('managed_by_agency');
        });
    }
};
