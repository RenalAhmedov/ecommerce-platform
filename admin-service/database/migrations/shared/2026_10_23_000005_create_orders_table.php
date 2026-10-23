<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id()->generatedAs()->always();
            $table->string('order_number', 32);
            $table->bigInteger('user_id');
            $table->string('status', 20)->default('pending');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('payment_reference', 100)->nullable();
            $table->jsonb('shipping_address')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->unique('order_number');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'payment_failed'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_check CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_check CHECK (total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
