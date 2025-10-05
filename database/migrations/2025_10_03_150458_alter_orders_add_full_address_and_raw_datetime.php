<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'purchased_at_text')) {
                $table->string('purchased_at_text')->nullable()->after('purchased_at');
            }
            if (!Schema::hasColumn('orders', 'buyer_address_full')) {
                $table->text('buyer_address_full')->nullable()->after('buyer_kana');
            }
            if (!Schema::hasColumn('orders', 'shipto_address_full')) {
                $table->text('shipto_address_full')->nullable()->after('shipto_kana');
            }
            if (!Schema::hasColumn('orders', 'mail_preference')) {
                $table->string('mail_preference')->nullable()->after('ship_time_window');
            }

            // 旧列（buyer_pref/address1/2等）は残します。不要なら別途 drop を作成してください。
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'purchased_at_text')) {
                $table->dropColumn('purchased_at_text');
            }
            if (Schema::hasColumn('orders', 'buyer_address_full')) {
                $table->dropColumn('buyer_address_full');
            }
            if (Schema::hasColumn('orders', 'shipto_address_full')) {
                $table->dropColumn('shipto_address_full');
            }
            if (Schema::hasColumn('orders', 'mail_preference')) {
                $table->dropColumn('mail_preference');
            }
        });
    }
};
