<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id()->generatedAs()->always();
            $table->bigInteger('product_id');
            $table->integer('quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->unique('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_quantity_check CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_low_stock_threshold_check CHECK (low_stock_threshold >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
