<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang');
            $table->foreignId('barang_id')->constrained('barang');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_petugas', 150);
            $table->unsignedInteger('stok_sebelum');
            $table->unsignedInteger('stok_fisik');
            $table->bigInteger('selisih');
            $table->string('alasan', 1000);
            $table->timestamp('dibuat_pada')->useCurrent();
            $table->index(['gudang_id', 'dibuat_pada']);
            $table->index(['barang_id', 'dibuat_pada']);
        });

        Schema::create('warehouse_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120);
            $table->foreignId('gudang_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->string('event_key', 60);
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255);
            $table->text('details');
            $table->timestamp('dibuat_pada')->useCurrent();
            $table->index(['gudang_id', 'dibuat_pada']);
            $table->index(['event_key', 'dibuat_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_activity_logs');
        Schema::dropIfExists('stock_adjustments');
    }
};
