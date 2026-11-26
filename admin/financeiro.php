<?php
/**
 * Financeiro: lançamentos manuais (entradas e saídas), categorias, valores automáticos das inscrições e balancete.
 * Regras e contas em includes/financeiro.php (em centavos).
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/financeiro.php';
admin_exigir('financeiro');

/** Data do filtro: 'AAAA-MM-DD' válida ou ''. */
function fin_data_filtro(string $v): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : '';
}

/** Texto para o CSV sem virar fórmula no Excel. */
function fin_celula(string $v): string
{
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

$de  = fin_data_filtro((string) ($_GET['de'] ?? ''));
$ate = fin_data_filtro((string) ($_GET['ate'] ?? ''));
if ($de !== '' && $ate !== '' && $de > $ate) {
    [$de, $ate] = [$ate, $de];
}
$qs = http_build_query(array_filter(['de' => $de, 'ate' => $ate]));
$aqui = 'financeiro.php' . ($qs !== '' ? '?' . $qs : '');
$usuario = (string) ($_SESSION['admin_usuario'] ?? 'painel');

$erros = [];
$form = ['id' => 0, 'tipo' => 'entrada', 'categoria_id' => '', 'data' => date('Y-m-d'), 'descricao' => '', 'valor' => ''];

try {
    $pdo = db();
    fin_garantir($pdo);
} catch (PDOException $e) {
    $pdo = null;
    $erros['_'] = 'Não foi possível conectar ao banco de dados.' . (banco_debug() ? ' ' . $e->getMessage() : '');
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido(post_txt('csrf'))) {
        flash('erro', 'Sessão expirada. Tente novamente.');
        header('Location: ' . $aqui);
        exit;
    }
    $acao = post_txt('acao');
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $categorias = fin_categorias($pdo);
        switch ($acao) {
            case 'lancar':
            case 'editar':
                $r = fin_validar_lancamento($_POST, $categorias);
                $existe = $acao === 'lancar';
                if ($acao === 'editar') {
                    $st = $pdo->prepare('SELECT COUNT(*) FROM fin_lancamentos WHERE id = :id');
                    $st->execute([':id' => $id]);
                    $existe = (int) $st->fetchColumn() === 1;
                }
                if (!$existe) {
                    flash('erro', 'Lançamento não encontrado.');
                } elseif ($r['erros']) {
                    $erros = $r['erros'];
                    $form = array_merge($form, array_intersect_key($_POST, $form), ['id' => $acao === 'editar' ? $id : 0]);
                    break;
                } else {
                    $d = $r['dados'];
                    $valor = number_format($d['valor'] / 100, 2, '.', '');
                    if ($acao === 'lancar') {
                        $pdo->prepare('INSERT INTO fin_lancamentos (tipo, categoria_id, data, descricao, valor, criado_por)
                                       VALUES (:t, :c, :d, :x, :v, :u)')
                            ->execute([':t' => $d['tipo'], ':c' => $d['categoria_id'], ':d' => $d['data'], ':x' => $d['descricao'], ':v' => $valor, ':u' => $usuario]);
                        flash('ok', ($d['tipo'] === 'saida' ? 'Saída' : 'Entrada') . ' de ' . fin_moeda($d['valor']) . ' lançada.');
                    } else {
                        $pdo->prepare('UPDATE fin_lancamentos SET tipo = :t, categoria_id = :c, data = :d, descricao = :x, valor = :v,
                                       alterado_por = :u, alterado_em = NOW() WHERE id = :id')
                            ->execute([':t' => $d['tipo'], ':c' => $d['categoria_id'], ':d' => $d['data'], ':x' => $d['descricao'], ':v' => $valor, ':u' => $usuario, ':id' => $id]);
                        flash('ok', 'Lançamento alterado.');
                    }
                }
                header('Location: ' . $aqui);
                exit;

            case 'excluir':
                $st = $pdo->prepare('DELETE FROM fin_lancamentos WHERE id = :id');
                $st->execute([':id' => $id]);
                flash($st->rowCount() ? 'ok' : 'erro', $st->rowCount() ? 'Lançamento excluído.' : 'Lançamento não encontrado.');
                header('Location: ' . $aqui);
                exit;

            case 'cat_criar':
            case 'cat_renomear':
                $nome = trim(post_txt('nome'));
                $tipo = post_txt('tipo');
                if ($nome === '' || mb_strlen($nome) > 60) {
                    flash('erro', 'O nome da categoria deve ter de 1 a 60 caracteres.');
                } elseif (fin_nome_reservado($nome)) {
                    flash('erro', 'O nome "' . $nome . '" é usado pelos valores automáticos das inscrições. Escolha outro (ex.: "' . $nome . ' em dinheiro").');
                } elseif ($acao === 'cat_criar' && !isset(FIN_TIPOS[$tipo])) {
                    flash('erro', 'Escolha se a categoria é de entrada ou de saída.');
                } else {
                    try {
                        if ($acao === 'cat_criar') {
                            $pdo->prepare('INSERT INTO fin_categorias (tipo, nome) VALUES (:t, :n)')->execute([':t' => $tipo, ':n' => $nome]);
                            flash('ok', 'Categoria "' . $nome . '" criada.');
                        } else {
                            $st = $pdo->prepare('UPDATE fin_categorias SET nome = :n WHERE id = :id');
                            $st->execute([':n' => $nome, ':id' => $id]);
                            flash('ok', 'Categoria renomeada para "' . $nome . '".');
                        }
                    } catch (PDOException $e) {
                        if (($e->errorInfo[1] ?? 0) !== 1062) {
                            throw $e;
                        }
                        flash('erro', 'Já existe uma categoria com esse nome.');
                    }
                }
                header('Location: ' . $aqui . '#categorias');
                exit;

            case 'cat_ativar':
                $pdo->prepare('UPDATE fin_categorias SET ativa = :a WHERE id = :id')
                    ->execute([':a' => post_txt('ativa') === '1' ? 1 : 0, ':id' => $id]);
                flash('ok', post_txt('ativa') === '1' ? 'Categoria reativada.' : 'Categoria desativada: não aparece mais nos novos lançamentos.');
                header('Location: ' . $aqui . '#categorias');
                exit;

            case 'cat_excluir':
                $cat = null;
                foreach ($categorias as $c) {
                    if ((int) $c['id'] === $id) {
                        $cat = $c;
                    }
                }
                if (!$cat) {
                    flash('erro', 'Categoria não encontrada.');
                } elseif ((int) $cat['usos'] > 0) {
                    flash('erro', 'A categoria "' . $cat['nome'] . '" tem lançamentos e não pode ser excluída. Desative-a para que não apareça mais nos novos lançamentos.');
                } else {
                    // Confere de novo na hora de apagar: alguém pode ter lançado nela neste instante
                    $st = $pdo->prepare('DELETE FROM fin_categorias WHERE id = :id AND NOT EXISTS (SELECT 1 FROM fin_lancamentos WHERE categoria_id = :id2)');
                    $st->execute([':id' => $id, ':id2' => $id]);
                    flash($st->rowCount() ? 'ok' : 'erro', $st->rowCount() ? 'Categoria "' . $cat['nome'] . '" excluída.'
                        : 'A categoria "' . $cat['nome'] . '" tem lançamentos e não pode ser excluída. Desative-a para que não apareça mais nos novos lançamentos.');
                }
                header('Location: ' . $aqui . '#categorias');
                exit;

            default:
                flash('erro', 'Ação inválida.');
                header('Location: ' . $aqui);
                exit;
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro no financeiro: ' . $e->getMessage());
        $erros['_'] = 'Não foi possível concluir a ação.' . (banco_debug() ? ' ' . $e->getMessage() : '');
    }
}

$categorias = [];
$b = null;
if ($pdo) {
    try {
        $categorias = fin_categorias($pdo);
        $manuais = fin_lancamentos($pdo);
        $b = fin_balancete($manuais, fin_automaticos(fin_inscricoes_com_pagamento($pdo)), $de, $ate);

        // Editar: carrega o lançamento no formulário
        $editar = (int) ($_GET['editar'] ?? 0);
        if ($editar && !$erros) {
            foreach ($manuais as $m) {
                if ((int) $m['id'] === $editar) {
                    $form = ['id' => $editar, 'tipo' => $m['tipo'], 'categoria_id' => (string) $m['categoria_id'], 'data' => $m['data'],
                             'descricao' => $m['descricao'], 'valor' => number_format($m['valor'] / 100, 2, ',', '.')];
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro ao ler o financeiro: ' . $e->getMessage());
        $erros['_'] = 'Não foi possível carregar o financeiro.';
    }
}

/* ---------- CSV do período ---------- */
if ($b && isset($_GET['csv'])) {
    $reais = function (int $c): string {
        return number_format($c / 100, 2, ',', '');
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="financeiro-' . date('Y-m-d-Hi') . '.csv"');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    $linha = function (array $l) use ($saida) {
        fputcsv($saida, $l, ';', '"', '');
    };
    $linha(['Balancete', 'Período: ' . ($de !== '' ? data_br($de) : 'início') . ' a ' . ($ate !== '' ? data_br($ate) : 'hoje')]);
    $linha(['Saldo anterior', $reais($b['saldo_anterior'])]);
    foreach ($b['entradas'] as $rot => $c) {
        $linha(['Entrada', fin_celula($rot), $reais($c)]);
    }
    $linha(['Total de entradas', '', $reais($b['total_entradas'])]);
    foreach ($b['saidas'] as $rot => $c) {
        $linha(['Saída', fin_celula($rot), $reais($c)]);
    }
    $linha(['Total de saídas', '', $reais($b['total_saidas'])]);
    $linha(['Saldo', '', $reais($b['saldo_final'])]);
    $linha([]);
    $linha(['Data', 'Tipo', 'Categoria', 'Descrição', 'Valor', 'Saldo', 'Origem', 'Lançado por']);
    foreach ($b['linhas'] as $l) {
        $linha([data_br($l['data']), $l['tipo'] === 'saida' ? 'Saída' : 'Entrada', fin_celula($l['rotulo']), fin_celula($l['descricao']),
                ($l['tipo'] === 'saida' ? '-' : '') . $reais($l['valor']), $reais($l['saldo']),
                $l['automatico'] ? 'Automático (inscrições)' : 'Manual', fin_celula((string) $l['criado_por'])]);
    }
    exit;
}

$titulo_admin = 'Financeiro';
$menu_ativo   = 'financeiro';
require __DIR__ . '/_topo.php';
$cat_por_tipo = ['entrada' => [], 'saida' => []];
foreach ($categorias as $c) {
    $cat_por_tipo[$c['tipo']][] = $c;
}
$erro_campo = function (string $campo) use ($erros): string {
    return isset($erros[$campo]) ? '<small class="adm-campo__erro">' . e($erros[$campo]) . '</small>' : '';
};
?>

<h1>Financeiro</h1>

<?php if (isset($erros['_'])): ?>
    <p class="adm-aviso adm-aviso--erro" role="alert"><?= e($erros['_']) ?></p>
<?php endif; ?>

<form method="get" class="adm-filtros adm-fin-filtro">
    <div><label for="de">De</label><input type="date" id="de" name="de" value="<?= e($de) ?>"></div>
    <div><label for="ate">Até</label><input type="date" id="ate" name="ate" value="<?= e($ate) ?>"></div>
    <div class="adm-filtros__acoes">
        <button class="adm-btn" type="submit">Filtrar</button>
        <?php if ($qs !== ''): ?><a class="adm-btn adm-btn--claro" href="financeiro.php">Ver tudo</a><?php endif; ?>
        <a class="adm-btn adm-btn--claro" href="<?= e($aqui . ($qs !== '' ? '&' : '?') . 'csv=1') ?>">Exportar CSV</a>
    </div>
</form>

<?php if ($b): ?>
<section class="adm-cards adm-cards--3" aria-label="Resumo do período">
    <div class="adm-card adm-card--entrada"><span>Entradas</span><strong data-total="entradas" data-centavos="<?= $b['total_entradas'] ?>"><?= e(fin_moeda($b['total_entradas'])) ?></strong></div>
    <div class="adm-card adm-card--saida"><span>Saídas</span><strong data-total="saidas" data-centavos="<?= $b['total_saidas'] ?>"><?= e(fin_moeda($b['total_saidas'])) ?></strong></div>
    <div class="adm-card"><span>Saldo<?= $de !== '' ? ' (com o saldo anterior)' : '' ?></span><strong data-total="saldo" data-centavos="<?= $b['saldo_final'] ?>"><?= e(fin_moeda($b['saldo_final'])) ?></strong></div>
</section>
<?php endif; ?>

<div class="adm-duplo">
    <section>
        <h2 id="lancamento"><?= $form['id'] ? 'Editar lançamento' : 'Novo lançamento' ?></h2>
        <form method="post" action="<?= e($aqui) ?>" class="adm-form adm-form--cartao" data-lancamento-form novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="<?= $form['id'] ? 'editar' : 'lancar' ?>">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
            <div class="adm-fin-tipo" role="radiogroup" aria-label="Tipo">
                <?php foreach (FIN_TIPOS as $t => $rot): ?>
                    <label><input type="radio" name="tipo" value="<?= e($t) ?>"<?= $form['tipo'] === $t ? ' checked' : '' ?>> <?= e($rot) ?></label>
                <?php endforeach; ?>
            </div>
            <?= $erro_campo('tipo') ?>
            <label for="fin-cat">Categoria</label>
            <select id="fin-cat" name="categoria_id" data-categorias>
                <option value="">Escolha a categoria</option>
                <?php foreach ($cat_por_tipo as $t => $lista): ?>
                    <optgroup label="<?= e(FIN_TIPOS[$t]) ?>" data-tipo="<?= e($t) ?>">
                        <?php foreach ($lista as $c): ?>
                            <?php if ((int) $c['ativa']): ?>
                                <option value="<?= (int) $c['id'] ?>"<?= (string) $form['categoria_id'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['nome']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <?= $erro_campo('categoria_id') ?>
            <label for="fin-data">Data</label>
            <input type="date" id="fin-data" name="data" value="<?= e((string) $form['data']) ?>" required>
            <?= $erro_campo('data') ?>
            <label for="fin-desc">Descrição</label>
            <input type="text" id="fin-desc" name="descricao" value="<?= e((string) $form['descricao']) ?>" maxlength="200" placeholder="Ex.: Patrocínio da Padaria X">
            <?= $erro_campo('descricao') ?>
            <label for="fin-valor">Valor (R$)</label>
            <input type="text" id="fin-valor" name="valor" value="<?= e((string) $form['valor']) ?>" inputmode="decimal" placeholder="150,00">
            <?= $erro_campo('valor') ?>
            <button class="adm-btn" type="submit"><?= $form['id'] ? 'Salvar alteração' : 'Lançar' ?></button>
            <?php if ($form['id']): ?><a class="adm-link-cancelar" href="<?= e($aqui) ?>">Cancelar edição</a><?php endif; ?>
        </form>
    </section>

    <?php if ($b): ?>
    <section>
        <h2>Balancete</h2>
        <table class="adm-tabela adm-tabela--compacta adm-balancete">
            <?php if ($de !== ''): ?>
                <tr><td>Saldo anterior a <?= e(data_br($de)) ?></td><td class="num"><?= e(fin_moeda($b['saldo_anterior'])) ?></td></tr>
            <?php endif; ?>
            <tr class="adm-balancete__titulo"><th colspan="2">Entradas</th></tr>
            <?php foreach ($b['entradas'] as $rot => $c): ?>
                <tr data-linha-balancete="entrada" data-rotulo="<?= e($rot) ?>" data-centavos="<?= $c ?>">
                    <td><?= e($rot) ?><?= isset(FIN_AUTO[$rot]) ? ' <small class="adm-selo">automático</small>' : '' ?></td><td class="num"><?= e(fin_moeda($c)) ?></td></tr>
            <?php endforeach; ?>
            <tr class="adm-balancete__total"><th>Total de entradas</th><th class="num"><?= e(fin_moeda($b['total_entradas'])) ?></th></tr>
            <tr class="adm-balancete__titulo"><th colspan="2">Saídas</th></tr>
            <?php foreach ($b['saidas'] as $rot => $c): ?>
                <tr data-linha-balancete="saida" data-rotulo="<?= e($rot) ?>" data-centavos="<?= $c ?>">
                    <td><?= e($rot) ?><?= isset(FIN_AUTO[$rot]) ? ' <small class="adm-selo">automático</small>' : '' ?></td><td class="num"><?= e(fin_moeda($c)) ?></td></tr>
            <?php endforeach; ?>
            <tr class="adm-balancete__total"><th>Total de saídas</th><th class="num"><?= e(fin_moeda($b['total_saidas'])) ?></th></tr>
            <tr class="adm-balancete__saldo"><th>Saldo</th><th class="num"><?= e(fin_moeda($b['saldo_final'])) ?></th></tr>
        </table>
        <p class="adm-nota">Automáticos: inscrições, camisetas e taxas pagas, pela data em que o pagamento foi confirmado.
            A taxa de administração entra e sai (repasse) no mesmo valor. Diferente da tela Inscritos, aqui as inscrições pagas e
            depois canceladas continuam (não há reembolso).</p>
    </section>
    <?php endif; ?>
</div>

<?php if ($b): ?>
<section>
    <h2>Lançamentos</h2>
    <?php if (!$b['linhas'] && $de === ''): ?>
        <p class="adm-vazio">Nenhum lançamento ainda.</p>
    <?php else: ?>
    <div class="adm-rolagem">
        <table class="adm-tabela adm-tabela--compacta">
            <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th class="num">Valor</th><th class="num">Saldo</th><th></th></tr></thead>
            <tbody>
            <?php if ($de !== ''): ?>
                <tr data-saldo-anterior data-centavos="<?= $b['saldo_anterior'] ?>"><td><?= e(data_br($de)) ?></td><td colspan="3">Saldo anterior</td>
                    <td class="num"><?= e(fin_moeda($b['saldo_anterior'])) ?></td><td></td></tr>
            <?php endif; ?>
            <?php foreach ($b['linhas'] as $l): ?>
                <tr<?= $l['automatico'] ? ' class="adm-fin-auto"' : ' data-lancamento="' . (int) $l['id'] . '"' ?>>
                    <td><?= e(data_br($l['data'])) ?></td>
                    <td><?= e($l['rotulo']) ?><?= $l['automatico'] ? ' <small class="adm-selo">automático</small>' : '' ?></td>
                    <td><?= e($l['descricao']) ?><?= !$l['automatico'] && $l['criado_por'] !== '' ? ' <small class="adm-nota">por ' . e($l['criado_por']) . '</small>' : '' ?></td>
                    <td class="num <?= $l['tipo'] === 'saida' ? 'adm-fin-saida' : 'adm-fin-entrada' ?>"><?= e(fin_moeda(($l['tipo'] === 'saida' ? -1 : 1) * $l['valor'])) ?></td>
                    <td class="num"><?= e(fin_moeda($l['saldo'])) ?></td>
                    <td class="acoes acoes--varias">
                        <?php if (!$l['automatico']): ?>
                            <a class="adm-btn adm-btn--claro adm-btn--mini" href="<?= e($aqui . ($qs !== '' ? '&' : '?') . 'editar=' . (int) $l['id']) ?>#lancamento">Editar</a>
                            <form method="post" action="<?= e($aqui) ?>" data-excluir onsubmit="return confirm('Excluir este lançamento? Não dá para desfazer.');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                <button class="adm-btn adm-btn--perigo adm-btn--mini" type="submit">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section id="categorias">
    <h2>Categorias</h2>
    <p class="adm-nota">Categoria com lançamentos não pode ser excluída; desative para que não apareça mais nos novos lançamentos.</p>
    <div class="adm-duplo">
        <?php foreach ($cat_por_tipo as $t => $lista): ?>
            <div>
                <h3><?= $t === 'saida' ? 'Saídas' : 'Entradas' ?></h3>
                <table class="adm-tabela adm-tabela--compacta">
                    <tbody>
                    <?php foreach ($lista as $c): ?>
                        <tr<?= (int) $c['ativa'] ? '' : ' class="adm-fin-inativa"' ?>>
                            <td>
                                <form method="post" action="<?= e($aqui) ?>" class="adm-fin-renomear">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="cat_renomear">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <input type="text" name="nome" value="<?= e($c['nome']) ?>" maxlength="60" aria-label="Nome da categoria">
                                    <button class="adm-btn adm-btn--claro adm-btn--mini" type="submit">Renomear</button>
                                </form>
                                <small class="adm-nota"><?= (int) $c['usos'] ?> lançamento(s)<?= (int) $c['ativa'] ? '' : ' · desativada' ?></small>
                            </td>
                            <td class="acoes acoes--varias">
                                <form method="post" action="<?= e($aqui) ?>">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="cat_ativar">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <input type="hidden" name="ativa" value="<?= (int) $c['ativa'] ? '0' : '1' ?>">
                                    <button class="adm-btn adm-btn--claro adm-btn--mini" type="submit"><?= (int) $c['ativa'] ? 'Desativar' : 'Reativar' ?></button>
                                </form>
                                <?php if ((int) $c['usos'] === 0): ?>
                                    <form method="post" action="<?= e($aqui) ?>" onsubmit="return confirm('Excluir a categoria <?= e(addslashes($c['nome'])) ?>?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="acao" value="cat_excluir">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button class="adm-btn adm-btn--perigo adm-btn--mini" type="submit">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" action="<?= e($aqui) ?>" class="adm-fin-nova-cat">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="cat_criar">
                    <input type="hidden" name="tipo" value="<?= e($t) ?>">
                    <input type="text" name="nome" maxlength="60" placeholder="Nova categoria de <?= $t === 'saida' ? 'saída' : 'entrada' ?>" aria-label="Nova categoria" required>
                    <button class="adm-btn adm-btn--mini" type="submit">Criar</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
/* Mostra no seletor só as categorias do tipo escolhido (Entrada / Saída) */
(function () {
    var form = document.querySelector('form[data-lancamento-form]');
    if (!form) return;
    var sel = form.querySelector('[data-categorias]');
    function filtrar() {
        var tipo = (form.querySelector('[name=tipo]:checked') || {}).value;
        Array.prototype.forEach.call(sel.querySelectorAll('optgroup'), function (g) {
            var outro = g.getAttribute('data-tipo') !== tipo;
            g.hidden = outro;
            g.disabled = outro;
        });
        var opt = sel.options[sel.selectedIndex];
        if (opt && opt.parentNode.disabled) sel.value = '';
    }
    Array.prototype.forEach.call(form.querySelectorAll('[name=tipo]'), function (r) { r.addEventListener('change', filtrar); });
    filtrar();
})();
</script>

<?php require __DIR__ . '/_rodape.php'; ?>
