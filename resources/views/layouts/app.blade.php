<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0A2A18">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    {{-- O sistema de desenho partilhado por toda a suite, e depois o que é próprio daqui. --}}
    <link rel="stylesheet" href="{{ asset('css/suite.css') }}?v={{ filemtime(public_path('css/suite.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="icon" href="{{ asset('img/icon-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
</head>
<body class="@yield('body-class')" @yield('body-attrs')>
<a class="skip-link" href="#conteudo">Saltar para o conteúdo</a>

@php
    $pessoa = auth()->user();
    $iniciais = $pessoa
        ? \Illuminate\Support\Str::of($pessoa->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')
        : '–';
@endphp

<div class="app">
    <button class="lateral__veu no-print" data-fechar-lateral hidden aria-label="Fechar menu"></button>

    <aside class="lateral no-print" data-lateral>
        <div class="lateral__topo">
            <div class="lateral__marca">
                <img src="{{ asset('img/nexus-1.png') }}" alt="Nexus" width="150" height="28">
                <span>Knowledgebase</span>
            </div>
            <button class="lateral__fechar" data-fechar-lateral aria-label="Fechar menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="lateral__nav" aria-label="Navegação principal">
            <a class="nav-item {{ request()->routeIs('consulta') ? 'nav-item--activo' : '' }}" href="{{ route('consulta') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <span>Consultar</span>
            </a>

            @auth
                @can('editar')
                    <a class="nav-item {{ request()->routeIs('gerir.procedimentos.*') ? 'nav-item--activo' : '' }}" href="{{ route('gerir.procedimentos.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Procedimentos</span>
                    </a>
                @endcan
                @can('admin')
                    <a class="nav-item {{ request()->routeIs('gerir.categorias.*') ? 'nav-item--activo' : '' }}" href="{{ route('gerir.categorias.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        <span>Categorias</span>
                    </a>
                    <a class="nav-item {{ request()->routeIs('gerir.regras.*') ? 'nav-item--activo' : '' }}" href="{{ route('gerir.regras.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                        <span>Regras de segurança</span>
                    </a>
                @endcan
            @endauth
        </nav>

        @auth
            @can('editar')
                <a href="{{ route('gerir.procedimentos.create') }}" class="botao-primario">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14m-7-7h14"/></svg>
                    Novo procedimento
                </a>
            @endcan

            <div class="lateral__pessoa">
                <span class="lateral__avatar">{{ $iniciais }}</span>
                <span class="lateral__quem">
                    <span class="lateral__nome">{{ $pessoa->name }}</span>
                    <span class="lateral__papel">{{ $pessoa->email }}</span>
                </span>
                {{-- Sair daqui é voltar à escolha de módulos, não terminar a sessão.
                     A sessão termina no portal, que é onde ela começa. --}}
                <a href="{{ rtrim(config('app.portal_url'), '/') }}/" class="lateral__sair"
                   title="Voltar aos módulos" aria-label="Voltar aos módulos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 16l-4-4m0 0l4-4m-4 4h14M9 4H7a3 3 0 00-3 3v10a3 3 0 003 3h2"/></svg>
                </a>
            </div>
        @endauth
    </aside>

    <div class="trabalho">
        <header class="topo-movel no-print">
            <button class="topo-movel__menu" data-abrir-lateral aria-label="Abrir menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <img src="{{ asset('img/nexus-1.png') }}" alt="Nexus" width="150" height="28">
        </header>

        <header class="topo no-print">
            <nav class="topo__caminho" aria-label="Onde estou">
                <a href="{{ config('app.portal_url') }}">Início</a>
                <span class="topo__sep">/</span>
                @hasSection('caminho')
                    <a href="{{ route('consulta') }}">Knowledgebase</a>
                    <span class="topo__sep">/</span>
                    @yield('caminho')
                @else
                    <span class="actual">Knowledgebase</span>
                @endif
            </nav>
            <div class="topo__accoes">@yield('accoes')</div>
        </header>

        <main id="conteudo" class="principal" tabindex="-1">
            <div class="largura @yield('largura')">
                @yield('hero')

                @if(session('status'))
                    <div class="alerta alerta--ok no-print" role="status">
                        <svg class="alerta__icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>
                        <div>{{ session('status') }}</div>
                    </div>
                @endif

                @if($errors->any() && ! View::hasSection('sem-resumo-erros'))
                    <div class="alerta alerta--erro no-print" role="alert">
                        <svg class="alerta__icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                        <div>
                            <strong>Não foi possível concluir. Verifique:</strong>
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</div>

<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
<script src="{{ asset('js/assistente.js') }}?v={{ filemtime(public_path('js/assistente.js')) }}" defer></script>
@include('components.assistente')

</body>
</html>
