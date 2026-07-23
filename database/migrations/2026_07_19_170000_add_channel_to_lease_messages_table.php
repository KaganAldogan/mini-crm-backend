<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_messages', function (Blueprint $table) {
            $table->string('channel', 32)->default('shared')->after('body');
            $table->index(['lease_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('lease_messages', function (Blueprint $table) {
            $table->dropIndex(['lease_id', 'channel']);
            $table->dropColumn('channel');
        });
    }
};
