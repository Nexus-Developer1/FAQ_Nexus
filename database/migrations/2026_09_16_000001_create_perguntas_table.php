<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Perguntas feitas ao assistente (set. 2026). O valor está nas que ficam com
// encontrou = false: é a lista do que falta documentar na Knowledgebase.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perguntas', function (Blueprint $t) {
            $t->id();
            $t->string('pergunta', 500);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nome')->nullable();          // quem perguntou, mesmo que a conta saia
            $t->json('procedimentos')->nullable();   // ids que a pesquisa escolheu
            $t->boolean('encontrou')->default(false);
            $t->timestamps();

            $t->index(['encontrou', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perguntas');
    }
};
