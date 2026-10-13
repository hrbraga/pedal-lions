<?php
/**
 * Base do painel administrativo: sessão segura, autenticação e utilitários.
 */
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('pedal_admin');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

const ADMIN_INATIVIDADE = 7200; // 2 horas sem uso encerram a sessão

const STATUS_ROTULOS = [
    'pendente'  => 'Pendente',
    'pago'      => 'Pago',
    'cancelado' => 'Cancelado',
];

function admin_logado(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    if (time() - (int) ($_SESSION['admin_ultimo'] ?? 0) > ADMIN_INATIVIDADE) {
        admin_sair();
        return false;
    }
    $_SESSION['admin_ultimo'] = time();
    return true;
}

function admin_exigir(): void
{
    if (!admin_logado()) {
        header('Location: login.php');
        exit;
    }
}

function admin_sair(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $mensagem];
}

/* ---------- Proteção contra tentativas de senha (por IP) ---------- */

function tentativas_garantir_tabela(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS admin_tentativas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip (ip, criado_em)
    ) ENGINE=InnoDB');
}

function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_bloqueado(): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM admin_tentativas WHERE ip = :ip AND criado_em > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([':ip' => ip_cliente()]);
    return (int) $st->fetchColumn() >= 8;
}

function login_registrar_falha(): void
{
    $pdo = db();
    $pdo->prepare('INSERT INTO admin_tentativas (ip) VALUES (:ip)')->execute([':ip' => ip_cliente()]);
    $pdo->exec('DELETE FROM admin_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)');
}

function login_limpar_falhas(): void
{
    db()->prepare('DELETE FROM admin_tentativas WHERE ip = :ip')->execute([':ip' => ip_cliente()]);
}

/* ---------- Formatação ---------- */

function telefone_formatado(string $tel): string
{
    $d = so_digitos($tel);
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    }
    return $tel;
}

function data_hora_br(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date('d/m/Y H:i', $t) : $dt;
}

function idade_no_evento(string $nascimento): int
{
    $nasc   = new DateTime($nascimento);
    $evento = new DateTime(evento()['data_iso']);
    return (int) $nasc->diff($evento)->y;
}

/** Só aceita voltar para páginas do próprio painel (evita redirecionamento aberto). */
function destino_seguro(string $destino, string $padrao = 'painel.php'): string
{
    return preg_match('/^(painel|inscrito)\.php(\?[A-Za-z0-9_=&%.+\-]*)?$/', $destino) ? $destino : $padrao;
}
