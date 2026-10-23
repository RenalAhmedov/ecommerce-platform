<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id()->generatedAs()->always();
            $table->bigInteger('parent_id')->nullable();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });

        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_parent_check CHECK (parent_id IS NULL OR parent_id <> id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
