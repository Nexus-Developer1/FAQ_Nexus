<?php

namespace App\Services;

use App\Models\Procedure;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assistente da Knowledgebase (set. 2026).
 *
 * Duas etapas, por esta ordem:
 *  1. PESQUISA — escolhe os procedimentos que interessam à pergunta, entre os que a pessoa
 *     PODE VER (a visibilidade por área é respeitada aqui; nunca se envia ao modelo nada
 *     que a pessoa não pudesse abrir a seguir).
 *  2. RESPOSTA — o modelo local (Ollama) explica com base nesses procedimentos. Quando a
 *     pesquisa não encontra nada, o modelo responde com o conhecimento geral dele, e a
 *     resposta vai marcada como tal (set. 2026) — é sempre uma resposta, nunca um «não sei».
 *
 * A pesquisa faz metade do trabalho: com os procedimentos certos escolhidos, o modelo lê
 * ~300 palavras em vez das ~10 000 da Knowledgebase toda — é a diferença entre responder
 * em segundos e demorar minutos.
 */
class AssistenteKnowledgebase
{
    /**
     * Procedimentos relevantes para a pergunta, do mais para o menos.
     *
     * Usa a pesquisa de texto do Postgres em português (percebe que "autentica",
     * "autenticar" e "autenticação" são a mesma palavra) sem acentos (quem escreve
     * "autenticacao" encontra na mesma), com o título a pesar mais do que os passos.
     *
     * @return Collection<int, Procedure>
     */
    public function procedimentosRelevantes(string $pergunta, ?User $utilizador, ?int $limite = null, ?array $contexto = null): Collection
    {
        $termos = $this->termos($pergunta);

        // Seguimento ("e no Office 2016?"): sozinha, a pergunta não tem palavras que
        // cheguem para procurar — junta-se a anterior para não perder o assunto.
        if (count($termos) < 3 && filled($contexto['pergunta'] ?? null)) {
            $termos = array_values(array_unique(array_merge($termos, $this->termos($contexto['pergunta']))));
        }
        if ($termos === []) {
            return collect();
        }

        $limite ??= (int) config('assistente.max_procedimentos');
        $consulta = implode(' | ', $termos); // OR: quanto mais palavras acertar, mais acima fica

        // Ids visíveis a esta pessoa — a filtragem por área vive no modelo, não aqui.
        $visiveis = Procedure::visivelPara($utilizador)->pluck('id');
        if ($visiveis->isEmpty()) {
            return collect();
        }

        $pontuados = DB::select(
            "select p.id,
                    ts_rank(
                        setweight(to_tsvector('portuguese', unaccent(coalesce(p.title, ''))), 'A')
                        || setweight(to_tsvector('portuguese', unaccent(coalesce(p.problem, ''))), 'B')
                        || setweight(to_tsvector('portuguese', unaccent(coalesce(passos.texto, ''))), 'C')
                        || setweight(to_tsvector('portuguese', unaccent(coalesce(c.name, ''))), 'C'),
                        to_tsquery('portuguese', unaccent(?))
                    ) as pontos
               from procedures p
               left join categories c on c.id = p.category_id
               left join lateral (
                    select string_agg(s.content, ' ') as texto
                      from procedure_steps s
                     where s.procedure_id = p.id
               ) passos on true
              where p.id = any(?)
              order by pontos desc
              limit ?",
            [$consulta, '{'.$visiveis->implode(',').'}', $limite]
        );

        // Cada procedimento a mais custa ~15s de leitura ao modelo neste servidor. Por isso
        // só entram os que estão perto do primeiro: numa pergunta clara vai um só; numa
        // ambígua vão dois ou três, para o modelo poder escolher.
        $linhas = collect($pontuados)->filter(fn ($l) => (float) $l->pontos > 0)->values();
        if ($linhas->isEmpty()) {
            return collect();
        }

        $melhor = (float) $linhas->first()->pontos;
        $ids = $linhas->filter(fn ($l) => (float) $l->pontos >= $melhor * 0.6)->pluck('id')->all();

        return Procedure::with(['category', 'steps'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true))
            ->values();
    }

    /**
     * Sinónimos da casa: o que as pessoas dizem → o que está escrito nos procedimentos.
     * Sem isto, "o servidor faz barulho" não encontra "reduzir a rotação das ventoinhas".
     * Cresce à medida do uso — a lista das perguntas sem resposta mostra o que falta aqui.
     *
     * @var array<string, list<string>>
     */
    private const SINONIMOS = [
        'barulho' => ['ventoinha', 'ventoinhas', 'rotacao', 'ruido'],
        'ruidoso' => ['ventoinha', 'rotacao'],
        'ruido' => ['ventoinha', 'rotacao'],
        'password' => ['palavra', 'passe', 'autenticacao'],
        'passe' => ['password', 'autenticacao'],
        'login' => ['autenticacao', 'sessao', 'entrar'],
        'dc' => ['dominio', 'controlador'],
        'ad' => ['dominio', 'directory'],
        'partilha' => ['smb', 'share', 'partilhas'],
        'pasta' => ['partilha', 'smb'],
        'impressora' => ['impressao', 'print'],
        'lento' => ['desempenho', 'performance'],
        'arrancar' => ['boot', 'arranque', 'iniciar'],
        'desliga' => ['encerra', 'shutdown'],
        'placa' => ['rede', 'nic', 'adaptador'],
        'internet' => ['rede', 'router', 'wan'],
        'mail' => ['email', 'outlook', 'exchange'],
        'correio' => ['email', 'outlook', 'exchange'],
        'disco' => ['armazenamento', 'raid', 'nas'],
        'copia' => ['backup', 'seguranca'],
        'maquina' => ['servidor', 'virtual'],
        'vm' => ['virtual', 'hyper'],
    ];

    /**
     * Palavras da pergunta que valem para pesquisar: sem acentos, sem as palavras vazias
     * do português e sem as de uma ou duas letras. Cada uma é escapada para o to_tsquery.
     *
     * @return list<string>
     */
    private function termos(string $pergunta): array
    {
        $vazias = ['que', 'qual', 'quais', 'como', 'para', 'por', 'com', 'sem', 'dos', 'das',
            'nos', 'nas', 'uma', 'uns', 'umas', 'este', 'esta', 'isto', 'esse', 'essa', 'isso',
            'aqui', 'ali', 'nao', 'sim', 'faco', 'fazer', 'tenho', 'quero', 'preciso', 'pode',
            'posso', 'devo', 'esta', 'estao', 'ser', 'sao', 'tem', 'meu', 'minha', 'seu', 'sua',
            'mais', 'menos', 'muito', 'pouco', 'sempre', 'nunca', 'depois', 'antes', 'entao'];

        $sem = strtr(mb_strtolower($pergunta), [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);

        $palavras = preg_split('/[^a-z0-9]+/u', $sem, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $termos = [];
        foreach ($palavras as $p) {
            if (mb_strlen($p) < 3 || in_array($p, $vazias, true)) {
                continue;
            }
            $termos[$p] = $p; // sem repetidos

            foreach (self::SINONIMOS[$p] ?? [] as $sinonimo) {
                $termos[$sinonimo] = $sinonimo;
            }
        }

        return array_values(array_slice($termos, 0, 18));
    }

    /**
     * As instruções e os procedimentos que vão para o modelo. Só isto: ele não tem acesso
     * a mais nada, por isso o que não estiver aqui não pode ser respondido.
     *
     * @param  Collection<int, Procedure>  $procedimentos
     */
    public function instrucoes(Collection $procedimentos, ?array $contexto = null): string
    {
        $blocos = $procedimentos->map(function (Procedure $p) {
            $passos = $p->steps->map(fn ($s) => $s->position.'. '.$s->content)->implode("\n");

            // Só o essencial: cada linha a mais são segundos de leitura para o modelo
            // neste servidor. Categoria e escalonamento ficam de fora — quem precisa
            // deles abre o procedimento pela ligação que aparece ao lado da resposta.
            return trim(
                'PROC-'.str_pad((string) $p->reference_number, 2, '0', STR_PAD_LEFT)
                .': '.$p->title."
"
                .($p->problem ? 'Problema: '.$p->problem."
" : '')
                .($passos !== '' ? "Passos:
".$passos : '')
            );
        })->implode("\n\n---\n\n");

        // Só se junta a troca anterior quando existe: cada linha a mais é tempo de leitura.
        $anterior = filled($contexto['pergunta'] ?? null)
            ? "\n\nCONVERSA ANTERIOR (para dar seguimento, não repitas):\nPergunta: "
                .$contexto['pergunta']."\nResposta: ".mb_substr((string) ($contexto['resposta'] ?? ''), 0, 600)
            : '';

        return <<<TEXTO
            És o assistente da Knowledgebase interna de uma empresa de informática.
            Respondes em português de Portugal, de forma curta e directa.

            REGRAS (segue-as sempre):
            - Se a pergunta é sobre o que está nos procedimentos abaixo, responde SÓ com
              base neles, e começa por indicar o procedimento, por exemplo: "Segundo o PROC-05, ...".
            - Copia comandos, caminhos e valores EXACTAMENTE como estão escritos.
            - Escreve as instruções no imperativo ("Feche o Outlook", "Abra o regedit"),
              nunca no passado nem na primeira pessoa.
            - NUNCA saltes passos. Se o procedimento tem 5 passos, a resposta tem de
              referir os 5, pela mesma ordem e com o mesmo número.
            - Não mudes a ordem dos passos nem juntes dois num só.
            - Se os procedimentos NÃO respondem à pergunta, diz numa frase que isso não
              está na Knowledgebase e responde na mesma com o teu conhecimento, de forma
              curta e prática, sem inventar que é um procedimento da casa.

            PROCEDIMENTOS:
            $blocos
            $anterior
            TEXTO;
    }

    /**
     * Instruções para quando a pesquisa não encontrou procedimento nenhum: o modelo responde
     * com o conhecimento geral dele (é um assistente de informática), curto e prático. A
     * pessoa vê uma nota a dizer que a resposta não vem da Knowledgebase.
     */
    public function instrucoesGerais(?array $contexto = null): string
    {
        $anterior = filled($contexto['pergunta'] ?? null)
            ? "\n\nCONVERSA ANTERIOR (para dar seguimento, não repitas):\nPergunta: "
                .$contexto['pergunta']."\nResposta: ".mb_substr((string) ($contexto['resposta'] ?? ''), 0, 600)
            : '';

        return <<<TEXTO
            És o assistente técnico de uma empresa de informática (Windows, servidores, redes,
            Microsoft 365, Outlook, impressoras, backups, virtualização). Respondes em
            português de Portugal, de forma curta, directa e prática.

            REGRAS (segue-as sempre):
            - Não há procedimento interno sobre esta pergunta: responde com o teu conhecimento.
            - Dá passos numerados quando a resposta é um procedimento; senão, responde em
              poucas frases.
            - Escreve as instruções no imperativo ("Abra o Painel de Controlo", "Execute").
            - Escreve comandos e caminhos exactos, sem os inventar. Se não tens a certeza,
              diz que não tens a certeza em vez de inventares.
            - Não repitas a pergunta nem faças introduções. Vai directo à resposta.
            - Se a pergunta não é de informática, responde na mesma, com brevidade.$anterior
            TEXTO;
    }
}
