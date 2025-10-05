<?php

// database/migrations/2025_10_04_180000_add_note_to_orders_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'note')) {
                $table->text('note')->nullable()->after('raw_body');
            }
        });
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
