<?php

namespace App\Http\Controllers;

use App\Models\Pergunta;
use App\Services\AssistenteKnowledgebase;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Responde a perguntas em linguagem normal, com o modelo local (Ollama) a ler apenas os
 * procedimentos que a pessoa pode ver. Quando não há procedimento sobre o assunto, responde
 * na mesma com o conhecimento geral do modelo — e diz que o faz (set. 2026). A resposta vai
 * a sair palavra a palavra: escrever demora ~20s neste servidor, mas as primeiras linhas
 * aparecem em 2-3s.
 */
class AssistenteController extends Controller
{
    public function perguntar(Request $request, AssistenteKnowledgebase $assistente): StreamedResponse
    {
        $dados = $request->validate([
            'pergunta' => ['required', 'string', 'min:5', 'max:500'],
            // Pergunta e resposta anteriores, para dar seguimento ("e no Office 2016?").
            'contexto' => ['nullable', 'array'],
            'contexto.pergunta' => ['nullable', 'string', 'max:500'],
            'contexto.resposta' => ['nullable', 'string', 'max:1500'],
        ]);

        $pergunta = trim($dados['pergunta']);
        $utilizador = $request->user();

        if (! config('assistente.ativo')) {
            return $this->fluxo([['erro' => 'O assistente está desligado de momento.']]);
        }

        // Travão por pessoa: gerar ocupa o servidor, não pode ser martelado.
        $chave = 'assistente:'.$utilizador->id;
        if (RateLimiter::tooManyAttempts($chave, (int) config('assistente.limite_por_minuto'))) {
            return $this->fluxo([['erro' => 'Muitas perguntas seguidas. Aguarde um momento.']]);
        }
        RateLimiter::hit($chave, 60);

        // 1.ª etapa: a pesquisa escolhe os procedimentos. A pergunta fica sempre registada
        // (com ou sem procedimento) — é o que diz o que falta documentar.
        $contexto = $dados['contexto'] ?? null;

        $procedimentos = $assistente->procedimentosRelevantes($pergunta, $utilizador, contexto: $contexto);

        Pergunta::registar($pergunta, $utilizador, $procedimentos->pluck('id')->all());

        // Sem procedimento, o modelo responde com o que sabe — e a pessoa fica a saber
        // que a resposta não vem da Knowledgebase (a nota aparece por cima do texto).
        $nota = $procedimentos->isEmpty()
            ? 'Não há procedimento sobre isto na Knowledgebase. Resposta com o conhecimento geral do assistente — confirme antes de executar.'
            : null;

        $instrucoes = $procedimentos->isEmpty()
            ? $assistente->instrucoesGerais($contexto)
            : $assistente->instrucoes($procedimentos, $contexto);

        $citados = $procedimentos->map(fn ($p) => [
            'ref' => 'PROC-'.str_pad((string) $p->reference_number, 2, '0', STR_PAD_LEFT),
            'titulo' => $p->title,
            'ancora' => $p->reference_number,
        ])->all();

        return response()->stream(function () use ($instrucoes, $pergunta, $citados, $nota) {
            ob_implicit_flush(true);

            // Os procedimentos vão primeiro: a pessoa tem a ligação certa em menos de 1s,
            // enquanto o texto da resposta ainda está a ser escrito.
            $this->enviar(['procedimentos' => $citados, 'nota' => $nota]);

            // Uma geração de cada vez no servidor inteiro: duas em paralelo duplicavam a
            // carga dos 4 núcleos e atrasavam tudo o resto.
            $lock = Cache::lock('assistente:gerar', (int) config('assistente.timeout') + 30);

            try {
                $lock->block((int) config('assistente.espera_lock'), function () use ($instrucoes, $pergunta) {
                    $this->gerar($instrucoes, $pergunta);
                });
            } catch (LockTimeoutException $e) {
                $this->enviar(['erro' => 'O assistente está a responder a outra pergunta. Tente daqui a pouco.']);
            }

            $this->enviar(['fim' => true]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no', // não acumular no proxy
        ]);
    }

    /** Fala com o Ollama e vai despejando o texto à medida que chega. */
    private function gerar(string $instrucoes, string $pergunta): void
    {
        $corpo = json_encode([
            'model' => config('assistente.modelo'),
            'system' => $instrucoes,
            'prompt' => $pergunta,
            'stream' => true,
            'think' => false, // sem "pensamento" à frente: triplicava o tempo sem melhorar
            'options' => [
                'temperature' => 0.2,
                'num_predict' => (int) config('assistente.max_palavras'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init(rtrim((string) config('assistente.url'), '/').'/api/generate');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $corpo,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => (int) config('assistente.timeout'),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $pedaco) {
                foreach (preg_split('/\r?\n/', $pedaco, -1, PREG_SPLIT_NO_EMPTY) as $linha) {
                    $j = json_decode($linha, true);
                    if (isset($j['response']) && $j['response'] !== '') {
                        $this->enviar(['texto' => $j['response']]);
                    }
                }

                return strlen($pedaco);
            },
        ]);

        if (curl_exec($ch) === false) {
            $erro = curl_error($ch);
            Log::warning('Assistente: falha a falar com o modelo.', ['erro' => $erro]);
            $this->enviar(['erro' => 'O assistente não respondeu. Use a pesquisa e os procedimentos em baixo.']);
        }

        curl_close($ch);
    }

    /**
     * Uma linha de JSON por evento (o browser lê linha a linha). O PHP-FPM tem
     * output_buffering=4096: sem o ob_flush() o texto só sairia de 4 KB em 4 KB, ou seja,
     * a resposta aparecia toda de uma vez no fim — que é o que se quer evitar.
     */
    private function enviar(array $dados): void
    {
        echo json_encode($dados, JSON_UNESCAPED_UNICODE)."\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

    /** @param  list<array<string, mixed>>  $linhas */
    private function fluxo(array $linhas): StreamedResponse
    {
        return response()->stream(function () use ($linhas) {
            foreach ($linhas as $l) {
                $this->enviar($l);
            }
            $this->enviar(['fim' => true]);
        }, 200, ['Content-Type' => 'application/x-ndjson; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }
}
