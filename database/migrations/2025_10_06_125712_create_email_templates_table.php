<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();        // 例: shipping_completed
            $table->string('subject');              // 件名（Blade可）
            $table->longText('body_md');            // 本文Markdown（Blade可）
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
