<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/cupons.php';
admin_exigir();

/** Converte "10,50" ou "10.50" em número; devolve null se não for um valor válido. */
function numero_decimal(string $texto): ?float
{
    $texto = str_replace(',', '.', trim($texto));
    if ($texto === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $texto)) {
        return null;
    }
    return (float) $texto;
}

$erros   = [];
$edicao  = null;                       // cupom que está sendo editado
$form    = ['nome' => '', 'codigo' => '', 'tipo' => 'percentual', 'valor' => '', 'quantidade' => ''];

try {
    $pdo = db();
} catch (PDOException $e) {
    $pdo = null;
    $erros[] = 'Não foi possível conectar ao banco de dados.' . (banco_debug() ? ' ' . $e->getMessage() : '');
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = post_txt('acao');

    if (!csrf_valido(post_txt('csrf'))) {
        flash('erro', 'Sessão expirada. Tente novamente.');
        header('Location: cupons.php');
        exit;
    }

    try {
        switch ($acao) {

            case 'criar':
            case 'editar':
                $id = (int) ($_POST['id'] ?? 0);
                $form = [
                    'nome'       => trim(preg_replace('/\s+/u', ' ', post_txt('nome'))),
                    'codigo'     => post_txt('codigo'),
                    'tipo'       => post_txt('tipo'),
                    'valor'      => post_txt('valor'),
                    'quantidade' => post_txt('quantidade'),
                ];

                if (mb_strlen($form['nome']) < 3 || mb_strlen($form['nome']) > 100) {
                    $erros[] = 'Informe o nome do cupom (de 3 a 100 caracteres).';
                }
                if (!in_array($form['tipo'], ['percentual', 'fixo'], true)) {
                    $erros[] = 'Escolha o tipo de desconto.';
                }

                $valor = numero_decimal($form['valor']);
                if ($valor === null || $valor <= 0) {
                    $erros[] = 'Informe um valor de desconto maior que zero.';
                } elseif ($form['tipo'] === 'percentual' && $valor > 100) {
                    $erros[] = 'O desconto percentual não pode passar de 100%.';
                } elseif ($form['tipo'] === 'fixo' && $valor > 9999.99) {
                    $erros[] = 'Valor de desconto muito alto.';
                }

                if (!preg_match('/^\d{1,6}$/', $form['quantidade']) || (int) $form['quantidade'] < 1) {
                    $erros[] = 'Informe a quantidade de usos (um número inteiro, no mínimo 1).';
                }
                $quantidade = (int) $form['quantidade'];

                if ($acao === 'criar') {
                    $codigo = cupom_normalizar($form['codigo']);
                    if ($codigo === '') {
                        $erros[] = 'O código deve ter de 3 a 30 caracteres, usando letras, números, hífen ou sublinhado (sem espaços).';
                    }

                    if (!$erros) {
                        try {
                            $pdo->prepare('INSERT INTO cupons (nome, codigo, tipo, valor, quantidade) VALUES (:n, :c, :t, :v, :q)')
                                ->execute([':n' => $form['nome'], ':c' => $codigo, ':t' => $form['tipo'], ':v' => number_format($valor, 2, '.', ''), ':q' => $quantidade]);
                            flash('ok', 'Cupom "' . $codigo . '" criado.');
                            header('Location: cupons.php');
                            exit;
                        } catch (PDOException $e) {
                            if (($e->errorInfo[1] ?? 0) === 1062) {
                                $erros[] = 'Já existe um cupom com o código "' . $codigo . '".';
                            } else {
                                throw $e;
                            }
                        }
                    }
                } else { // editar
                    $st = $pdo->prepare('SELECT * FROM cupons WHERE id = :id');
                    $st->execute([':id' => $id]);
                    $edicao = $st->fetch() ?: null;

                    if (!$edicao) {
                        $erros[] = 'Cupom não encontrado.';
                        break;
                    }
                    $form['codigo'] = $edicao['codigo'];
                    $usos = cupom_usos($pdo, $id);
                    if (!$erros && $quantidade < $usos) {
                        $erros[] = 'A quantidade não pode ser menor que os ' . $usos . ' usos já feitos.';
                    }

                    if (!$erros) {
                        $pdo->prepare('UPDATE cupons SET nome = :n, tipo = :t, valor = :v, quantidade = :q WHERE id = :id')
                            ->execute([':n' => $form['nome'], ':t' => $form['tipo'], ':v' => number_format($valor, 2, '.', ''), ':q' => $quantidade, ':id' => $id]);
                        flash('ok', 'Cupom "' . $edicao['codigo'] . '" atualizado. Inscrições já feitas mantêm o desconto que receberam.');
                        header('Location: cupons.php');
                        exit;
                    }
                }
                break;

            case 'alternar':
                $id = (int) ($_POST['id'] ?? 0);
                $st = $pdo->prepare('SELECT codigo, ativo FROM cupons WHERE id = :id');
                $st->execute([':id' => $id]);
                $c = $st->fetch();
                if (!$c) {
                    $erros[] = 'Cupom não encontrado.';
                    break;
                }
                $novo = (int) $c['ativo'] ? 0 : 1;
                $pdo->prepare('UPDATE cupons SET ativo = :a WHERE id = :id')->execute([':a' => $novo, ':id' => $id]);
                flash('ok', 'Cupom "' . $c['codigo'] . '" ' . ($novo ? 'ativado.' : 'desativado. Ninguém mais consegue usá-lo.'));
                header('Location: cupons.php');
                exit;

            case 'excluir':
                $id = (int) ($_POST['id'] ?? 0);
                $st = $pdo->prepare('SELECT codigo FROM cupons WHERE id = :id');
                $st->execute([':id' => $id]);
                $codigo_exc = $st->fetchColumn();
                if ($codigo_exc === false) {
                    $erros[] = 'Cupom não encontrado.';
                    break;
                }
                $st = $pdo->prepare('SELECT COUNT(*) FROM inscricoes WHERE cupom_id = :id');
                $st->execute([':id' => $id]);
                if ((int) $st->fetchColumn() > 0) {
                    $erros[] = 'Este cupom já foi usado em inscrições e não pode ser excluído. Desative-o para encerrar o uso.';
                    break;
                }
                $pdo->prepare('DELETE FROM cupons WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'Cupom "' . $codigo_exc . '" excluído.');
                header('Location: cupons.php');
                exit;

            default:
                $erros[] = 'Ação inválida.';
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro em cupons: ' . $e->getMessage());
        $erros[] = 'Não foi possível concluir a ação.' . (banco_debug() ? ' ' . $e->getMessage() : '');
    }
}

// Cupom em edição (link "Editar") e cupom cujos usos estão sendo vistos
$editar_id = (int) ($_GET['editar'] ?? 0);
if ($pdo && !$edicao && $editar_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = $pdo->prepare('SELECT * FROM cupons WHERE id = :id');
    $st->execute([':id' => $editar_id]);
    $edicao = $st->fetch() ?: null;
    if ($edicao) {
        $form = [
            'nome'       => $edicao['nome'],
            'codigo'     => $edicao['codigo'],
            'tipo'       => $edicao['tipo'],
            'valor'      => number_format((float) $edicao['valor'], 2, ',', ''),
            'quantidade' => (string) $edicao['quantidade'],
        ];
    }
}

$cupons = [];
$usos_cupom = null;
$usos_lista = [];
if ($pdo) {
    try {
        $cupons = $pdo->query("SELECT c.*,
                (SELECT COUNT(*) FROM inscricoes i WHERE i.cupom_id = c.id AND i.status <> 'cancelado') AS usos,
                (SELECT COALESCE(SUM(i.valor_desconto), 0) FROM inscricoes i WHERE i.cupom_id = c.id AND i.status <> 'cancelado') AS desconto_total,
                (SELECT COUNT(*) FROM inscricoes i WHERE i.cupom_id = c.id) AS vinculos
            FROM cupons c ORDER BY c.criado_em DESC, c.id DESC")->fetchAll();

        $ver_id = (int) ($_GET['usos'] ?? 0);
        if ($ver_id > 0) {
            $st = $pdo->prepare('SELECT * FROM cupons WHERE id = :id');
            $st->execute([':id' => $ver_id]);
            $usos_cupom = $st->fetch() ?: null;
            if ($usos_cupom) {
                $st = $pdo->prepare('SELECT id, codigo, nome, categoria, valor_inscricao, valor_desconto, valor_total, status, criado_em
                                     FROM inscricoes WHERE cupom_id = :id ORDER BY criado_em DESC, id DESC');
                $st->execute([':id' => $ver_id]);
                $usos_lista = $st->fetchAll();
            }
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro ao listar cupons: ' . $e->getMessage());
        $erros[] = 'Não foi possível carregar os cupons.' . (banco_debug() ? ' ' . $e->getMessage() : '');
    }
}

$total_usos = 0;
$total_desconto = 0.0;
foreach ($cupons as $c) {
    $total_usos += (int) $c['usos'];
    $total_desconto += (float) $c['desconto_total'];
}

$rotulos_situacao = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'esgotado' => 'Esgotado'];

$titulo_admin = 'Cupons';
$menu_ativo = 'cupons';
require __DIR__ . '/_topo.php';
?>

<h1>Cupons de desconto</h1>

<?php if ($erros): ?>
    <div class="adm-aviso adm-aviso--erro" role="alert">
        <ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<p>O desconto vale <strong>somente para o valor da inscrição</strong>, nunca para a camiseta. O desconto é calculado sobre o valor cheio da inscrição e não altera a taxa de administração, exceto quando cobre 100% da inscrição: nesse caso a taxa também é isenta e o atleta paga só a camiseta, se comprar. Se o total ficar em R$ 0,00, a inscrição já entra como paga. Um cupom conta como usado enquanto a inscrição não for cancelada ou excluída.</p>

<section class="adm-cards adm-cards--tres" aria-label="Resumo dos cupons">
    <div class="adm-card"><span>Cupons cadastrados</span><strong><?= count($cupons) ?></strong><small>&nbsp;</small></div>
    <div class="adm-card"><span>Usos (inscrições com cupom)</span><strong><?= $total_usos ?></strong><small>sem contar canceladas</small></div>
    <div class="adm-card"><span>Desconto concedido</span><strong><?= e(moeda($total_desconto)) ?></strong><small>soma de todos os cupons</small></div>
</section>

<?php if ($usos_cupom): ?>
    <h2>Usos do cupom <code><?= e($usos_cupom['codigo']) ?></code> — <?= e($usos_cupom['nome']) ?></h2>
    <p><a href="cupons.php">&larr; Voltar à lista de cupons</a></p>
    <?php if (!$usos_lista): ?>
        <p class="adm-vazio">Este cupom ainda não foi usado.</p>
    <?php else: ?>
        <div class="adm-rolagem">
            <table class="adm-tabela">
                <thead><tr><th>Inscrito</th><th>Código</th><th>Categoria</th><th class="num">Inscrição</th><th class="num">Desconto</th><th class="num">Total pago/a pagar</th><th>Situação</th><th>Data</th></tr></thead>
                <tbody>
                <?php foreach ($usos_lista as $u): ?>
                    <?php $cat = categoria_por_slug($u['categoria']); ?>
                    <tr>
                        <td><a href="inscrito.php?id=<?= (int) $u['id'] ?>"><?= e($u['nome']) ?></a></td>
                        <td><code><?= e($u['codigo']) ?></code></td>
                        <td><?= e($cat['nome'] ?? $u['categoria']) ?></td>
                        <td class="num"><?= e(moeda((float) $u['valor_inscricao'])) ?></td>
                        <td class="num">− <?= e(moeda((float) $u['valor_desconto'])) ?></td>
                        <td class="num"><?= e(moeda((float) $u['valor_total'])) ?></td>
                        <td><span class="adm-selo adm-selo--<?= e($u['status']) ?>"><?= e(STATUS_ROTULOS[$u['status']] ?? $u['status']) ?></span></td>
                        <td><?= e(data_hora_br($u['criado_em'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<h2>Cupons</h2>
<?php if (!$cupons): ?>
    <p class="adm-vazio">Nenhum cupom cadastrado ainda. Use o formulário abaixo para criar o primeiro.</p>
<?php else: ?>
    <div class="adm-rolagem">
        <table class="adm-tabela">
            <thead>
                <tr><th>Nome</th><th>Código</th><th>Desconto</th><th class="num">Usos</th><th class="num">Restantes</th><th class="num">Desconto concedido</th><th>Situação</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($cupons as $c): ?>
                <?php
                    $usos = (int) $c['usos'];
                    $situacao = cupom_situacao($c, $usos);
                    $restantes = max(0, (int) $c['quantidade'] - $usos);
                ?>
                <tr>
                    <td><?= e($c['nome']) ?></td>
                    <td><code><?= e($c['codigo']) ?></code></td>
                    <td><?= e(cupom_descricao($c)) ?></td>
                    <td class="num"><a href="cupons.php?usos=<?= (int) $c['id'] ?>"><?= $usos ?> de <?= (int) $c['quantidade'] ?></a></td>
                    <td class="num"><?= $restantes ?></td>
                    <td class="num"><?= e(moeda((float) $c['desconto_total'])) ?></td>
                    <td><span class="adm-selo adm-selo--cupom-<?= e($situacao) ?>"><?= e($rotulos_situacao[$situacao]) ?></span></td>
                    <td class="acoes acoes--varias">
                        <a class="adm-btn adm-btn--claro adm-btn--mini" href="cupons.php?editar=<?= (int) $c['id'] ?>#form-cupom">Editar</a>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="acao" value="alternar">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="adm-btn adm-btn--claro adm-btn--mini" type="submit"><?= (int) $c['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                        </form>
                        <?php if ((int) $c['vinculos'] === 0): ?>
                            <form method="post" onsubmit="return confirm('Excluir o cupom <?= e(addslashes($c['codigo'])) ?>?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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

<h2 id="form-cupom"><?= $edicao ? 'Editar cupom ' . e($edicao['codigo']) : 'Novo cupom' ?></h2>
<form method="post" class="adm-form adm-form--cartao adm-form--grade" autocomplete="off" action="cupons.php<?= $edicao ? '?editar=' . (int) $edicao['id'] : '' ?>#form-cupom">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'criar' ?>">
    <?php if ($edicao): ?><input type="hidden" name="id" value="<?= (int) $edicao['id'] ?>"><?php endif; ?>

    <div>
        <label for="c-nome">Nome do cupom</label>
        <input type="text" id="c-nome" name="nome" maxlength="100" value="<?= e($form['nome']) ?>" placeholder="Ex.: Parceiros do Lions" required>
    </div>
    <div>
        <label for="c-codigo">Código (o que o atleta digita)</label>
        <?php if ($edicao): ?>
            <input type="text" id="c-codigo" value="<?= e($form['codigo']) ?>" readonly>
        <?php else: ?>
            <input type="text" id="c-codigo" name="codigo" maxlength="30" value="<?= e($form['codigo']) ?>" placeholder="Ex.: LIONS20" style="text-transform:uppercase" required>
        <?php endif; ?>
    </div>
    <div>
        <label for="c-tipo">Tipo de desconto</label>
        <select id="c-tipo" name="tipo" required>
            <option value="percentual"<?= $form['tipo'] === 'percentual' ? ' selected' : '' ?>>Percentual (%)</option>
            <option value="fixo"<?= $form['tipo'] === 'fixo' ? ' selected' : '' ?>>Valor fixo (R$)</option>
        </select>
    </div>
    <div>
        <label for="c-valor">Valor do desconto <small>(% ou R$, conforme o tipo)</small></label>
        <input type="text" id="c-valor" name="valor" inputmode="decimal" value="<?= e($form['valor']) ?>" placeholder="Ex.: 20 ou 15,00" required>
    </div>
    <div>
        <label for="c-qtd">Quantidade de usos</label>
        <input type="text" id="c-qtd" name="quantidade" inputmode="numeric" value="<?= e($form['quantidade']) ?>" placeholder="Ex.: 50" required>
    </div>
    <div class="adm-form__acoes">
        <button class="adm-btn" type="submit"><?= $edicao ? 'Salvar alterações' : 'Criar cupom' ?></button>
        <?php if ($edicao): ?><a class="adm-btn adm-btn--claro" href="cupons.php">Cancelar edição</a><?php endif; ?>
    </div>
</form>
<?php if ($edicao): ?>
    <p class="adm-nota">O código não pode ser alterado depois de criado. Mudar o desconto não afeta inscrições já feitas.</p>
<?php endif; ?>

<?php require __DIR__ . '/_rodape.php'; ?>
