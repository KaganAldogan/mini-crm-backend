<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->foreignUuid('decided_by_user_id')
                ->nullable()
                ->after('technician_user_id')
                ->constrained('users', 'uid')
                ->nullOnDelete();
            $table->timestamp('appointment_at')
                ->nullable()
                ->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by_user_id');
            $table->dropColumn('appointment_at');
        });
    }
};
