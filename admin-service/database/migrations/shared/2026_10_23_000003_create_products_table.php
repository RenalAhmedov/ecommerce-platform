<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id()->generatedAs()->always();
            $table->bigInteger('category_id')->nullable();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->string('sku', 64);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('image_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
