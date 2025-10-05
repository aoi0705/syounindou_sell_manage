<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->string('shop_name')->nullable();
            $table->dateTime('purchased_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->integer('subtotal')->default(0);
            $table->integer('shipping_fee')->default(0);
            $table->integer('cool_fee')->default(0);
            $table->integer('total')->default(0);
            $table->integer('tax10')->default(0);
            $table->integer('tax8')->default(0);
            $table->longText('raw_body');
            $table->string('ship_carrier')->nullable();
            $table->string('ship_date_request')->nullable();
            $table->string('ship_time_window')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_kana')->nullable();
            $table->string('buyer_postal')->nullable();
            $table->string('buyer_pref')->nullable();
            $table->string('buyer_address1')->nullable();
            $table->string('buyer_address2')->nullable();
            $table->string('buyer_tel')->nullable();
            $table->string('buyer_mobile')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('shipto_name')->nullable();
            $table->string('shipto_kana')->nullable();
            $table->string('shipto_postal')->nullable();
            $table->string('shipto_pref')->nullable();
            $table->string('shipto_address1')->nullable();
            $table->string('shipto_address2')->nullable();
            $table->string('shipto_tel')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
