<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')
            ->where('status', 'reserved')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        // irreversible cleanup
    }
};
