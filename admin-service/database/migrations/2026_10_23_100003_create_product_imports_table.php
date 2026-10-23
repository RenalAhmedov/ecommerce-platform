<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id()->generatedAs()->always();
            $table->bigInteger('user_id')->nullable();
            $table->string('original_name', 255);
            $table->string('file_path', 500);
            $table->string('status', 20)->default('queued');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('created_count')->default(0);
            $table->integer('updated_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->jsonb('errors')->default(DB::raw("'[]'::jsonb"));
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE product_imports ADD CONSTRAINT product_imports_status_check CHECK (status IN ('queued', 'processing', 'completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
