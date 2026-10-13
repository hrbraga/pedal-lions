<?php
/**
 * Funções auxiliares usadas em todas as páginas.
 */

date_default_timezone_set('America/Sao_Paulo');

/** Carrega (uma única vez) os dados do evento. */
function evento(): array
{
    static $dados = null;
    if ($dados === null) {
        $dados = require __DIR__ . '/../config/evento.php';
    }
    return $dados;
}

/** Escapa texto para exibir com segurança em HTML. */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formata um número como moeda brasileira. */
function moeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/** Menor valor de inscrição entre as categorias. */
function menor_valor(): float
{
    $valores = array_column(evento()['categorias'], 'valor');
    return $valores ? min($valores) : 0.0;
}

/** Retorna o caminho de uma imagem opcional se o arquivo existir. */
function imagem_opcional(string $arquivo): ?string
{
    $caminho = __DIR__ . '/../assets/img/' . $arquivo;
    return file_exists($caminho) ? 'assets/img/' . $arquivo : null;
}

/** Converte AAAA-MM-DD em DD/MM/AAAA. */
function data_br(string $iso): string
{
    $d = DateTime::createFromFormat('Y-m-d', $iso);
    return $d ? $d->format('d/m/Y') : $iso;
}

/** A compra da camiseta está aberta? (vale até o fim do dia do prazo) */
function camiseta_aberta(): bool
{
    $prazo = evento()['camiseta']['prazo_iso'] ?? '';
    return $prazo !== '' && date('Y-m-d') <= $prazo;
}
