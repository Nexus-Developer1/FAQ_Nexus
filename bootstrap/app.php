<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cabeçalhos de segurança (CSP, nosniff, X-Frame, Referrer) em TODAS as respostas —
        // global e o primeiro da fila, para embrulhar também os redirects e os erros que
        // os outros middlewares devolvem antes de chegar à rota.
        $middleware->prepend(\App\Http\Middleware\CabecalhosSeguranca::class);

        $middleware->alias([
            'acesso' => \App\Http\Middleware\ExigeAcessoAplicacao::class,
        ]);

        // Quem ficar com a conta desactivada é posto fora no pedido seguinte,
        // mesmo que tenha entrado pelo "manter sessão iniciada".
        $middleware->web(append: [\App\Http\Middleware\GarantirContaActiva::class]);

        // Sem sessão, vai-se ao portal fazer o login.
        $middleware->redirectGuestsTo(fn () => config('app.portal_url'));
        $middleware->redirectUsersTo(fn () => route('consulta'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
