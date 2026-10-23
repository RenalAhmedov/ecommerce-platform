<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->generatedAs()->always();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('password', 255);
            $table->string('role', 20)->default('customer');
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));
            $table->timestampTz('deleted_at')->nullable();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('customer', 'admin'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
