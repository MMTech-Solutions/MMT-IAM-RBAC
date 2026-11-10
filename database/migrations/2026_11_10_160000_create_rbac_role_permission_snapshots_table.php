<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rbac_role_permission_snapshots')) {
            return;
        }

        Schema::create('rbac_role_permission_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('message_key', 191)->unique();
            $table->uuid('role_id');
            $table->string('surface', 32);
            $table->unsignedBigInteger('rev');
            $table->json('permissions');
            $table->string('snapshot_updated_at', 64)->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'surface']);
            $table->index(['role_id', 'surface', 'rev']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_role_permission_snapshots');
    }
};
