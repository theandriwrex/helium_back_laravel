<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->text('freelancer_note');
            $table->text('client_feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'revision_number']);
            $table->index(['order_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revisions');
    }
};

