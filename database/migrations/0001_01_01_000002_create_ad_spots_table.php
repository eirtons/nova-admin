<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_spots', function (Blueprint $table) {
            $table->id();
            $table->string('position');        // 广告位 key（枚举，非唯一）
            $table->longText('head_code')->nullable();
            $table->longText('body_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['position', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spots');
    }
};
