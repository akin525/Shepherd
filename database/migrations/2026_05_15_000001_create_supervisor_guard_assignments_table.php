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
        Schema::create('supervisor_guard_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('supervisor_user_id');
            $table->unsignedBigInteger('guard_user_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique('guard_user_id');
            $table->unique(['client_id', 'supervisor_user_id', 'guard_user_id'], 'sga_client_supervisor_guard_unique');
            $table->index(['client_id', 'supervisor_user_id'], 'sga_client_supervisor_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_guard_assignments');
    }
};
