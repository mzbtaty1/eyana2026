<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Full-table schema drift catch-up. The `passwords` table exists on
     * live Dev (backing the wired UserController::password()/
     * password_create()/password_save() routes at /passwords,
     * /passwords/create, /passwords/save) but was never captured by any
     * migration. Recreates it at its live Dev column set. Live Dev uses
     * MyISAM/latin1 (evidence it was created outside Laravel, same
     * signature as the previously-fixed `visas` table); this migration
     * uses InnoDB instead, following the same precedent already applied
     * to `visas`, for consistency with every other table in this schema.
     *
     * `id` is a signed INT(11) auto-increment primary key, matching live
     * Dev exactly, instead of the standard bigint unsigned $table->id()
     * produces.
     */
    public function up(): void
    {
        if (!Schema::hasTable('passwords')) {
            Schema::create('passwords', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->integer('id', true, false);
                $table->text('url');
                $table->text('user');
                $table->text('pass');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passwords');
    }
};
