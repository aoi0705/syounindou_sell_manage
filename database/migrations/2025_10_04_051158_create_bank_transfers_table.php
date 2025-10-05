<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_at_text')->nullable(); // 原文（例: 2024年04月03日）
            $table->dateTime('transfer_at')->nullable();    // パース成功時
            $table->integer('amount')->default(0);
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('payer_name')->nullable();
            $table->text('raw_body')->nullable();           // 取り込みテキスト原文
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};
