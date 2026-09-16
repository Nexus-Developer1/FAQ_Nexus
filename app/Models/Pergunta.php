<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pergunta feita ao assistente. Fica registada sobretudo pelas que NÃO tiveram resposta:
 * é essa lista que diz que procedimentos faltam escrever (set. 2026).
 */
class Pergunta extends Model
{
    protected $table = 'perguntas';

    protected $fillable = ['pergunta', 'user_id', 'nome', 'procedimentos', 'encontrou'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['procedimentos' => 'array', 'encontrou' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  list<int>  $procedimentos */
    public static function registar(string $pergunta, ?User $utilizador, array $procedimentos): void
    {
        // Nunca rebentar a resposta por causa do registo.
        try {
            static::create([
                'pergunta' => mb_substr($pergunta, 0, 500),
                'user_id' => $utilizador?->id,
                'nome' => $utilizador?->name,
                'procedimentos' => $procedimentos,
                'encontrou' => $procedimentos !== [],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
