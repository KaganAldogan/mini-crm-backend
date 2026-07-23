<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->text('completion_note')->nullable()->after('tenant_note');
            $table->timestamp('technician_completed_at')->nullable()->after('appointment_at');
            $table->timestamp('tenant_confirmed_at')->nullable()->after('technician_completed_at');
        });

        Schema::table('maintenance_request_images', function (Blueprint $table) {
            $table->string('kind', 20)->default('request')->after('maintenance_request_id');
            $table->index(
                ['maintenance_request_id', 'kind', 'sort_order'],
                'maint_req_images_req_kind_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_request_images', function (Blueprint $table) {
            $table->dropIndex('maint_req_images_req_kind_sort_idx');
            $table->dropColumn('kind');
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn([
                'completion_note',
                'technician_completed_at',
                'tenant_confirmed_at',
            ]);
        });
    }
};
