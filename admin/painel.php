<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_filtros.php';
admin_exigir();

$ev = evento();
$por_pagina = 25;

[$onde, $params, $f] = montar_filtros($_GET);
$pagina_atual = max(1, (int) ($_GET['p'] ?? 1));

$linhas = [];
$total_filtrado = 0;
$resumo = ['pendente' => ['n' => 0, 'valor' => 0.0], 'pago' => ['n' => 0, 'valor' => 0.0], 'cancelado' => ['n' => 0, 'valor' => 0.0]];
$por_categoria = [];
$por_tamanho = [];
$erro_banco = null;

try {
    $pdo = db();

    // Totais gerais (não dependem dos filtros)
    foreach ($pdo->query('SELECT status, COUNT(*) AS n, COALESCE(SUM(valor_total),0) AS valor, COALESCE(SUM(valor_taxa),0) AS taxa FROM inscricoes GROUP BY status') as $r) {
        $resumo[$r['status']] = ['n' => (int) $r['n'], 'valor' => (float) $r['valor'], 'taxa' => (float) $r['taxa']];
    }
    foreach ($pdo->query('SELECT categoria, status, COUNT(*) AS n FROM inscricoes WHERE status <> \'cancelado\' GROUP BY categoria, status') as $r) {
        $por_categoria[$r['categoria']][$r['status']] = (int) $r['n'];
    }
    foreach ($pdo->query('SELECT camiseta_tamanho AS t, status, COUNT(*) AS n FROM inscricoes WHERE camiseta_tamanho IS NOT NULL AND status <> \'cancelado\' GROUP BY camiseta_tamanho, status') as $r) {
        $por_tamanho[$r['t']][$r['status']] = (int) $r['n'];
    }

    // Lista filtrada
    $st = $pdo->prepare("SELECT COUNT(*) FROM inscricoes $onde");
    $st->execute($params);
    $total_filtrado = (int) $st->fetchColumn();

    $total_paginas = max(1, (int) ceil($total_filtrado / $por_pagina));
    $pagina_atual  = min($pagina_atual, $total_paginas);
    $offset        = ($pagina_atual - 1) * $por_pagina;

    $st = $pdo->prepare("SELECT id, codigo, nome, cpf, categoria, equipe, camiseta_tamanho, valor_total, status, criado_em
                         FROM inscricoes $onde
                         ORDER BY criado_em DESC, id DESC
                         LIMIT " . (int) $por_pagina . " OFFSET " . (int) $offset);
    $st->execute($params);
    $linhas = $st->fetchAll();
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro no painel: ' . $e->getMessage());
    $erro_banco = banco_debug() ? $e->getMessage() : 'Não foi possível carregar os dados.';
    $total_paginas = 1;
}

// Query string dos filtros (para paginação e exportação)
$filtros_qs = http_build_query(array_filter($f, fn($v) => $v !== ''));
$voltar = 'painel.php' . ($filtros_qs !== '' || $pagina_atual > 1
    ? '?' . http_build_query(array_filter($f + ['p' => $pagina_atual > 1 ? $pagina_atual : ''], fn($v) => $v !== ''))
    : '');
function link_pagina(string $qs, int $p): string
{
    $partes = [];
    if ($qs !== '') { $partes[] = $qs; }
    if ($p > 1) { $partes[] = 'p=' . $p; }
    return 'painel.php' . ($partes ? '?' . implode('&', $partes) : '');
}

$titulo_admin = 'Inscritos';
$menu_ativo = 'inscritos';
require __DIR__ . '/_topo.php';
?>

<h1>Inscritos</h1>

<?php if ($erro_banco): ?>
    <p class="adm-aviso adm-aviso--erro">Erro ao consultar o banco: <?= e($erro_banco) ?></p>
<?php endif; ?>

<section class="adm-cards" aria-label="Resumo">
    <div class="adm-card">
        <span>Pagos</span>
        <strong><?= (int) $resumo['pago']['n'] ?></strong>
        <small><?= e(moeda($resumo['pago']['valor'])) ?> recebidos (<?= e(moeda((float) ($resumo['pago']['taxa'] ?? 0))) ?> de taxa)</small>
    </div>
    <div class="adm-card">
        <span>Pendentes</span>
        <strong><?= (int) $resumo['pendente']['n'] ?></strong>
        <small><?= e(moeda($resumo['pendente']['valor'])) ?> a receber (<?= e(moeda((float) ($resumo['pendente']['taxa'] ?? 0))) ?> de taxa)</small>
    </div>
    <div class="adm-card">
        <span>Cancelados</span>
        <strong><?= (int) $resumo['cancelado']['n'] ?></strong>
        <small>não entram nas contas</small>
    </div>
    <div class="adm-card">
        <span>Total de inscrições</span>
        <strong><?= (int) ($resumo['pago']['n'] + $resumo['pendente']['n'] + $resumo['cancelado']['n']) ?></strong>
        <small>incluindo canceladas</small>
    </div>
</section>

<section class="adm-duplo">
    <div>
        <h2>Por categoria</h2>
        <div class="adm-rolagem">
            <table class="adm-tabela adm-tabela--compacta">
                <thead><tr><th>Categoria</th><th class="num">Pagos</th><th class="num">Pendentes</th></tr></thead>
                <tbody>
                <?php foreach ($ev['categorias'] as $cat): ?>
                    <tr>
                        <td><?= e($cat['nome']) ?></td>
                        <td class="num"><?= (int) ($por_categoria[$cat['slug']]['pago'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($por_categoria[$cat['slug']]['pendente'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <h2>Camisetas por tamanho</h2>
        <div class="adm-rolagem">
            <table class="adm-tabela adm-tabela--compacta">
                <thead><tr><th>Tamanho</th><th class="num">Pagas</th><th class="num">Pendentes</th></tr></thead>
                <tbody>
                <?php $soma_p = 0; $soma_n = 0; ?>
                <?php foreach ($ev['camiseta']['tamanhos'] as $t): ?>
                    <?php $p = (int) ($por_tamanho[$t]['pago'] ?? 0); $n = (int) ($por_tamanho[$t]['pendente'] ?? 0); $soma_p += $p; $soma_n += $n; ?>
                    <tr><td><?= e($t) ?></td><td class="num"><?= $p ?></td><td class="num"><?= $n ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Total</th><th class="num"><?= $soma_p ?></th><th class="num"><?= $soma_n ?></th></tr></tfoot>
            </table>
        </div>
    </div>
</section>

<h2>Lista</h2>

<form method="get" class="adm-filtros" action="painel.php">
    <div>
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, CPF, e-mail, equipe ou código">
    </div>
    <div>
        <label for="status">Situação</label>
        <select id="status" name="status">
            <option value="">Todas</option>
            <?php foreach (STATUS_ROTULOS as $chave => $rotulo): ?>
                <option value="<?= e($chave) ?>"<?= $f['status'] === $chave ? ' selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="categoria">Categoria</label>
        <select id="categoria" name="categoria">
            <option value="">Todas</option>
            <?php foreach ($ev['categorias'] as $cat): ?>
                <option value="<?= e($cat['slug']) ?>"<?= $f['categoria'] === $cat['slug'] ? ' selected' : '' ?>><?= e($cat['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="camiseta">Camiseta</label>
        <select id="camiseta" name="camiseta">
            <option value="">Todas</option>
            <option value="sim"<?= $f['camiseta'] === 'sim' ? ' selected' : '' ?>>Com camiseta</option>
            <option value="nao"<?= $f['camiseta'] === 'nao' ? ' selected' : '' ?>>Sem camiseta</option>
        </select>
    </div>
    <div class="adm-filtros__acoes">
        <button class="adm-btn" type="submit">Filtrar</button>
        <?php if ($filtros_qs !== ''): ?><a class="adm-btn adm-btn--claro" href="painel.php">Limpar</a><?php endif; ?>
        <a class="adm-btn adm-btn--claro" href="exportar.php<?= $filtros_qs !== '' ? '?' . e($filtros_qs) : '' ?>">Exportar CSV</a>
    </div>
</form>

<p class="adm-contagem"><?= (int) $total_filtrado ?> <?= $total_filtrado === 1 ? 'inscrição encontrada' : 'inscrições encontradas' ?><?= $filtros_qs !== '' ? ' com os filtros aplicados' : '' ?>.</p>

<?php if (!$linhas): ?>
    <p class="adm-vazio">Nenhuma inscrição para mostrar.</p>
<?php else: ?>
    <div class="adm-rolagem">
        <table class="adm-tabela">
            <thead>
                <tr>
                    <th>Código</th><th>Nome</th><th>CPF</th><th>Categoria</th><th>Equipe</th>
                    <th>Camiseta</th><th class="num">Total</th><th>Situação</th><th>Inscrito em</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($linhas as $l): ?>
                <?php $cat = categoria_por_slug($l['categoria']); ?>
                <tr>
                    <td><code><?= e($l['codigo']) ?></code></td>
                    <td><a href="inscrito.php?id=<?= (int) $l['id'] ?>"><?= e($l['nome']) ?></a></td>
                    <td><?= e(cpf_formatado($l['cpf'])) ?></td>
                    <td><?= e($cat['nome'] ?? $l['categoria']) ?></td>
                    <td><?= e($l['equipe'] ?? '') ?></td>
                    <td><?= e($l['camiseta_tamanho'] ?? '—') ?></td>
                    <td class="num"><?= e(moeda((float) $l['valor_total'])) ?></td>
                    <td><span class="adm-selo adm-selo--<?= e($l['status']) ?>"><?= e(STATUS_ROTULOS[$l['status']] ?? $l['status']) ?></span></td>
                    <td><?= e(data_hora_br($l['criado_em'])) ?></td>
                    <td class="acoes">
                        <?php if ($l['status'] === 'pendente'): ?>
                            <form method="post" action="acao.php" onsubmit="return confirm('Confirmar o pagamento de <?= e(addslashes($l['nome'])) ?>?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                <input type="hidden" name="acao" value="pago">
                                <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                                <button class="adm-btn adm-btn--mini" type="submit">Confirmar pagamento</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
        <nav class="adm-paginacao" aria-label="Paginação">
            <?php if ($pagina_atual > 1): ?>
                <a class="adm-btn adm-btn--claro" href="<?= e(link_pagina($filtros_qs, $pagina_atual - 1)) ?>">Anterior</a>
            <?php endif; ?>
            <span>Página <?= (int) $pagina_atual ?> de <?= (int) $total_paginas ?></span>
            <?php if ($pagina_atual < $total_paginas): ?>
                <a class="adm-btn adm-btn--claro" href="<?= e(link_pagina($filtros_qs, $pagina_atual + 1)) ?>">Próxima</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/_rodape.php'; ?>
