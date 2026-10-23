<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id()->generatedAs()->always();
            $table->bigInteger('product_id');
            $table->bigInteger('order_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->integer('change');
            $table->string('reason', 20);
            $table->string('note', 255)->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_change_check CHECK (change <> 0)');
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_reason_check CHECK (reason IN ('initial', 'restock', 'sale', 'cancellation', 'adjustment'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
