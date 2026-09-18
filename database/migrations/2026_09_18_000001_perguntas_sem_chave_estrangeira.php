<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As perguntas ao assistente deixam de ter chave estrangeira no utilizador.
 *
 * Porquê: as contas da Knowledgebase NÃO vivem nesta base de dados. O modelo `User` lê a
 * tabela `utilizadores` da base da suite (ver `config('database.suite')`), e a tabela
 * `perguntas` vive aqui, em `procedimentos`. A chave apontava para a tabela `users` local —
 * um resto do esqueleto do Laravel, com duas linhas antigas — por isso qualquer registo
 * rebentava com violação de chave estrangeira.
 *
 * Como o `registar()` apanha a excepção para nunca estragar a resposta ao utilizador, a
 * falha passou despercebida: a tabela esteve sempre vazia desde que foi criada (16/09).
 * Não há chave estrangeira entre bases de dados; a coluna fica como número simples.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perguntas', function (Blueprint $tabela) {
            $tabela->dropForeign('perguntas_user_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::table('perguntas', function (Blueprint $tabela) {
            $tabela->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
