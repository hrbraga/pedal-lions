<?php
/**
 * E-mail "Pagamento confirmado" para o atleta: montagem da mensagem (sem banco nem rede,
 * testada em tests/email_pagamento_test.php).
 */
require_once __DIR__ . '/funcoes.php';

/**
 * Mensagem de pagamento confirmado.
 * $insc: linha de inscricoes; $ev: evento(); $site: endereço do site sem barra no fim.
 * Retorna ['assunto' => ..., 'texto' => ..., 'html' => ...].
 */
function email_pago_mensagem(array $insc, array $ev, string $site): array
{
    $nome     = trim((string) ($insc['nome'] ?? ''));
    $primeiro = $nome === '' ? '' : preg_split('/\s+/', $nome)[0];
    $org      = (string) ($ev['organizador'] ?? 'Lions Clube de Cornélio Procópio');

    $categoria = (string) ($insc['categoria'] ?? '');
    foreach ($ev['categorias'] ?? [] as $c) {
        if (($c['slug'] ?? null) === $categoria) {
            $categoria = (string) $c['nome'];
            break;
        }
    }

    $camiseta = null;
    if (!empty($insc['camiseta_tamanho'])) {
        $modelo = (string) ($insc['camiseta_modelo'] ?? '');
        foreach (camiseta_modelos($ev['camiseta'] ?? []) as $m) {
            if ($m['slug'] === $modelo) {
                $modelo = $m['nome'];
                break;
            }
        }
        $camiseta = ($modelo !== '' ? $modelo . ', ' : '') . 'tamanho ' . $insc['camiseta_tamanho'];
    }

    // Só as largadas da programação (os horários que interessam a quem pedala)
    $largadas = [];
    foreach ($ev['programacao'] ?? [] as $p) {
        if (stripos((string) ($p['titulo'] ?? ''), 'largada') !== false) {
            $largadas[] = trim(($p['hora'] ?? '') . ' – ' . $p['titulo']);
        }
    }

    $dados = [['Código da inscrição', (string) ($insc['codigo'] ?? '')], ['Categoria', $categoria]];
    if ($camiseta !== null) {
        $dados[] = ['Camiseta', $camiseta];
    }
    $dados[] = ['Valor pago', moeda((float) ($insc['valor_total'] ?? 0))];

    $evento = [['Data', (string) ($ev['data_extenso'] ?? '')], ['Local da largada', (string) ($ev['local_largada'] ?? '')]];
    if ($largadas) {
        $evento[] = ['Horários das largadas', implode("\n", $largadas)];
    }

    $regulamento = $site . '/regulamento.php';
    $programacao = $site . '/programacao.php';
    $grupo       = trim((string) ($ev['contato']['grupo_whatsapp'] ?? ''));
    $whatsapp    = trim((string) ($ev['contato']['whatsapp'] ?? ''));
    $nome_evento = (string) ($ev['nome'] ?? '2º Pedal Solidário');

    // ---------- Texto puro ----------
    $t   = [];
    $t[] = 'Olá, ' . $primeiro . '!';
    $t[] = '';
    $t[] = 'O pagamento da sua inscrição no ' . $nome_evento . ' foi confirmado. Obrigado por pedalar com a gente!';
    $t[] = '';
    foreach ($dados as [$r, $v]) {
        $t[] = $r . ': ' . $v;
    }
    $t[] = '';
    $t[] = 'O EVENTO';
    foreach ($evento as [$r, $v]) {
        $t[] = $r . ': ' . str_replace("\n", "\n  ", strpos($v, "\n") !== false ? "\n" . $v : $v);
    }
    $t[] = 'Programação completa: ' . $programacao;
    $t[] = 'Regulamento: ' . $regulamento;
    if ($grupo !== '') {
        $t[] = 'Grupo do WhatsApp dos participantes: ' . $grupo;
    }
    if ($whatsapp !== '') {
        $t[] = 'Dúvidas: WhatsApp ' . $whatsapp;
    }
    $t[] = '';
    $t[] = 'Até lá!';
    $t[] = $org;

    // ---------- HTML ----------
    $linhas = function (array $pares): string {
        $h = '';
        foreach ($pares as [$r, $v]) {
            $h .= '<tr><td style="padding:6px 12px 6px 0;color:#55627D;vertical-align:top;white-space:nowrap">' . e($r) . '</td>'
                . '<td style="padding:6px 0;color:#142038;font-weight:bold">' . nl2br(e($v), false) . '</td></tr>';
        }
        return $h;
    };
    $link = function (string $url, string $rotulo): string {
        return '<a href="' . e($url) . '" style="color:#0A1E4D;font-weight:bold">' . e($rotulo) . '</a>';
    };

    $h  = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e('Pagamento confirmado') . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F3F5FA;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F5FA"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:8px;overflow:hidden">'
        . '<tr><td style="background:#0A1E4D;padding:20px 24px;color:#FFFFFF;font-size:20px;font-weight:bold">'
        . e($nome_evento) . '<div style="color:#F5B800;font-size:15px;margin-top:4px">Pagamento confirmado</div></td></tr>'
        . '<tr><td style="padding:24px;color:#142038">'
        . '<p style="margin:0 0 12px">Olá, ' . e($primeiro) . '!</p>'
        . '<p style="margin:0 0 20px">O pagamento da sua inscrição no ' . e($nome_evento) . ' foi confirmado. Obrigado por pedalar com a gente!</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px">' . $linhas($dados) . '</table>'
        . '<h2 style="font-size:17px;color:#0A1E4D;margin:0 0 8px;border-top:1px solid #D9DFEC;padding-top:16px">O evento</h2>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px">' . $linhas($evento) . '</table>'
        . '<p style="margin:0 0 6px">' . $link($programacao, 'Programação completa') . ' · ' . $link($regulamento, 'Regulamento') . '</p>'
        . ($grupo !== '' ? '<p style="margin:0 0 6px">' . $link($grupo, 'Entre no grupo do WhatsApp dos participantes') . '</p>' : '')
        . ($whatsapp !== '' ? '<p style="margin:0 0 6px;color:#55627D">Dúvidas: WhatsApp ' . e($whatsapp) . '</p>' : '')
        . '<p style="margin:20px 0 0">Até lá!<br><strong>' . e($org) . '</strong></p>'
        . '</td></tr></table></td></tr></table></body></html>';

    return ['assunto' => 'Pagamento confirmado – ' . $nome_evento, 'texto' => implode("\n", $t) . "\n", 'html' => $h];
}

/** Endereço do site (https://dominio/pasta) a partir do pedido atual, sem barra no fim. */
function email_site_base(array $server): string
{
    $https  = !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
    $pasta  = str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/')));
    $pasta  = preg_replace('#/admin$#', '', rtrim($pasta, '/'));
    return ($https ? 'https://' : 'http://') . ($server['HTTP_HOST'] ?? 'localhost') . $pasta;
}
