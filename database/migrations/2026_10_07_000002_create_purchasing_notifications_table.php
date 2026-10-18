<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchasing_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permintaan_pembelian_id')->constrained('permintaan_pembelian')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['recipient_id', 'permintaan_pembelian_id'], 'purchasing_notifications_recipient_request_unique');
            $table->index(['recipient_id', 'read_at', 'created_at'], 'purchasing_notifications_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_notifications');
    }
};
