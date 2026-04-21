<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagrams', function (Blueprint $table) {
            $table->id();

            $table->uuid('diagram_id')->index();

            $table->unsignedInteger('version')->default(0);

            $table->jsonb('diagram');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Para assegurar que não existe um diagrama com duas versões iguais
            $table->unique(['diagram_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagrams');
    }
};
