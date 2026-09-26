<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_token')->unique();       // token untuk pasien, di session/cookie
            $table->string('queue_date', 10);              // Y-m-d, reset nomor harian
            $table->unsignedInteger('queue_number');       // 1,2,3... hari ini
            $table->string('queue_label', 10);             // "A-001"

            $table->enum('status', ['WAITING', 'CALLED', 'DONE', 'SKIPPED'])
                ->default('WAITING');

            $table->foreignId('counter_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // FIFO: kolom ini yang dipakai lockForUpdate() + orderBy
            $table->unique(['queue_date', 'queue_number']);
            $table->index(['queue_date', 'status', 'id']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
