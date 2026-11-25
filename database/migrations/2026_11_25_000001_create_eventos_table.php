<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Recorrido de cada evento: recibido → filtrado | enrutado | no_enrutable → procesado.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 60)->unique();   // campo "id" del contrato
            $table->string('correlation_id', 100)->index();
            $table->string('tipo', 50);
            $table->string('prioridad', 10);
            $table->foreignId('product_id')->nullable()->constrained('damaged_products')->nullOnDelete();
            $table->json('datos');
            $table->string('destino', 30)->nullable();     // lo decide el enrutador
            $table->string('estado', 20);
            $table->string('motivo')->nullable();           // por qué se filtró o falló
            $table->string('procesado_por', 30)->nullable(); // consumidor que lo atendió
            $table->json('resultado')->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
