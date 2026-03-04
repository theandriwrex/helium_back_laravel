<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('pse_reference');
            $table->text('requirements')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('requirements');
            $table->timestamp('delivered_at')->nullable()->after('started_at');
            $table->timestamp('completed_at')->nullable()->after('delivered_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'requirements',
                'started_at',
                'delivered_at',
                'completed_at',
                'cancelled_at',
            ]);
        });
    }
};
