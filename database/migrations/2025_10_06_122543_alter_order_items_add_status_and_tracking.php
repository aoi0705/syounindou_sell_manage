<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // 0:未対応 / 1:送り状発行済み / 2:発送済み
            $table->unsignedTinyInteger('status')
                  ->default(0)->index()
                  ->comment('0=pending,1=label_issued,2=shipped');

            $table->string('tracking_no', 100)->nullable()->index();
            $table->timestamp('label_issued_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['status','tracking_no','label_issued_at','shipped_at']);
        });
    }
};
