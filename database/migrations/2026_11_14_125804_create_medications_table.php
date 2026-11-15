<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 40);
            $table->string('form', 40);
            $table->string('strength', 40)->nullable();
            $table->string('unit', 20)->default('adet');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('min_threshold');
            $table->date('expires_on')->nullable();
            $table->string('storage_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
