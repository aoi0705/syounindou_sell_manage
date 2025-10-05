<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_shipped')) {
                $table->boolean('is_shipped')->default(false)->after('mail_preference');
            }
            if (!Schema::hasColumn('orders', 'is_gift')) {
                $table->boolean('is_gift')->default(false)->after('is_shipped');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_gift')) {
                $table->dropColumn('is_gift');
            }
            if (Schema::hasColumn('orders', 'is_shipped')) {
                $table->dropColumn('is_shipped');
            }
        });
    }
};
