@extends('layouts.app')
@section('title', 'Consulta')

@section('caminho')
    <span class="actual">Consulta</span>
@endsection

@section('hero')
{{-- Sem cabeçalho à vista: a barra de topo e a barra lateral já dizem onde se
     está, e a pesquisa fica logo no cimo. O h1 fica, escondido, porque uma
     página sem cabeçalho principal deixa quem usa leitor de ecrã sem saber o
     que está a ler. --}}
<h1 class="visually-hidden">Procedimentos técnicos</h1>
@endsection

@section('content')
<div data-consulta class="sobreposto">

    @if($semArea)
        {{-- Há procedimentos, mas nenhum da área desta pessoa — e a área ainda
             não lhe foi atribuída. Antes dizia-se "ainda não há procedimentos",
             o que a punha à procura de um problema que não é dela. --}}
        <div class="vazio">
            <div class="vazio__icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <h2>A sua conta ainda não tem área.</h2>
            <p>
                Os procedimentos estão separados por área, e a sua ainda não foi
                definida &mdash; por isso não aparece nada. Peça a um administrador
                da Knowledgebase que a defina.
            </p>
        </div>
    @elseif(! $hasAny)
        <div class="vazio">
            <div class="vazio__icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M10 12h4M10 16h4M10 8h4"/></svg>
            </div>
            <h2>Ainda não há procedimentos.</h2>
            @can('editar')
                <div class="accoes"><a class="btn btn--primario" href="{{ route('gerir.procedimentos.create') }}">Criar o primeiro</a></div>
            @endcan
        </div>
    @else
        <form class="filtros no-print" method="get" action="{{ route('consulta') }}" role="search" aria-label="Filtrar procedimentos">
            <div class="campo">
                <label for="q">Pesquisar</label>
                <div class="pesquisa">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Sintoma, equipamento, título, passo…" autocomplete="off">
                </div>
            </div>
            <div class="campo">
                <label for="categoria">Categoria</label>
                <select id="categoria" name="categoria">
                    <option value="">Todas</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($filters['categoria'] === $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filtros__accoes">
                <button type="submit" class="btn btn--escuro">Filtrar</button>
                <a href="{{ route('consulta') }}" class="btn btn--secundario" data-limpar>Limpar</a>
            </div>
        </form>

        @if($rules->isNotEmpty())
            <section class="regras" aria-labelledby="regras-titulo">
                <div class="regras__icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div>
                    <h2 id="regras-titulo">Regras de segurança</h2>
                    <ol>
                        @foreach($rules as $rule)
                            <li>{{ $rule->content }}</li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif

        @php
            $qs = http_build_query(array_filter(['q' => $filters['q'], 'categoria' => $filters['categoria']]));
        @endphp

        <div class="resumo-lista no-print">
            <span class="contagem" data-contagem aria-live="polite">
                @if($procedures->isEmpty())
                    Nenhum procedimento corresponde aos filtros.
                @else
                    {{ $procedures->count() }} {{ $procedures->count() === 1 ? 'procedimento' : 'procedimentos' }}
                @endif
            </span>
            <div class="accoes">
                <button type="button" class="btn btn--secundario btn--pequeno" data-expandir>Expandir todos</button>
                <button type="button" class="btn btn--secundario btn--pequeno" data-recolher>Recolher todos</button>
                <a class="btn btn--secundario btn--pequeno" data-imprimir-todos data-base="{{ route('imprimir') }}"
                   href="{{ route('imprimir') }}{{ $qs ? '?'.$qs : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></svg>
                    Imprimir lista
                </a>
            </div>
        </div>

        <div class="vazio" data-sem-resultados @if($procedures->isNotEmpty()) hidden @endif>
            <div class="vazio__icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8.5 11h5"/></svg>
            </div>
            <h2>Sem resultados</h2>
            <p><a href="{{ route('consulta') }}">Limpar filtros</a></p>
        </div>

        @foreach($procedures as $p)
            <details class="proc" id="proc-{{ $p->reference_number }}"
                     data-categoria="{{ $p->category_id }}"
                     data-texto="{{ $p->reference }} {{ $p->title }} {{ $p->problem }} {{ $p->category->name }} {{ $p->steps->pluck('content')->implode(' ') }} {{ $p->ticket_notes }} {{ $p->escalation }}">
                <summary>
                    <svg class="proc__seta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    <span class="proc__titulo">{{ $p->title }}</span>
                    <span class="proc__tags">
                        <span class="etiqueta etiqueta--ref">{{ $p->reference }}</span>
                        <span class="etiqueta">{{ $p->category->name }}</span>
                    </span>
                </summary>
                <div class="proc__corpo">
                    @php $temLateral = filled($p->ticket_notes) || filled($p->escalation); @endphp
                    <div class="proc__grelha @unless($temLateral) proc__grelha--simples @endunless">
                        <div>
                            @if(filled($p->problem))
                                <h3>Problema / sintomas</h3>
                                <p class="proc__problema">{{ $p->problem }}</p>
                            @endif

                            <h3>Solução — passos</h3>
                            @if($p->steps->isEmpty())
                                <p class="meta">Este procedimento ainda não tem passos.</p>
                            @else
                                <ol class="proc__passos">
                                    @foreach($p->steps as $step)
                                        <li>{{ $step->content }}</li>
                                    @endforeach
                                </ol>
                            @endif

                            @if($p->anexos->isNotEmpty())
                                <h3>Anexos</h3>
                                <ul class="anexos anexos--consulta">
                                    @foreach($p->anexos as $anexo)
                                        <li class="anexo">
                                            {{-- Imagens abrem aqui mesmo, numa camada por cima da página.
                                                 Sem JavaScript, o link continua a levar à imagem. --}}
                                            <a class="anexo__ver" href="{{ route('anexo', [$p, $anexo]) }}"
                                               @if($anexo->ehImagem())
                                                   data-ampliar data-legenda="{{ $anexo->rotulo }}"
                                                   title="Ver «{{ $anexo->rotulo }}» em tamanho real"
                                               @else
                                                   target="_blank" rel="noopener"
                                                   title="Abrir «{{ $anexo->rotulo }}»"
                                               @endif>
                                                @if($anexo->ehImagem())
                                                    <img src="{{ route('anexo', [$p, $anexo]) }}" alt="{{ $anexo->rotulo }}" loading="lazy">
                                                @else
                                                    <span class="anexo__pdf" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                        PDF
                                                    </span>
                                                @endif
                                            </a>
                                            <div class="anexo__info">
                                                <span class="anexo__nome">{{ $anexo->rotulo }}</span>
                                                <span class="anexo__meta">{{ $anexo->tamanho_legivel }}</span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        @if($temLateral)
                        <aside class="proc__lateral">
                            @if(filled($p->ticket_notes))
                                <div class="caixa-info caixa-info--ticket">
                                    <h3>O que registar no ticket</h3>
                                    <p>{{ $p->ticket_notes }}</p>
                                </div>
                            @endif
                            @if(filled($p->escalation))
                                <div class="caixa-info caixa-info--escalar">
                                    <h3>Quando escalar</h3>
                                    <p>{{ $p->escalation }}</p>
                                </div>
                            @endif
                        </aside>
                        @endif
                    </div>

                    <div class="proc__rodape">
                        <span class="meta">Actualizado a {{ $p->updated_at->format('d/m/Y') }}@if($p->updated_by) · {{ $p->updated_by }}@endif</span>
                        <span class="accoes no-print">
                            <a class="btn btn--secundario btn--pequeno" href="{{ route('imprimir.um', $p) }}">Imprimir</a>
                            @can('editar')
                                <a class="btn btn--secundario btn--pequeno" href="{{ route('gerir.procedimentos.edit', $p) }}">Editar</a>
                            @endcan
                        </span>
                    </div>
                </div>
            </details>
        @endforeach
    @endif
</div>
@endsection
