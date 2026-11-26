<?php
/**
 * Dashboard: resumo do evento (inscrições, perfil, camisetas) e, para quem tem a permissão Financeiro, o resumo financeiro.
 * Gráficos em HTML/CSS (sem biblioteca externa): barras finas, azul = pagas, laranja = pendentes, dica ao passar o mouse
 * ou focar, e "ver em tabela" em cada um. Contas em includes/dashboard.php e includes/financeiro.php.
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_once __DIR__ . '/../includes/financeiro.php';
admin_exigir('dashboard');

$ev = evento();
$r = null;
$b = null;
$erro = '';
try {
    $pdo = db();
    $r = dash_resumo(dash_inscricoes($pdo), $ev['categorias'] ?? [], camiseta_modelos($ev['camiseta'] ?? []),
        $ev['camiseta']['tamanhos'] ?? [], (string) $ev['data_iso'], (int) ($ev['inscricoes_vagas'] ?? 0));
    if (admin_pode('financeiro')) {
        $b = fin_balancete(fin_lancamentos($pdo), fin_automaticos(fin_inscricoes_com_pagamento($pdo)), '', '');
    }
} catch (Throwable $e) {
    error_log('Pedal Solidário – dashboard: ' . $e->getMessage());
    $erro = 'Não foi possível carregar os números agora.' . (banco_debug() ? ' ' . $e->getMessage() : '');
}

/** Número inteiro com separador de milhar. */
function dash_n(int $n): string
{
    return number_format($n, 0, ',', '.');
}

/**
 * Barras horizontais empilhadas (pagas / pendentes), com legenda, dica e tabela.
 * $linhas: [['rotulo','pagas','pendentes'], ...]
 */
function dash_grafico(string $id, string $titulo, array $linhas, string $nota = ''): string
{
    $max = 0;
    foreach ($linhas as $l) {
        $max = max($max, $l['pagas'] + $l['pendentes']);
    }
    $h = '<section class="dash-grafico" data-grafico="' . e($id) . '"><h3>' . e($titulo) . '</h3>';
    if ($nota !== '') {
        $h .= '<p class="adm-nota">' . e($nota) . '</p>';
    }
    if (!$linhas || $max === 0) {
        return $h . '<p class="dash-vazio">Ainda sem inscrições.</p></section>';
    }
    $h .= '<ul class="dash-legenda" aria-hidden="true"><li><i class="dash-cor-pagas"></i>Pagas</li><li><i class="dash-cor-pendentes"></i>Pendentes</li></ul>';
    $h .= '<div class="dash-barras">';
    foreach ($linhas as $l) {
        $tot = $l['pagas'] + $l['pendentes'];
        $dica = $l['rotulo'] . ': ' . dash_n($tot) . ' (' . dash_n($l['pagas']) . ' pagas, ' . dash_n($l['pendentes']) . ' pendentes)';
        $h .= '<div class="dash-linha" data-linha="' . e($l['rotulo']) . '" data-pagas="' . (int) $l['pagas'] . '" data-pendentes="' . (int) $l['pendentes'] . '"'
            . ' tabindex="0" data-dica="' . e($dica) . '">'
            . '<span class="dash-rotulo">' . e($l['rotulo']) . '</span>'
            . '<span class="dash-trilho">';
        foreach (['pagas', 'pendentes'] as $s) {
            if ($l[$s] > 0) {
                $h .= '<span class="dash-seg dash-cor-' . $s . '" style="width:' . round($l[$s] / $max * 100, 2) . '%"></span>';
            }
        }
        $h .= '</span><span class="dash-valor">' . dash_n($tot) . '</span></div>';
    }
    $h .= '</div>';
    $h .= '<details><summary>Ver em tabela</summary><table class="adm-tabela adm-tabela--compacta"><thead><tr><th></th>'
        . '<th class="num">Pagas</th><th class="num">Pendentes</th><th class="num">Total</th></tr></thead><tbody>';
    foreach ($linhas as $l) {
        $h .= '<tr><td>' . e($l['rotulo']) . '</td><td class="num">' . dash_n($l['pagas']) . '</td><td class="num">' . dash_n($l['pendentes'])
            . '</td><td class="num">' . dash_n($l['pagas'] + $l['pendentes']) . '</td></tr>';
    }
    return $h . '</tbody></table></details></section>';
}

/** Barras de valores em reais (uma série). $valores: rótulo => centavos. */
function dash_grafico_valor(string $id, string $titulo, array $valores, string $cor): string
{
    $max = $valores ? max($valores) : 0;
    $h = '<section class="dash-grafico" data-grafico="' . e($id) . '"><h3>' . e($titulo) . '</h3>';
    if ($max <= 0) {
        return $h . '<p class="dash-vazio">Nada lançado ainda.</p></section>';
    }
    $h .= '<div class="dash-barras">';
    foreach ($valores as $rot => $c) {
        $h .= '<div class="dash-linha" data-linha="' . e((string) $rot) . '" data-valor="' . (int) $c . '" tabindex="0" data-dica="' . e($rot . ': ' . fin_moeda($c)) . '">'
            . '<span class="dash-rotulo">' . e((string) $rot) . '</span>'
            . '<span class="dash-trilho"><span class="dash-seg dash-cor-' . e($cor) . '" style="width:' . round($c / $max * 100, 2) . '%"></span></span>'
            . '<span class="dash-valor">' . e(fin_moeda($c)) . '</span></div>';
    }
    $h .= '</div><details><summary>Ver em tabela</summary><table class="adm-tabela adm-tabela--compacta"><tbody>';
    foreach ($valores as $rot => $c) {
        $h .= '<tr><td>' . e((string) $rot) . '</td><td class="num">' . e(fin_moeda($c)) . '</td></tr>';
    }
    return $h . '</tbody></table></details></section>';
}

$titulo_admin = 'Dashboard';
$menu_ativo   = 'dashboard';
require __DIR__ . '/_topo.php';
?>

<h1>Dashboard</h1>

<?php if ($erro): ?>
    <p class="adm-aviso adm-aviso--erro" role="alert"><?= e($erro) ?></p>
<?php endif; ?>

<?php if ($r): ?>
<h2 class="dash-secao">Evento</h2>
<section class="adm-cards adm-cards--neutro" aria-label="Destaques do evento">
    <div class="adm-card"><span>Inscrições</span><strong data-dash="total" data-valor="<?= $r['total'] ?>"><?= dash_n($r['total']) ?></strong>
        <small><span data-dash="pagas" data-valor="<?= $r['pagas'] ?>"><?= dash_n($r['pagas']) ?></span> pagas ·
            <span data-dash="pendentes" data-valor="<?= $r['pendentes'] ?>"><?= dash_n($r['pendentes']) ?></span> pendentes</small></div>
    <div class="adm-card"><span>Vagas restantes</span>
        <?php if ($r['vagas_restantes'] === null): ?><strong>sem limite</strong>
        <?php else: ?><strong data-dash="vagas" data-valor="<?= $r['vagas_restantes'] ?>"><?= dash_n($r['vagas_restantes']) ?></strong>
            <small>de <?= dash_n((int) $ev['inscricoes_vagas']) ?> (pagas e pendentes ocupam vaga)</small><?php endif; ?></div>
    <div class="adm-card"><span>Camisetas</span><strong data-dash="camisetas" data-valor="<?= $r['camisetas']['pagas'] + $r['camisetas']['pendentes'] ?>"><?= dash_n($r['camisetas']['pagas'] + $r['camisetas']['pendentes']) ?></strong>
        <small><?= dash_n($r['camisetas']['pagas']) ?> pagas · <?= dash_n($r['camisetas']['pendentes']) ?> pendentes</small></div>
    <div class="adm-card"><span>Sem equipe</span><strong data-dash="sem_equipe" data-valor="<?= $r['sem_equipe'] ?>"><?= dash_n($r['sem_equipe']) ?></strong>
        <small>inscritos que não informaram equipe</small></div>
</section>

<div class="dash-grade">
    <?= dash_grafico('categorias', 'Por categoria', $r['categorias']) ?>
    <?= dash_grafico('genero', 'Por gênero', $r['genero']) ?>
    <?= dash_grafico('idade', 'Por idade', $r['idade'], 'Idade no dia do evento.') ?>
    <?= dash_grafico('camisetas', 'Camisetas por modelo e tamanho', $r['camisetas_modelo']) ?>
    <?= dash_grafico('cidades', 'Cidades', $r['cidades'], 'As 10 com mais inscritos.') ?>
    <?= dash_grafico('equipes', 'Equipes', $r['equipes'], 'As 10 maiores.') ?>
</div>

<section class="dash-grafico dash-grafico--largo" data-grafico="por-dia">
    <h3>Inscrições por dia</h3>
    <?php if (!$r['por_dia']): ?>
        <p class="dash-vazio">Ainda sem inscrições.</p>
    <?php else: ?>
        <?php $max_dia = max(array_column($r['por_dia'], 'qtd')); ?>
        <div class="dash-colunas">
            <?php foreach ($r['por_dia'] as $d): ?>
                <span class="dash-coluna" data-linha="<?= e($d['data']) ?>" data-valor="<?= (int) $d['qtd'] ?>" tabindex="0"
                      data-dica="<?= e(data_br($d['data']) . ': ' . dash_n($d['qtd']) . ($d['qtd'] === 1 ? ' inscrição' : ' inscrições')) ?>">
                    <span class="dash-coluna__barra" style="height:<?= round($d['qtd'] / $max_dia * 100, 2) ?>%"></span>
                </span>
            <?php endforeach; ?>
        </div>
        <p class="dash-eixo"><span><?= e(data_br($r['por_dia'][0]['data'])) ?></span><span>máximo: <?= dash_n($max_dia) ?> no dia</span>
            <span><?= e(data_br(end($r['por_dia'])['data'])) ?></span></p>
        <details><summary>Ver em tabela</summary><table class="adm-tabela adm-tabela--compacta"><tbody>
            <?php foreach ($r['por_dia'] as $d): ?><tr><td><?= e(data_br($d['data'])) ?></td><td class="num"><?= dash_n($d['qtd']) ?></td></tr><?php endforeach; ?>
        </tbody></table></details>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($b): ?>
<div data-dash-fin>
    <h2 class="dash-secao">Financeiro</h2>
    <section class="adm-cards adm-cards--3" aria-label="Destaques financeiros">
        <div class="adm-card adm-card--entrada"><span>Entradas</span><strong><?= e(fin_moeda($b['total_entradas'])) ?></strong></div>
        <div class="adm-card adm-card--saida"><span>Saídas</span><strong><?= e(fin_moeda($b['total_saidas'])) ?></strong></div>
        <div class="adm-card"><span>Saldo</span><strong><?= e(fin_moeda($b['saldo_final'])) ?></strong></div>
    </section>
    <div class="dash-grade">
        <?= dash_grafico_valor('fin-balanco', 'Entradas x Saídas', ['Entradas' => $b['total_entradas'], 'Saídas' => $b['total_saidas']], 'pagas') ?>
        <?= dash_grafico_valor('fin-entradas', 'Entradas por origem', $b['entradas'], 'pagas') ?>
        <?= dash_grafico_valor('fin-saidas', 'Saídas por categoria', $b['saidas'], 'pagas') ?>
    </div>
    <p class="adm-nota">Os mesmos números do <a href="financeiro.php">Financeiro</a> (evento inteiro).</p>
</div>
<?php endif; ?>

<div class="dash-dica" role="status" hidden></div>
<script>
/* Dica com o número exato ao passar o mouse, tocar ou focar uma barra (texto via textContent) */
(function () {
    var dica = document.querySelector('.dash-dica');
    if (!dica) return;
    function mostrar(el, x, y) {
        dica.textContent = el.getAttribute('data-dica');
        dica.hidden = false;
        var r = dica.getBoundingClientRect();
        dica.style.left = Math.max(8, Math.min(window.innerWidth - r.width - 8, x + 12)) + 'px';
        dica.style.top = Math.max(8, y - r.height - 12) + 'px';
    }
    document.querySelectorAll('[data-dica]').forEach(function (el) {
        el.addEventListener('pointermove', function (e) { mostrar(el, e.clientX, e.clientY); });
        el.addEventListener('pointerleave', function () { dica.hidden = true; });
        el.addEventListener('focus', function () { var r = el.getBoundingClientRect(); mostrar(el, r.left + r.width / 2, r.top); });
        el.addEventListener('blur', function () { dica.hidden = true; });
    });
})();
</script>

<?php require __DIR__ . '/_rodape.php'; ?>
