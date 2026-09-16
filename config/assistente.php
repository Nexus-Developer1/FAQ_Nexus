<?php

// Assistente da Knowledgebase: responde a perguntas em linguagem normal, SÓ com base nos
// procedimentos que a pessoa pode ver. O modelo corre no próprio servidor (Ollama), por isso
// nada sai para fora e não há custo por pergunta.
return [
    'ativo' => env('ASSISTENTE_ATIVO', true),

    // Ollama local. Nunca deve ser um endereço externo: os procedimentos vão no pedido.
    'url' => env('ASSISTENTE_URL', 'http://127.0.0.1:11434'),

    // qwen3.5:2b — medido no servidor (set. 2026): respostas certas, ~7 palavras/s.
    // O 0.8b é mais rápido mas salta passos e inventa; o 4b é igual mas o dobro do tempo.
    'modelo' => env('ASSISTENTE_MODELO', 'qwen3.5:2b'),

    // Quantos procedimentos entram no pedido, no máximo. Neste servidor cada procedimento
    // a mais custa ~15s só de leitura, por isso o serviço manda normalmente UM — os outros
    // só entram quando a pesquisa fica indecisa entre vários.
    'max_procedimentos' => (int) env('ASSISTENTE_MAX_PROCEDIMENTOS', 2),

    // Tecto de palavras da resposta e tempo máximo de espera (segundos).
    'max_palavras' => (int) env('ASSISTENTE_MAX_PALAVRAS', 400),
    'timeout' => (int) env('ASSISTENTE_TIMEOUT', 180),

    // Uma pergunta de cada vez no servidor: gerar ocupa os 4 núcleos durante ~20s e não
    // pode atrasar o resto. Quem chegar durante esse tempo espera até `espera_lock`.
    'espera_lock' => (int) env('ASSISTENTE_ESPERA_LOCK', 25),

    // Perguntas por minuto, por pessoa.
    'limite_por_minuto' => (int) env('ASSISTENTE_LIMITE', 6),
];
