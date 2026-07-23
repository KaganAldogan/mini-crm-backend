<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->string('location', 180)->nullable()->after('description');
            $table->string('urgency', 20)->default('normal')->after('location');
            $table->index('urgency');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropIndex(['urgency']);
            $table->dropColumn(['location', 'urgency']);
        });
    }
};
