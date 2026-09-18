<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Cabeçalhos de segurança, versionados com a aplicação (23.ª revisão de segurança, set. 2026).
//
// A Nexus Infra já os emitia por middleware desde julho; o portal e a Knowledgebase só tinham
// os que o Apache lhes punha (nosniff, X-Frame, Referrer) — e nenhuma Content-Security-Policy.
// O portal é onde se escrevem as palavras-passe da suite: é o sítio onde uma política de
// conteúdo mais faz falta. A política é a mesma da Nexus Infra: 'self' por defeito, scripts e
// estilos em linha permitidos (a página de entrada tem o "olhinho" da palavra-passe em linha),
// Google Fonts, imagens de data:, nada de plugins e nada de embutir a página noutro sítio.
//
// HSTS fica de fora de propósito: pertence à camada TLS (Apache), que sabe se a ligação é segura.
class CabecalhosSeguranca
{
    private const CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-eval' 'unsafe-inline'; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."font-src 'self' https://fonts.gstatic.com data:; "
        ."img-src 'self' data: blob:; "
        ."connect-src 'self'; "
        ."frame-ancestors 'self'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Não sobrepõe uma política que uma rota tenha definido de propósito.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::CSP);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
