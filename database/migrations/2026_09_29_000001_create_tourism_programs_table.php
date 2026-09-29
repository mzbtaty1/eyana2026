<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Internal tourism (Phase 1): reusable programs («برامج سياحية») -- templates only.
     * A booking made from a program copies its items and never changes with it afterwards.
     * New table only; nothing existing is touched. Programs are deactivated, never deleted.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tourism_programs')) {
            Schema::create('tourism_programs', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('name', 255);
                $table->string('destination', 255)->nullable();
                $table->unsignedSmallInteger('days')->nullable();
                $table->unsignedSmallInteger('nights')->nullable();
                $table->text('description')->nullable();
                $table->tinyInteger('status')->default(1);   // 1 = active, 0 = inactive
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tourism_programs');
    }
};
