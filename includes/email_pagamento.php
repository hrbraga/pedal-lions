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
    $saudacao = 'Olá, Atleta ' . preg_replace('/\s+/u', ' ', $nome) . '!';
    $logo     = is_file(__DIR__ . '/../assets/img/logo.png') ? realpath(__DIR__ . '/../assets/img/logo.png') : null;
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
    $avatar      = $site . '/avatar.php';
    $grupo       = trim((string) ($ev['contato']['grupo_whatsapp'] ?? ''));
    $whatsapp    = trim((string) ($ev['contato']['whatsapp'] ?? ''));
    $nome_evento = (string) ($ev['nome'] ?? '2º Pedal Solidário');

    // ---------- Texto puro ----------
    $t   = [];
    $t[] = $saudacao;
    $t[] = '';
    $t[] = 'O pagamento da sua inscrição no ' . $nome_evento . ' foi confirmado. Obrigado por pedalar com a gente!';
    $t[] = '';
    foreach ($dados as [$r, $v]) {
        $t[] = $r . ': ' . $v;
    }
    $t[] = '';
    $t[] = 'O EVENTO';
    foreach ($evento as [$r, $v]) {
        $t[] = strpos($v, "\n") !== false ? $r . ":\n  " . str_replace("\n", "\n  ", $v) : $r . ': ' . $v;
    }
    $t[] = '';
    if ($grupo !== '') {
        $t[] = 'Para entrar no grupo do WhatsApp dos participantes, acesse: ' . $grupo;
    }
    $t[] = 'Para fazer seu avatar do evento, acesse: ' . $avatar;
    $t[] = 'Para ver a programação completa, acesse: ' . $programacao;
    $t[] = 'Para ler o regulamento, acesse: ' . $regulamento;
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
    // Botão que funciona nos programas de e-mail (tabela com fundo colorido; o link ocupa o botão todo)
    $botao = function (string $url, string $rotulo, string $cor, string $tinta = '#FFFFFF'): string {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 10px"><tr>'
            . '<td align="center" bgcolor="' . $cor . '" style="background:' . $cor . ';border-radius:6px">'
            . '<a href="' . e($url) . '" target="_blank" style="display:block;padding:13px 16px;color:' . $tinta . ';font-weight:bold;font-size:16px;text-decoration:none">'
            . e($rotulo) . '</a></td></tr></table>';
    };

    $h  = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e('Pagamento confirmado') . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F3F5FA;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F5FA"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:8px;overflow:hidden">'
        . '<tr><td style="background:#0A1E4D;padding:16px 24px">'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
        . ($logo !== null ? '<td style="padding-right:14px;vertical-align:middle"><img src="cid:logo-lions" alt="Lions Clube" width="60" height="57" style="display:block;border:0"></td>' : '')
        . '<td style="vertical-align:middle;color:#FFFFFF;font-size:20px;font-weight:bold">' . e($nome_evento)
        . '<div style="color:#F5B800;font-size:15px;margin-top:4px">Pagamento confirmado</div></td></tr></table></td></tr>'
        . '<tr><td style="padding:24px;color:#142038">'
        . '<p style="margin:0 0 12px">' . e($saudacao) . '</p>'
        . '<p style="margin:0 0 20px">O pagamento da sua inscrição no ' . e($nome_evento) . ' foi confirmado. Obrigado por pedalar com a gente!</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px">' . $linhas($dados) . '</table>'
        . '<h2 style="font-size:17px;color:#0A1E4D;margin:0 0 8px;border-top:1px solid #D9DFEC;padding-top:16px">O evento</h2>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px">' . $linhas($evento) . '</table>'
        . ($grupo !== '' ? $botao($grupo, 'Clique aqui para entrar no grupo do WhatsApp', '#1B7A3E') : '')
        . $botao($avatar, 'Clique aqui para fazer seu avatar', '#F5B800', '#0A1E4D')
        . $botao($programacao, 'Clique aqui para ver a programação completa', '#0A1E4D')
        . $botao($regulamento, 'Clique aqui para ler o regulamento', '#0A1E4D')
        . ($whatsapp !== '' ? '<p style="margin:16px 0 6px;color:#55627D">Dúvidas: WhatsApp ' . e($whatsapp) . '</p>' : '')
        . '<p style="margin:20px 0 0">Até lá!<br><strong>' . e($org) . '</strong></p>'
        . '</td></tr></table></td></tr></table></body></html>';

    return ['assunto' => 'Pagamento confirmado – ' . $nome_evento, 'texto' => implode("\n", $t) . "\n", 'html' => $h,
            'imagens' => $logo !== null ? ['logo-lions' => $logo] : []];
}

/** Endereço do site (https://dominio/pasta) a partir do pedido atual, sem barra no fim. */
function email_site_base(array $server): string
{
    $https  = !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
    $pasta  = str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/')));
    $pasta  = preg_replace('#/admin$#', '', rtrim($pasta, '/'));
    return ($https ? 'https://' : 'http://') . ($server['HTTP_HOST'] ?? 'localhost') . $pasta;
}

/* ---------- Envio pela caixa do domínio (SMTP) ---------- */

require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';

/**
 * Configuração do e-mail (config/email.php, fora do git; modelo em config/email.exemplo.php).
 * null = arquivo ausente ou sem host/usuário.
 */
function email_config(?string $arquivo = null): ?array
{
    $arquivo = $arquivo ?? __DIR__ . '/../config/email.php';
    if (!is_file($arquivo)) {
        return null;
    }
    try {
        $cfg = include $arquivo;
    } catch (\Throwable $e) { // erro de digitação no arquivo: segue como "não configurado" e registra no log
        error_log('Pedal Solidário – config/email.php com erro: ' . $e->getMessage());
        return null;
    }
    if (!is_array($cfg) || trim((string) ($cfg['host'] ?? '')) === '' || trim((string) ($cfg['usuario'] ?? '')) === '') {
        return null;
    }
    return $cfg + ['porta' => 465, 'seguranca' => 'ssl', 'senha' => '', 'nome' => 'Lions Clube de Cornélio Procópio'];
}

/**
 * Envia a mensagem (['assunto','texto','html']). null = enviado; senão o motivo (até 255 caracteres).
 * Nunca lança exceção.
 */
function email_enviar(?array $cfg, string $para, string $nome_para, array $msg): ?string
{
    if ($cfg === null) {
        return 'e-mail não configurado';
    }
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->setLanguage('pt_br', __DIR__ . '/vendor/PHPMailer/language/');
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host     = (string) $cfg['host'];
        $mail->Port     = (int) $cfg['porta'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $cfg['usuario'];
        $mail->Password = (string) $cfg['senha'];
        $seg = strtolower((string) $cfg['seguranca']);
        if ($seg === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($seg === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
        // Desiste em segundos também quando o servidor conecta e trava (o padrão de espera é de minutos)
        $mail->Timeout = 15;
        $mail->getSMTPInstance()->Timelimit = 15;
        $mail->setFrom((string) $cfg['usuario'], (string) $cfg['nome']);
        $mail->addReplyTo((string) $cfg['usuario'], (string) $cfg['nome']);
        $mail->addAddress($para, $nome_para);
        $mail->isHTML(true);
        $mail->Subject = (string) $msg['assunto'];
        $mail->Body    = (string) $msg['html'];
        $mail->AltBody = (string) $msg['texto'];
        // Imagens dentro do e-mail (o HTML as usa por src="cid:...")
        foreach ($msg['imagens'] ?? [] as $cid => $caminho) {
            $mail->addEmbeddedImage($caminho, $cid, basename($caminho));
        }
        $mail->send();
        return null;
    } catch (\Throwable $e) {
        $motivo = trim($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage());
        return mb_substr($motivo !== '' ? $motivo : 'falha no envio', 0, 255);
    }
}

/**
 * Envia o e-mail de pagamento confirmado de uma inscrição e registra o resultado
 * (email_pago_em quando sai; email_pago_erro com o motivo quando falha). null = enviado.
 */
function email_pago_enviar(PDO $pdo, int $id, string $site, ?array $cfg): ?string
{
    $st = $pdo->prepare('SELECT * FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $id]);
    $insc = $st->fetch();
    if (!$insc) {
        return 'inscrição não encontrada';
    }
    if ($insc['status'] !== 'pago') {
        return 'inscrição não está paga';
    }
    $erro = email_enviar($cfg, (string) $insc['email'], (string) $insc['nome'], email_pago_mensagem($insc, evento(), $site));
    if ($erro === null) {
        $pdo->prepare('UPDATE inscricoes SET email_pago_em = NOW(), email_pago_erro = NULL WHERE id = :id')->execute([':id' => $id]);
    } else {
        $pdo->prepare('UPDATE inscricoes SET email_pago_erro = :erro WHERE id = :id')->execute([':erro' => $erro, ':id' => $id]);
    }
    return $erro;
}

/** Primeira frase do motivo da falha, para o aviso do painel (o motivo inteiro fica na inscrição). */
function email_motivo_curto(string $erro): string
{
    $partes = preg_split('/\.\s/u', $erro, 2);
    return rtrim($partes[0], '.');
}

/**
 * E-mail de teste do painel: a mesma mensagem de pagamento confirmado, com dados de exemplo,
 * "[TESTE]" no assunto e um aviso no começo.
 */
function email_teste_mensagem(array $ev, string $site): array
{
    $modelos = camiseta_modelos($ev['camiseta'] ?? []);
    $exemplo = [
        'nome'             => 'Fulano de Tal',
        'codigo'           => 'TESTE00001',
        'categoria'        => (string) ($ev['categorias'][0]['slug'] ?? ''),
        'camiseta_modelo'  => $modelos[0]['slug'] ?? null,
        'camiseta_tamanho' => $modelos ? 'M' : null,
        'valor_total'      => 0,
    ];
    $m = email_pago_mensagem($exemplo, $ev, $site);
    $aviso = 'Este é um e-mail de teste enviado pelo painel. Os dados da inscrição são de exemplo.';
    $m['assunto'] = '[TESTE ' . date('H:i:s') . '] ' . $m['assunto']; // horário: o Gmail não junta os testes numa conversa
    $m['texto']   = $aviso . "\n\n" . $m['texto'];
    $m['html']    = preg_replace('/(<p style="margin:0 0 12px">Olá)/u',
        '<p style="margin:0 0 16px;padding:10px 12px;background:#FFF1C2;color:#6B4E00;border-radius:6px">' . e($aviso) . '</p>$1', $m['html'], 1);
    return $m;
}
