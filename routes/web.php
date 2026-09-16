<?php

use App\Http\Controllers\Gerir\CategoryController;
use App\Http\Controllers\Gerir\ProcedureController as GerirProcedureController;
use App\Http\Controllers\Gerir\SafetyRuleController;
use App\Http\Controllers\AnexoController;
use App\Http\Controllers\AssistenteController;
use App\Http\Controllers\ProcedureController;
use Illuminate\Support\Facades\Route;

// A entrada é feita no portal, que é quem trata do login e da verificação em
// duas etapas. Aqui só se recebe quem já vem autenticado e com acesso a esta
// aplicação (ver o middleware ExigeAcessoAplicacao, no grupo de baixo).
Route::get('/entrar', fn () => redirect()->away(config('app.portal_url')))->name('login');

// ---------- Consulta ----------
Route::middleware(['auth', 'acesso'])->group(function () {
    Route::get('/', [ProcedureController::class, 'index'])->name('consulta');
    Route::get('/imprimir', [ProcedureController::class, 'printAll'])->name('imprimir');
    Route::get('/procedimentos/{procedure}/imprimir', [ProcedureController::class, 'printOne'])->name('imprimir.um');

    // Os anexos não estão na pasta pública: passam por aqui, onde se confirma
    // a sessão e a área de quem pede (ver o AnexoController).
    Route::get('/procedimentos/{procedure}/anexos/{anexo}', [AnexoController::class, 'mostrar'])->name('anexo');

    // Assistente: pergunta em linguagem normal, respondida pelo modelo local (set. 2026).
    Route::post('/perguntar', [AssistenteController::class, 'perguntar'])->name('assistente.perguntar');
});

// Endereços antigos: /admin/... passou a /gerir/..., mas há favoritos por aí.
// Route::redirect() escreve o caminho a contar da raiz do domínio e perdia o
// /knowledgebase-nexus; por isso vai por rota e por url(), que respeitam a
// sub-pasta onde a aplicação está instalada.
Route::get('/admin', fn () => redirect()->route('gerir.procedimentos.index'));
Route::get('/admin/{resto}', fn (string $resto) => redirect(url('gerir/'.$resto)))
    ->where('resto', '.*');

// ---------- Gerir conteúdo (requer permissão de edição) ----------
//
// O endereço diz "gerir" e não "admin": quem entra aqui pode ser apenas
// Editor, e "admin" dava a entender que era preciso ser administrador.
Route::middleware(['auth', 'acesso', 'can:editar'])->prefix('gerir')->name('gerir.')->group(function () {
    Route::redirect('/', '/gerir/procedimentos');

    // Procedimentos
    Route::get('procedimentos', [GerirProcedureController::class, 'index'])->name('procedimentos.index');
    Route::get('procedimentos/novo', [GerirProcedureController::class, 'create'])->name('procedimentos.create');
    Route::post('procedimentos', [GerirProcedureController::class, 'store'])->name('procedimentos.store');
    Route::get('procedimentos/{procedure}/editar', [GerirProcedureController::class, 'edit'])->name('procedimentos.edit');
    Route::put('procedimentos/{procedure}', [GerirProcedureController::class, 'update'])->name('procedimentos.update');
    Route::delete('procedimentos/{procedure}', [GerirProcedureController::class, 'destroy'])->middleware('can:admin')->name('procedimentos.destroy');
    Route::delete('procedimentos/{procedure}/anexos/{anexo}', [GerirProcedureController::class, 'destroyAnexo'])->name('procedimentos.anexos.destroy');

    // ---- Só administradores a partir daqui ----
    Route::middleware('can:admin')->group(function () {

    // Perfis: passaram para o portal, em "Quem acede a quê".
    //
    // Estiveram aqui e lá ao mesmo tempo, cada um com a sua cópia, e os dois
    // discordavam — o portal dizia "Leitor · Área técnica" e aqui a pessoa não
    // via nada. Quem tiver a morada antiga guardada vai dar ao sítio certo.
    Route::get('utilizadores', fn () => redirect()->away(
        rtrim(config('app.portal_url'), '/').'/gestao/utilizadores'
    ))->name('utilizadores.index');
    Route::get('utilizadores/{utilizador}/editar', fn (int $utilizador) => redirect()->away(
        rtrim(config('app.portal_url'), '/').'/gestao/utilizadores/'.$utilizador
    ))->name('utilizadores.edit');

    // Categorias
    Route::get('categorias', [CategoryController::class, 'index'])->name('categorias.index');
    Route::post('categorias', [CategoryController::class, 'store'])->name('categorias.store');
    Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categorias.update');
    Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categorias.destroy');

    // Regras de segurança
    Route::get('regras-seguranca', [SafetyRuleController::class, 'index'])->name('regras.index');
    Route::post('regras-seguranca', [SafetyRuleController::class, 'store'])->name('regras.store');
    Route::put('regras-seguranca/{rule}', [SafetyRuleController::class, 'update'])->name('regras.update');
    Route::post('regras-seguranca/{rule}/mover', [SafetyRuleController::class, 'move'])->name('regras.move');
    Route::delete('regras-seguranca/{rule}', [SafetyRuleController::class, 'destroy'])->name('regras.destroy');

    }); // fim: só administradores
});
