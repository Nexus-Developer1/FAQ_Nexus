/* Assistente da Knowledgebase (set. 2026) — bolha no canto que abre um mini-chat.
   A resposta chega palavra a palavra: o servidor escreve ~7 por segundo, por isso
   mostra-se o que vai chegando em vez de deixar a página parada. */
(function () {
  'use strict';

  var raiz = document.querySelector('[data-assistente]');
  if (!raiz) return;

  var bolha = raiz.querySelector('[data-assistente-abrir]');
  var painel = raiz.querySelector('#assistente-painel');
  var fechar = raiz.querySelector('[data-assistente-fechar]');
  var form = raiz.querySelector('form');
  var campo = raiz.querySelector('[data-assistente-pergunta]');
  var enviar = raiz.querySelector('[data-assistente-enviar]');
  var conversa = raiz.querySelector('[data-assistente-conversa]');
  var aCorrer = null;
  var ultima = null; // pergunta + resposta anteriores, para dar seguimento

  /* ---------- abrir / fechar ---------- */

  function abrir(sim) {
    raiz.classList.toggle('assistente--aberto', sim);
    painel.hidden = !sim;
    bolha.setAttribute('aria-expanded', sim ? 'true' : 'false');
    if (sim) {
      campo.focus();
      ajustar();
      abaixo();
      try { localStorage.setItem('kb-assistente-aberto', '1'); } catch (e) {}
    } else {
      try { localStorage.removeItem('kb-assistente-aberto'); } catch (e) {}
    }
  }

  bolha.addEventListener('click', function () { abrir(painel.hidden); });
  fechar.addEventListener('click', function () { abrir(false); bolha.focus(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !painel.hidden) { abrir(false); bolha.focus(); }
  });

  // Se ficou aberto, reabre ao mudar de página (a conversa não se guarda).
  try { if (localStorage.getItem('kb-assistente-aberto')) abrir(true); } catch (e) {}

  /* ---------- mensagens ---------- */

  function abaixo() { conversa.scrollTop = conversa.scrollHeight; }

  function mensagem(tipo, texto) {
    var div = document.createElement('div');
    div.className = 'assistente__msg assistente__msg--' + tipo;
    var p = document.createElement('p');
    p.textContent = texto || '';
    div.appendChild(p);
    conversa.appendChild(div);
    abaixo();
    return div;
  }

  function estado(div, texto) {
    var s = div.querySelector('.assistente__estado');
    if (!s) {
      s = document.createElement('span');
      s.className = 'assistente__estado';
      div.appendChild(s);
    }
    s.textContent = texto;
    abaixo();
  }

  function tiraEstado(div) {
    var s = div.querySelector('.assistente__estado');
    if (s) s.remove();
  }

  function fontes(div, lista) {
    if (!lista || !lista.length) return;
    var caixa = document.createElement('div');
    caixa.className = 'assistente__fontes';
    lista.forEach(function (p) {
      var a = document.createElement('a');
      a.className = 'assistente__fonte';
      a.href = '/#proc-' + p.ancora;
      a.textContent = p.ref + ' · ' + p.titulo;
      a.addEventListener('click', function () {
        var alvo = document.getElementById('proc-' + p.ancora);
        if (alvo) {
          if (alvo.tagName === 'DETAILS') alvo.open = true;
          if (window.innerWidth <= 640) abrir(false);
        }
      });
      caixa.appendChild(a);
    });
    div.appendChild(caixa);
    abaixo();
  }

  // Aviso por cima da resposta quando não vem de nenhum procedimento (conhecimento geral).
  function nota(div, texto) {
    var n = document.createElement('div');
    n.className = 'assistente__nota';
    n.textContent = texto;
    div.insertBefore(n, div.firstChild);
    abaixo();
  }

  /* ---------- perguntar ---------- */

  function ocupado(sim) {
    enviar.disabled = sim;
    campo.readOnly = sim;
  }

  function perguntar(pergunta) {
    if (aCorrer) aCorrer.abort();
    aCorrer = new AbortController();

    // Tira as sugestões depois da primeira pergunta.
    var sugestoes = conversa.querySelector('.assistente__sugestoes');
    if (sugestoes) sugestoes.remove();

    mensagem('eu', pergunta);
    var resposta = mensagem('bot', '');
    resposta.classList.add('assistente__msg--escrever');
    estado(resposta, 'A procurar nos procedimentos…');
    ocupado(true);

    var texto = '';

    fetch(form.action, {
      method: 'POST',
      signal: aCorrer.signal,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/x-ndjson',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ pergunta: pergunta, contexto: ultima })
    })
      .then(function (r) {
        if (!r.ok || !r.body) throw new Error('resposta inválida');
        var leitor = r.body.getReader();
        var dec = new TextDecoder();
        var sobra = '';

        function linha(l) {
          if (!l.trim()) return;
          var d;
          try { d = JSON.parse(l); } catch (_) { return; }

          if (d.procedimentos) {
            fontes(resposta, d.procedimentos);
            if (d.nota) nota(resposta, d.nota);
            estado(resposta, 'A escrever a resposta…');
          }
          if (d.texto) {
            tiraEstado(resposta);
            texto += d.texto;
            resposta.querySelector('p').textContent = texto;
            abaixo();
          }
          if (d.erro) {
            tiraEstado(resposta);
            resposta.classList.add('assistente__msg--erro');
            resposta.querySelector('p').textContent = d.erro;
          }
        }

        function ler() {
          return leitor.read().then(function (res) {
            if (res.done) { linha(sobra); return; }
            sobra += dec.decode(res.value, { stream: true });
            var partes = sobra.split('\n');
            sobra = partes.pop();
            partes.forEach(linha);
            return ler();
          });
        }

        return ler();
      })
      .then(function () {
        if (texto) ultima = { pergunta: pergunta, resposta: texto.slice(0, 600) };
      })
      .catch(function (e) {
        if (e.name === 'AbortError') return;
        tiraEstado(resposta);
        resposta.classList.add('assistente__msg--erro');
        resposta.querySelector('p').textContent = 'Não foi possível obter resposta. Tente a pesquisa da página.';
      })
      .then(function () {
        resposta.classList.remove('assistente__msg--escrever');
        ocupado(false);
        campo.focus();
      });
  }

  /* ---------- caixa de escrita ---------- */

  // Começa com uma linha e cresce com o texto, até ao tecto do CSS (5,5rem). O +2 é a
  // borda: o scrollHeight só conta o conteúdo e o enchimento.
  // (o valor vive aqui dentro: o reabrir automático corre antes das variáveis do fim
  // do ficheiro estarem atribuídas)
  function ajustar() {
    // Com o painel fechado o textarea não tem medidas (scrollHeight = 0) e o acerto daria
    // uma caixa de 2px. Nesse caso deixa-se a altura do CSS e mede-se quando abrir.
    if (!campo.offsetParent) {
      campo.style.height = '';

      return;
    }

    campo.style.height = 'auto';
    campo.style.height = Math.min(campo.scrollHeight + 2, 88) + 'px';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var p = campo.value.trim();
    if (p.length < 5) { campo.focus(); return; }
    campo.value = '';
    ajustar();
    perguntar(p);
  });

  // Enter envia; Shift+Enter muda de linha.
  campo.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  campo.addEventListener('input', ajustar);
  ajustar();

  // Colar texto com o rato não dispara 'input' em todos os browsers antigos.
  campo.addEventListener('paste', function () { setTimeout(ajustar, 0); });

  raiz.addEventListener('click', function (e) {
    var s = e.target.closest('[data-assistente-sugestao]');
    if (s) perguntar(s.textContent.trim());
  });
})();
