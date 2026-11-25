<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Estado de cada notificación de producto dañado: pendiente → enviada (o fallida).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('correlation_id', 100)->index();
            $table->string('modo', 10);                 // asincrono | sincrono
            $table->foreignId('product_id')->nullable()->constrained('damaged_products')->nullOnDelete();
            $table->string('canal', 10);                // email | sms
            $table->string('estado', 15)->default('pendiente');
            $table->string('error')->nullable();
            $table->timestamp('procesada_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
