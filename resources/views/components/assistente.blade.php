{{-- Assistente da Knowledgebase (set. 2026): bolha no canto, abre um mini-chat.
     Aparece em todas as páginas de quem tem sessão. O modelo corre no próprio servidor
     e só lê os procedimentos que esta pessoa pode ver. --}}
@auth
@if(config('assistente.ativo'))
<div class="assistente no-print" data-assistente>

    <button type="button" class="assistente__bolha" data-assistente-abrir
            aria-expanded="false" aria-controls="assistente-painel"
            title="Perguntar à Knowledgebase">
        <svg class="assistente__icone-abrir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"/>
        </svg>
        <svg class="assistente__icone-fechar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <path d="M18 6 6 18M6 6l12 12"/>
        </svg>
        <span class="visually-hidden">Perguntar à Knowledgebase</span>
    </button>

    <section class="assistente__painel" id="assistente-painel" hidden
             aria-label="Assistente da Knowledgebase">

        <header class="assistente__topo">
            <div>
                <strong>Assistente</strong>
                <span>procedimentos da casa e conhecimento geral</span>
            </div>
            <button type="button" class="assistente__fechar" data-assistente-fechar aria-label="Fechar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="assistente__conversa" data-assistente-conversa role="log" aria-live="polite">
            <div class="assistente__msg assistente__msg--bot">
                <p>Olá. Pergunte pelas suas palavras — procuro primeiro nos procedimentos da casa e, se não houver nenhum, respondo com o que sei.</p>
                <div class="assistente__sugestoes">
                    <button type="button" data-assistente-sugestao>O Outlook pede a palavra-passe sempre, o que faço?</button>
                    <button type="button" data-assistente-sugestao>O servidor está a fazer muito barulho, dá para baixar?</button>
                    <button type="button" data-assistente-sugestao>Como transfiro as funções FSMO para um DC novo?</button>
                    <button type="button" data-assistente-sugestao>Como vejo o IP de um PC com Windows?</button>
                </div>
            </div>
        </div>

        <form class="assistente__form" method="post" action="{{ route('assistente.perguntar') }}">
            @csrf
            <label class="visually-hidden" for="assistente-pergunta">A sua pergunta</label>
            <textarea id="assistente-pergunta" name="pergunta" rows="1" maxlength="500"
                      placeholder="Escreva a sua dúvida…" autocomplete="off"
                      data-assistente-pergunta></textarea>
            <button type="submit" class="assistente__enviar" data-assistente-enviar aria-label="Enviar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            </button>
        </form>
    </section>
</div>
@endif
@endauth
