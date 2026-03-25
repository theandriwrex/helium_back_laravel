<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('rating');
            $table->index('created_at');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index(['freelancer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['rating']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['freelancer_id', 'is_active']);
        });
    }
};
