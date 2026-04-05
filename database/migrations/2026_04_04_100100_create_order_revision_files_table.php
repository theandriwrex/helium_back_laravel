<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_revision_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_revision_id')
                ->constrained('order_revisions')
                ->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('order_revision_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revision_files');
    }
};

