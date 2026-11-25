<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Misma tabla damaged_products del backend del proyecto de aula (L3 Laravel / L4 PostgreSQL).
// Las restricciones CHECK propias de PostgreSQL solo se crean en pgsql; las pruebas usan SQLite.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('damage_level', ['leve', 'moderado', 'severo'])->index();
            $table->text('damage_notes')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->enum('status', ['draft', 'published', 'sold_out', 'archived'])->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['status', 'published_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE damaged_products ADD CONSTRAINT damaged_products_title_check CHECK (btrim(title) <> '')");
            DB::statement('ALTER TABLE damaged_products ADD CONSTRAINT damaged_products_price_check CHECK (price >= 0)');
            DB::statement('ALTER TABLE damaged_products ADD CONSTRAINT damaged_products_stock_check CHECK (stock >= 0)');
            DB::statement("ALTER TABLE damaged_products ADD CONSTRAINT damaged_products_published_check
                CHECK (status NOT IN ('published', 'sold_out') OR published_at IS NOT NULL)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_products');
    }
};
