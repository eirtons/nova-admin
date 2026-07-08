<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('static_pages', 'meta_description')) {
            return;
        }

        Schema::table('static_pages', function (Blueprint $table) {
            $table->string('meta_description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('static_pages', function (Blueprint $table) {
            $table->dropColumn('meta_description');
        });
    }
};
