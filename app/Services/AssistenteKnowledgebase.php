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
     * Quanto da pergunta tem de aparecer no procedimento para ele ser considerado útil.
     *
     * A pesquisa do Postgres devolve resultados por uma palavra em comum — "como vejo o IP
     * de um PC com Windows" traz um procedimento sobre o menu do Windows. Com um
     * procedimento que não serve à frente, o modelo recusa ("isto não está na
     * Knowledgebase") ou inventa para encaixar a citação. Medido nesta base: perguntas
     * documentadas cobrem 60% a 100% das palavras; não documentadas, 20% a 50%.
     */
    private const COBERTURA_MINIMA = 0.6;

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
        // As palavras que a PESSOA escreveu (sem os sinónimos da casa) servem depois para
        // medir a cobertura; a pesquisa é que leva os sinónimos.
        $palavras = $this->palavrasDaPergunta($pergunta);

        // Seguimento ("e no Office 2016?"): sozinha, a pergunta não tem palavras que
        // cheguem para procurar — junta-se a anterior para não perder o assunto.
        if (count($palavras) < 3 && filled($contexto['pergunta'] ?? null)) {
            $palavras = array_values(array_unique(array_merge($palavras, $this->palavrasDaPergunta($contexto['pergunta']))));
        }

        $termos = $this->comSinonimos($palavras);
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

        $encontrados = Procedure::with(['category', 'steps'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true))
            ->values();

        // O guarda-costas: se o melhor resultado mal toca na pergunta, é como se não houvesse
        // nada. Mais vale o modelo responder com o que sabe (e a pessoa ver o aviso de que
        // não vem da Knowledgebase) do que recusar ou inventar em cima de um procedimento
        // que não serve.
        if ($encontrados->isEmpty() || $this->cobertura($encontrados->first(), $palavras) < self::COBERTURA_MINIMA) {
            return collect();
        }

        return $encontrados;
    }

    /**
     * Que fatia das palavras da pergunta aparece mesmo neste procedimento (0 a 1).
     *
     * Conta os sinónimos da casa: quem escreve "barulho" está coberto pelo procedimento que
     * fala de "ventoinhas". Olha para o título, o problema, os passos e a categoria.
     *
     * @param  list<string>  $palavras
     */
    private function cobertura(Procedure $procedimento, array $palavras): float
    {
        if ($palavras === []) {
            return 0.0;
        }

        $texto = $this->semAcentos(implode(' ', [
            $procedimento->title,
            (string) $procedimento->problem,
            $procedimento->steps->map(fn ($s) => $s->content)->implode(' '),
            (string) ($procedimento->category->name ?? ''),
        ]));

        $cobertas = 0;
        foreach ($palavras as $palavra) {
            foreach ([$palavra, ...(self::SINONIMOS[$palavra] ?? [])] as $variante) {
                if (str_contains($texto, $variante)) {
                    $cobertas++;
                    break;
                }
            }
        }

        return $cobertas / count($palavras);
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
        return $this->comSinonimos($this->palavrasDaPergunta($pergunta));
    }

    /** Acrescenta às palavras da pergunta os sinónimos da casa (o que vai para a pesquisa). */
    /**
     * @param  list<string>  $palavras
     * @return list<string>
     */
    private function comSinonimos(array $palavras): array
    {
        $termos = [];
        foreach ($palavras as $palavra) {
            $termos[$palavra] = $palavra;
            foreach (self::SINONIMOS[$palavra] ?? [] as $sinonimo) {
                $termos[$sinonimo] = $sinonimo;
            }
        }

        return array_values(array_slice($termos, 0, 18));
    }

    /** Tira os acentos e põe em minúsculas — a pesquisa e a cobertura comparam assim. */
    private function semAcentos(string $texto): string
    {
        return strtr(mb_strtolower($texto), [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);
    }

    /**
     * Só as palavras que a pessoa escreveu: sem acentos, sem as vazias do português e sem as
     * de uma ou duas letras. Sem sinónimos — é sobre estas que se mede a cobertura.
     *
     * @return list<string>
     */
    private function palavrasDaPergunta(string $pergunta): array
    {
        $vazias = ['que', 'qual', 'quais', 'como', 'para', 'por', 'com', 'sem', 'dos', 'das',
            'nos', 'nas', 'uma', 'uns', 'umas', 'este', 'esta', 'isto', 'esse', 'essa', 'isso',
            'aqui', 'ali', 'nao', 'sim', 'faco', 'fazer', 'tenho', 'quero', 'preciso', 'pode',
            'posso', 'devo', 'esta', 'estao', 'ser', 'sao', 'tem', 'meu', 'minha', 'seu', 'sua',
            'mais', 'menos', 'muito', 'pouco', 'sempre', 'nunca', 'depois', 'antes', 'entao'];

        $palavras = preg_split('/[^a-z0-9]+/u', $this->semAcentos($pergunta), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $limpas = [];
        foreach ($palavras as $palavra) {
            if (mb_strlen($palavra) < 3 || in_array($palavra, $vazias, true)) {
                continue;
            }
            $limpas[$palavra] = $palavra; // sem repetidos
        }

        return array_values(array_slice($limpas, 0, 12));
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
