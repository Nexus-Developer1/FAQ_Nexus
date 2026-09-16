/* Assistente da Knowledgebase (set. 2026).
   A resposta chega palavra a palavra (o servidor escreve ~7 por segundo): mostra-se logo
   o que vai chegando, para não parecer que a página bloqueou. */
(function () {
  'use strict';

  var caixa = document.querySelector('[data-assistente]');
  if (!caixa) return;

  var form = caixa.querySelector('form');
  var campo = caixa.querySelector('[data-assistente-pergunta]');
  var painel = caixa.querySelector('[data-assistente-resposta]');
  var texto = caixa.querySelector('[data-assistente-texto]');
  var fontes = caixa.querySelector('[data-assistente-fontes]');
  var botao = caixa.querySelector('[data-assistente-enviar]');
  var estado = caixa.querySelector('[data-assistente-estado]');
  var aCorrer = null;

  function limpar() {
    texto.textContent = '';
    fontes.innerHTML = '';
    painel.hidden = false;
  }

  function ocupado(sim) {
    botao.disabled = sim;
    campo.readOnly = sim;
    caixa.classList.toggle('assistente--ocupado', sim);
    estado.textContent = sim ? 'A procurar nos procedimentos…' : '';
  }

  function mostrarFontes(lista) {
    if (!lista || !lista.length) return;
    var titulo = document.createElement('span');
    titulo.className = 'assistente__fontes-titulo';
    titulo.textContent = lista.length === 1 ? 'Procedimento usado:' : 'Procedimentos usados:';
    fontes.appendChild(titulo);
    lista.forEach(function (p) {
      var a = document.createElement('a');
      a.className = 'assistente__fonte';
      a.href = '#proc-' + p.ancora;
      a.textContent = p.ref + ' · ' + p.titulo;
      a.addEventListener('click', function () {
        var alvo = document.getElementById('proc-' + p.ancora);
        if (alvo && alvo.tagName === 'DETAILS') alvo.open = true;
      });
      fontes.appendChild(a);
    });
  }

  function erro(msg) {
    var p = document.createElement('p');
    p.className = 'assistente__erro';
    p.textContent = msg;
    texto.appendChild(p);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var pergunta = campo.value.trim();
    if (pergunta.length < 5) {
      campo.focus();
      return;
    }
    if (aCorrer) aCorrer.abort();
    aCorrer = new AbortController();

    limpar();
    ocupado(true);

    fetch(form.action, {
      method: 'POST',
      signal: aCorrer.signal,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/x-ndjson',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ pergunta: pergunta })
    })
      .then(function (r) {
        if (!r.ok || !r.body) throw new Error('resposta inválida');
        var leitor = r.body.getReader();
        var descodificador = new TextDecoder();
        var sobra = '';
        var jaEscreveu = false;

        function processar(linha) {
          if (!linha.trim()) return;
          var d;
          try { d = JSON.parse(linha); } catch (_) { return; }
          if (d.procedimentos) mostrarFontes(d.procedimentos);
          if (d.texto) {
            if (!jaEscreveu) { estado.textContent = ''; jaEscreveu = true; }
            texto.textContent += d.texto;
          }
          if (d.erro) erro(d.erro);
        }

        function ler() {
          return leitor.read().then(function (res) {
            if (res.done) {
              processar(sobra);
              ocupado(false);
              return;
            }
            sobra += descodificador.decode(res.value, { stream: true });
            var linhas = sobra.split('\n');
            sobra = linhas.pop();
            linhas.forEach(processar);
            return ler();
          });
        }

        return ler();
      })
      .catch(function (e) {
        if (e.name === 'AbortError') return;
        erro('Não foi possível obter resposta. Use a pesquisa em cima.');
        ocupado(false);
      });
  });
})();
