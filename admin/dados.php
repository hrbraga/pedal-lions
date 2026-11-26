<?php
/**
 * Dados do Evento: edita tudo o que aparece no site (antes só em config/evento.php).
 * Cada "Salvar" grava uma nova versão; o histórico permite restaurar versões anteriores.
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/evento_validar.php';
require_once __DIR__ . '/../includes/inscricoes.php';
admin_exigir('dados');

const TRAVA_DADOS = 'pedal_evento_dados';

$erros = [];      // 'secao.campo' => mensagem; '_' = mensagem geral
$form  = null;    // o que a pessoa enviou, quando volta com erro

try {
    $pdo = db();
    $pdo->query('SELECT 1 FROM evento_versoes LIMIT 1');
} catch (PDOException $e) {
    $pdo = null;
    $erros['_'] = 'Não foi possível conectar ao banco de dados.' . (banco_debug() ? ' ' . $e->getMessage() : '');
}

/** Apaga os logos enviados que nenhuma versão usa mais; uma falha aqui nunca atrapalha a gravação. */
function limpar_logos(PDO $pdo): void
{
    try {
        patro_limpar_envios($pdo, patro_raiz());
    } catch (Throwable $e) {
        error_log('Pedal Solidário – limpeza dos logos: ' . $e->getMessage());
    }
}

/** Slug da categoria => número de inscrições (de qualquer situação). */
function inscritos_por_categoria(PDO $pdo): array
{
    $contagem = [];
    foreach ($pdo->query('SELECT categoria, COUNT(*) AS n FROM inscricoes GROUP BY categoria') as $l) {
        $contagem[$l['categoria']] = (int) $l['n'];
    }
    return $contagem;
}

// Envio maior que o post_max_size do servidor: o PHP descarta todo o formulário (inclusive o csrf)
$post_grande = $_SERVER['REQUEST_METHOD'] === 'POST' && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
if ($pdo && $post_grande) {
    $erros['_'] = 'Os arquivos enviados passam do limite do servidor. Envie menos logos de cada vez.';
} elseif ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido(post_txt('csrf'))) {
        flash('erro', 'Sessão expirada. Abra a página de novo e refaça a alteração.');
        header('Location: dados.php');
        exit;
    }
    $usuario = (string) ($_SESSION['admin_usuario'] ?? 'painel');
    $trava   = false;

    try {
        $inscritos = inscritos_por_categoria($pdo);
        $trava = (int) $pdo->query("SELECT GET_LOCK('" . TRAVA_DADOS . "', 10)")->fetchColumn() === 1;
        if (!$trava) {
            $erros['_'] = 'Outra gravação está em andamento. Tente de novo em instantes.';
            $form = $_POST;
        } elseif (post_txt('acao') === 'restaurar') {
            $versao = evento_versao($pdo, (int) post_txt('id'));
            if (!$versao) {
                flash('erro', 'Versão não encontrada.');
            } else {
                $faltando = [];
                $slugs = array_column($versao['dados']['categorias'] ?? evento_padrao()['categorias'], 'slug');
                foreach (array_keys($inscritos) as $slug) {
                    if (!in_array($slug, $slugs, true)) {
                        $faltando[] = $slug;
                    }
                }
                if ($faltando) {
                    flash('erro', 'Essa versão não tem a(s) categoria(s) ' . implode(', ', $faltando) . ', que já têm inscrições. Não foi restaurada.');
                } else {
                    evento_salvar($pdo, $versao['dados'], $usuario, $versao['id']);
                    limpar_logos($pdo);
                    flash('ok', 'Versão ' . $versao['id'] . ' restaurada. O site já mostra esses dados.');
                }
            }
            header('Location: dados.php#historico');
            exit;
        } else {
            $atual = evento_versao_atual($pdo);
            if ((int) post_txt('versao') !== ($atual ? $atual['id'] : 0)) {
                $erros['_'] = 'Outra pessoa salvou alterações enquanto você editava. Abra a página de novo para ver a versão atual '
                            . '(copie antes o que você digitou, se precisar).';
                $form = $_POST;
            } else {
                // Logos enviados são guardados já agora: se outro campo tiver erro, o logo não se perde
                [$entrada, $erros_envio] = patro_receber_envios($_POST, $_FILES, patro_raiz());
                $r = evento_validar($entrada, evento(), array_keys($inscritos));
                // Salvar que faz as inscrições abrirem NA HORA (ex.: início apagado sem querer) precisa de confirmação
                $abre_agora = !$r['erros'] && !$erros_envio && post_txt('confirmar_abertura') !== '1'
                    && inscricoes_abre_agora(inscricoes_fechamento($pdo), inscricoes_fechamento_de($r['dados'], vagas_ocupadas($pdo)));
                if ($abre_agora) {
                    $erros = ['inscricoes.confirmar' => 'Ao salvar, as inscrições abrem agora (o início está em branco ou já passou). '
                        . 'Se for isso mesmo, marque a confirmação em Inscrições e salve de novo. Para abrir só no dia marcado, preencha o início.'];
                    $form = $entrada;
                } elseif ($r['erros'] || $erros_envio) {
                    $erros = $erros_envio + $r['erros'];
                    $form = $entrada;
                } else {
                    evento_salvar($pdo, $r['dados'], $usuario);
                    limpar_logos($pdo);
                    $msg = 'Dados do evento salvos. O site já mostra as alterações.';
                    $vagas = $r['dados']['inscricoes_vagas'];
                    $ocupadas = vagas_ocupadas($pdo);
                    if ($vagas > 0 && $ocupadas >= $vagas) {
                        $msg .= " Atenção: já há $ocupadas inscrições ocupando vaga e o limite é $vagas, então as inscrições estão fechadas.";
                    }
                    flash('ok', $msg);
                    header('Location: dados.php');
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        // Qualquer falha inesperada: nada foi gravado e o que a pessoa digitou volta para a tela
        error_log('Pedal Solidário – erro ao salvar Dados do Evento: ' . $e->getMessage());
        $erros = ['_' => 'Não foi possível salvar agora. Nada foi gravado; o que você digitou continua abaixo. Tente de novo em instantes.'
                       . (banco_debug() ? ' Detalhe técnico: ' . $e->getMessage() : '')];
        $form = $_POST;
    } finally {
        if ($trava) {
            $pdo->query("SELECT RELEASE_LOCK('" . TRAVA_DADOS . "')");
        }
    }
}

$ev        = evento();
$atual     = $pdo ? evento_versao_atual($pdo) : null;
$historico = $pdo ? evento_versoes($pdo) : [];
$inscritos = $pdo ? inscritos_por_categoria($pdo) : [];
$ocupadas  = $pdo ? vagas_ocupadas($pdo) : 0;
$base      = evento_para_formulario($ev);

// Ao voltar com erro, mostra o que a pessoa enviou (listas sem os itens em branco, como na validação)
if ($form !== null) {
    $f = $base;
    foreach (['geral', 'inscricoes', 'contato', 'camiseta'] as $secao) {
        $f[$secao] = is_array($form[$secao] ?? null) ? $form[$secao] : [];
    }
    $f['camiseta']['modelos'] = itens_de(is_array($form['camiseta'] ?? null) ? $form['camiseta'] : [], 'modelos', ['slug', 'nome', 'valor']);
    $f['camiseta']['tamanhos'] = array_values(array_filter(
        is_array($form['camiseta']['tamanhos'] ?? null) ? $form['camiseta']['tamanhos'] : [],
        function ($t) { return is_string($t) && trim($t) !== ''; }
    ));
    $listas = [
        'categorias' => ['slug', 'nome', 'valor', 'resumo'], 'premiacoes' => ['titulo', 'regra', 'premio'],
        'rotas' => ['nome', 'distancia', 'altimetria', 'descricao', 'mapa'], 'programacao' => ['hora', 'titulo', 'desc'],
        'faq' => ['p', 'r'], 'patrocinadores' => ['logo', 'nome', 'url'],
    ];
    foreach ($listas as $lista => $campos) {
        $f[$lista] = itens_de($form, $lista, $campos);
    }
} else {
    $f = $base;
}

/* ---------- Auxiliares de exibição ---------- */

/**
 * Para a lista de erros do topo: 'categorias.1.valor' → ['categorias', 'Categorias, item 2'].
 * Devolve a âncora da seção e um texto que diz onde está o campo.
 */
function onde_esta_o_erro(string $caminho): array
{
    $secoes = [
        'geral' => ['geral', 'Geral'], 'inscricoes' => ['inscricoes', 'Inscrições'], 'contato' => ['contatos', 'Contatos'],
        'camiseta' => ['camiseta', 'Camiseta'], 'categorias' => ['categorias', 'Categorias'],
        'premiacoes' => ['premiacoes', 'Premiações'], 'rotas' => ['rotas', 'Rotas'], 'programacao' => ['programacao', 'Programação'],
        'faq' => ['faq', 'Perguntas frequentes'], 'patrocinadores' => ['patrocinadores', 'Patrocinadores'],
    ];
    $partes = explode('.', $caminho);
    [$ancora, $rotulo] = $secoes[$partes[0]] ?? ['geral', 'Geral'];
    if (isset($partes[1]) && ctype_digit($partes[1])) {
        $rotulo .= ', item ' . ((int) $partes[1] + 1);
    }
    return [$ancora, $rotulo];
}

/** 'faq.3.p' → 'faq[3][p]' */
function nome_de(string $caminho): string
{
    $partes = explode('.', $caminho);
    return array_shift($partes) . ($partes ? '[' . implode('][', $partes) . ']' : '');
}

function valor_de(array $f, string $caminho): string
{
    $v = $f;
    foreach (explode('.', $caminho) as $p) {
        if (!is_array($v) || !array_key_exists($p, $v)) {
            return '';
        }
        $v = $v[$p];
    }
    return is_scalar($v) ? (string) $v : '';
}

/** Campo com rótulo e, se houver, a mensagem de erro logo abaixo. $tipo: text, date, number, email, textarea. */
function adm_campo(array $f, array $erros, string $caminho, string $rotulo, string $tipo = 'text', string $extra = ''): string
{
    $erro  = $erros[$caminho] ?? '';
    $nome  = e(nome_de($caminho));
    $valor = e(valor_de($f, $caminho));
    $inv   = $erro !== '' ? ' aria-invalid="true"' : '';
    $html  = '<label class="adm-campo' . ($erro !== '' ? ' adm-campo--erro' : '') . '"><span>' . e($rotulo) . '</span>';
    if ($tipo === 'textarea') {
        $html .= '<textarea name="' . $nome . '" rows="3"' . $inv . ' ' . $extra . '>' . $valor . '</textarea>';
    } else {
        $html .= '<input type="' . $tipo . '" name="' . $nome . '" value="' . $valor . '"' . $inv . ' ' . $extra . '>';
    }
    if ($erro !== '') {
        $html .= '<small class="adm-campo__erro">' . e($erro) . '</small>';
    }
    return $html . '</label>';
}

/** Botões subir/descer/remover de um item de lista. */
function botoes_item(bool $pode_remover = true): string
{
    $html = '<div class="adm-item__botoes">'
          . '<button type="button" class="adm-btn adm-btn--claro adm-btn--mini" data-acao="subir" title="Subir" aria-label="Subir">↑</button>'
          . '<button type="button" class="adm-btn adm-btn--claro adm-btn--mini" data-acao="descer" title="Descer" aria-label="Descer">↓</button>';
    if ($pode_remover) {
        $html .= '<button type="button" class="adm-btn adm-btn--perigo adm-btn--mini" data-acao="remover">Remover</button>';
    }
    return $html . '</div>';
}

/** Mensagem de erro geral de uma lista (ex.: 'categorias'). */
function erro_lista(array $erros, string $chave): string
{
    return isset($erros[$chave]) ? '<p class="adm-aviso adm-aviso--erro">' . e($erros[$chave]) . '</p>' : '';
}

/* ---------- Itens de cada lista (usados também como modelo para o JavaScript) ---------- */

function item_categoria(array $f, array $erros, string $i, array $inscritos): string
{
    $slug = valor_de($f, "categorias.$i.slug");
    $n = $slug !== '' ? ($inscritos[$slug] ?? 0) : 0;
    $nota = $n > 0 ? '<p class="adm-nota">' . $n . ' inscrito' . ($n > 1 ? 's' : '') . ' – não pode ser excluída.</p>' : '';
    return '<fieldset class="adm-item" data-item>' . botoes_item($n === 0) . $nota
        . '<input type="hidden" name="' . e(nome_de("categorias.$i.slug")) . '" value="' . e($slug) . '">'
        . '<div class="adm-grade">'
        . adm_campo($f, $erros, "categorias.$i.nome", 'Nome', 'text', 'maxlength="60"')
        . adm_campo($f, $erros, "categorias.$i.valor", 'Valor (R$)', 'text', 'inputmode="decimal" placeholder="60,00"')
        . '</div>'
        . adm_campo($f, $erros, "categorias.$i.resumo", 'Resumo', 'text', 'maxlength="300"')
        . '</fieldset>';
}

function item_premiacao(array $f, array $erros, string $i): string
{
    return '<fieldset class="adm-item" data-item>' . botoes_item()
        . adm_campo($f, $erros, "premiacoes.$i.titulo", 'Título', 'text', 'maxlength="120"')
        . adm_campo($f, $erros, "premiacoes.$i.regra", 'Regra', 'text', 'maxlength="300"')
        . adm_campo($f, $erros, "premiacoes.$i.premio", 'Prêmio', 'text', 'maxlength="300"')
        . '</fieldset>';
}

function item_rota(array $f, array $erros, string $i): string
{
    return '<fieldset class="adm-item" data-item>' . botoes_item()
        . adm_campo($f, $erros, "rotas.$i.nome", 'Nome', 'text', 'maxlength="120"')
        . '<div class="adm-grade">'
        . adm_campo($f, $erros, "rotas.$i.distancia", 'Distância', 'text', 'maxlength="30" placeholder="42 km"')
        . adm_campo($f, $erros, "rotas.$i.altimetria", 'Altimetria', 'text', 'maxlength="30" placeholder="860 m"')
        . '</div>'
        . adm_campo($f, $erros, "rotas.$i.descricao", 'Descrição', 'textarea', 'maxlength="300"')
        . adm_campo($f, $erros, "rotas.$i.mapa", 'Mapa do Strava (link da rota ou código de incorporação)', 'text',
            'placeholder="https://www.strava.com/routes/..."')
        . '</fieldset>';
}

function item_programacao(array $f, array $erros, string $i): string
{
    return '<fieldset class="adm-item" data-item>' . botoes_item()
        . '<div class="adm-grade adm-grade--hora">'
        . adm_campo($f, $erros, "programacao.$i.hora", 'Hora', 'text', 'maxlength="5" placeholder="07:30"')
        . adm_campo($f, $erros, "programacao.$i.titulo", 'Título', 'text', 'maxlength="120"')
        . '</div>'
        . adm_campo($f, $erros, "programacao.$i.desc", 'Descrição', 'text', 'maxlength="300"')
        . '</fieldset>';
}

function item_faq(array $f, array $erros, string $i): string
{
    return '<fieldset class="adm-item" data-item>' . botoes_item()
        . adm_campo($f, $erros, "faq.$i.p", 'Pergunta', 'text', 'maxlength="200"')
        . adm_campo($f, $erros, "faq.$i.r", 'Resposta', 'textarea', 'maxlength="2000"')
        . '</fieldset>';
}

/**
 * Patrocinador: prévia do logo, arquivo para enviar/trocar, nome e link.
 * O caminho do logo já guardado vai num campo escondido; um arquivo novo escolhido o substitui ao salvar.
 */
function item_patrocinador(array $f, array $erros, string $i): string
{
    $logo = valor_de($f, "patrocinadores.$i.logo");
    $erro = $erros["patrocinadores.$i.logo"] ?? '';
    $existe = $logo !== '' && patro_logo_valido($logo, patro_raiz());
    if ($logo !== '' && !$existe && $erro === '') {
        $erro = 'O arquivo deste logo não está mais no servidor. Envie o logo de novo.';
    }
    $previa = $existe ? ' src="../' . e(patro_url($logo)) . '"' : '';
    return '<fieldset class="adm-item adm-patro" data-item>' . botoes_item()
        . '<div class="adm-patro__logo">'
        . '<img class="adm-patro__previa" data-logo-previa alt=""' . $previa . ($existe ? '' : ' hidden') . '>'
        . '<input type="hidden" name="' . e(nome_de("patrocinadores.$i.logo")) . '" value="' . e($logo) . '">'
        . '<label class="adm-campo' . ($erro !== '' ? ' adm-campo--erro' : '') . '"><span>' . ($logo === '' ? 'Enviar logo' : 'Trocar logo') . '</span>'
        . '<input type="file" name="' . e(nome_de("patrocinadores.$i.arquivo")) . '" accept="image/png,image/jpeg,image/webp" data-logo-arquivo'
        . ($erro !== '' ? ' aria-invalid="true"' : '') . '>'
        . '<small class="adm-campo__erro" data-logo-aviso' . ($erro === '' ? ' hidden' : '') . '>' . e($erro) . '</small>'
        . '</label></div>'
        . '<div class="adm-grade">'
        . adm_campo($f, $erros, "patrocinadores.$i.nome", 'Nome', 'text', 'maxlength="100"')
        . adm_campo($f, $erros, "patrocinadores.$i.url", 'Link (opcional)', 'text', 'placeholder="https://"')
        . '</div>'
        . '</fieldset>';
}

/** Modelo de camiseta: nome e preço (o identificador escondido não muda depois de criado). */
function item_modelo(array $f, array $erros, string $i): string
{
    return '<fieldset class="adm-item" data-item>' . botoes_item()
        . '<input type="hidden" name="' . e(nome_de("camiseta.modelos.$i.slug")) . '" value="' . e(valor_de($f, "camiseta.modelos.$i.slug")) . '">'
        . '<div class="adm-grade">'
        . adm_campo($f, $erros, "camiseta.modelos.$i.nome", 'Modelo', 'text', 'maxlength="40" placeholder="Manga longa"')
        . adm_campo($f, $erros, "camiseta.modelos.$i.valor", 'Valor (R$)', 'text', 'inputmode="decimal" placeholder="130,00"')
        . '</div></fieldset>';
}

function item_tamanho(array $f, array $erros, string $i): string
{
    return '<div class="adm-item adm-item--linha" data-item>'
        . adm_campo($f, $erros, "camiseta.tamanhos.$i", 'Tamanho', 'text', 'maxlength="4"')
        . botoes_item() . '</div>';
}

/** Uma lista editável: itens atuais, botão de adicionar e o modelo para o JavaScript. */
function lista(string $nome, string $prefixo, array $itens, callable $item, string $rotulo_adicionar): string
{
    $html = '<div class="adm-lista" data-lista="' . e($nome) . '" data-prefixo="' . e($prefixo) . '">';
    foreach (array_keys($itens) as $i) {
        $html .= $item((string) $i);
    }
    $html .= '</div><template data-modelo="' . e($nome) . '">' . $item('__i__') . '</template>'
           . '<button type="button" class="adm-btn adm-btn--claro adm-lista__adicionar" data-adicionar="' . e($nome) . '">+ ' . e($rotulo_adicionar) . '</button>';
    return $html;
}

$vazio = [];  // formulário vazio, para os modelos de item novo
$secoes = [
    'geral' => 'Geral', 'inscricoes' => 'Inscrições', 'contatos' => 'Contatos', 'camiseta' => 'Camiseta',
    'categorias' => 'Categorias', 'premiacoes' => 'Premiações', 'rotas' => 'Rotas', 'programacao' => 'Programação',
    'faq' => 'Perguntas', 'patrocinadores' => 'Patrocinadores', 'historico' => 'Histórico',
];

$titulo_admin = 'Dados do Evento';
$menu_ativo   = 'dados';
require __DIR__ . '/_topo.php';
?>

<div class="adm-titulo-linha">
    <h1>Dados do Evento</h1>
    <p class="adm-nota">
        <?php if ($atual): ?>
            Última alteração: <?= e(data_hora_br($atual['salvo_em'])) ?> por <?= e($atual['salvo_por']) ?>
        <?php else: ?>
            Valores iniciais do arquivo de configuração (ainda não salvos pelo painel).
        <?php endif; ?>
    </p>
</div>

<nav class="adm-secoes" aria-label="Seções">
    <?php foreach ($secoes as $id => $rotulo): ?>
        <a href="#<?= e($id) ?>"><?= e($rotulo) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($erros): ?>
    <div class="adm-aviso adm-aviso--erro" role="alert">
        <?php if (isset($erros['_'])): ?>
            <?= e($erros['_']) ?>
        <?php else: ?>
            <strong>Nada foi salvo.</strong> Corrija os pontos abaixo (também marcados em vermelho em cada campo):
            <ul class="adm-erros-lista">
                <?php foreach ($erros as $caminho => $msg): ?>
                    <?php [$ancora, $onde] = onde_esta_o_erro((string) $caminho); ?>
                    <li><a href="#<?= e($ancora) ?>"><?= e($onde) ?></a>: <?= e($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($pdo): ?>
<form method="post" class="adm-form adm-dados-form" enctype="multipart/form-data" data-dados novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="versao" value="<?= $form !== null ? e(post_txt('versao')) : (int) ($atual['id'] ?? 0) ?>">

    <section id="geral" class="adm-secao">
        <h2>Geral</h2>
        <?= adm_campo($f, $erros, 'geral.nome', 'Nome do evento', 'text', 'maxlength="150"') ?>
        <?= adm_campo($f, $erros, 'geral.organizador', 'Organizador', 'text', 'maxlength="150"') ?>
        <div class="adm-grade">
            <?= adm_campo($f, $erros, 'geral.data_iso', 'Data do evento', 'date') ?>
            <?= adm_campo($f, $erros, 'geral.local_largada', 'Local de largada', 'text', 'maxlength="150"') ?>
            <?= adm_campo($f, $erros, 'geral.cidade', 'Cidade', 'text', 'maxlength="100"') ?>
            <?= adm_campo($f, $erros, 'geral.uf', 'UF', 'text', 'maxlength="2" style="text-transform:uppercase"') ?>
        </div>
    </section>

    <section id="inscricoes" class="adm-secao">
        <h2>Inscrições</h2>
        <label class="adm-check">
            <input type="checkbox" name="inscricoes[abertas]" value="1"<?= valor_de($f, 'inscricoes.abertas') !== '' ? ' checked' : '' ?>>
            Inscrições abertas <small>(desmarque para fechar; com início programado, abrem sozinhas no dia e hora marcados)</small>
        </label>
        <label class="adm-check">
            <input type="checkbox" name="inscricoes[lista_publica]" value="1"<?= valor_de($f, 'inscricoes.lista_publica') !== '' ? ' checked' : '' ?>>
            Mostrar a lista de inscritos no site <small>(página Inscritos, só as pagas: nome, cidade e equipe)</small>
        </label>
        <div class="adm-grade">
            <?= adm_campo($f, $erros, 'inscricoes.inicio', 'Início das inscrições (data e hora; em branco = abrem já)', 'datetime-local') ?>
            <?= adm_campo($f, $erros, 'inscricoes.prazo_iso', 'Último dia para se inscrever (em branco = sem prazo)', 'date') ?>
            <?= adm_campo($f, $erros, 'inscricoes.vagas', 'Número de vagas (0 = sem limite)', 'number', 'min="0" step="1"') ?>
            <?= adm_campo($f, $erros, 'inscricoes.taxa', 'Taxa de administração (%)', 'text', 'inputmode="decimal"') ?>
        </div>
        <?php if (isset($erros['inscricoes.confirmar'])): ?>
            <div class="adm-aviso adm-aviso--erro">
                <p><?= e($erros['inscricoes.confirmar']) ?></p>
                <label class="adm-check"><input type="checkbox" name="confirmar_abertura" value="1"> Sim, abrir as inscrições agora</label>
            </div>
        <?php endif; ?>
        <p class="adm-nota">Ocupando vaga agora: <?= (int) $ocupadas ?> inscrições (pagas e pendentes; canceladas liberam a vaga).</p>
    </section>

    <section id="contatos" class="adm-secao">
        <h2>Contatos</h2>
        <div class="adm-grade">
            <?= adm_campo($f, $erros, 'contato.email', 'E-mail', 'email', 'maxlength="150"') ?>
            <?= adm_campo($f, $erros, 'contato.whatsapp', 'WhatsApp (com DDD)', 'text', 'inputmode="tel"') ?>
        </div>
        <?= adm_campo($f, $erros, 'contato.instagram', 'Instagram (link completo, opcional)', 'text', 'placeholder="https://instagram.com/..."') ?>
        <?= adm_campo($f, $erros, 'contato.grupo_whatsapp', 'Grupo de WhatsApp (link de convite; em branco, o atalho some da home)', 'text', 'placeholder="https://chat.whatsapp.com/..."') ?>
    </section>

    <section id="camiseta" class="adm-secao">
        <h2>Camiseta</h2>
        <div class="adm-grade">
            <?= adm_campo($f, $erros, 'camiseta.prazo_iso', 'Último dia para comprar', 'date') ?>
        </div>
        <h3>Modelos e preços</h3>
        <p class="adm-nota">O atleta escolhe o modelo e o tamanho na inscrição. A camiseta não tem taxa nem desconto de cupom.</p>
        <?= erro_lista($erros, 'camiseta.modelos') ?>
        <?= lista('modelos', 'camiseta[modelos]', $f['camiseta']['modelos'] ?? [], function ($i) use ($f, $erros) {
            return item_modelo($i === '__i__' ? [] : $f, $erros, $i);
        }, 'Adicionar modelo') ?>
        <h3>Tamanhos</h3>
        <?= erro_lista($erros, 'camiseta.tamanhos') ?>
        <?= lista('tamanhos', 'camiseta[tamanhos]', $f['camiseta']['tamanhos'], function ($i) use ($f, $erros) {
            return item_tamanho($i === '__i__' ? [] : $f, $erros, $i);
        }, 'Adicionar tamanho') ?>
    </section>

    <section id="categorias" class="adm-secao">
        <h2>Categorias</h2>
        <p class="adm-nota">Inscrições já feitas guardam o valor que pagaram. Categorias com inscritos não podem ser excluídas.</p>
        <?= erro_lista($erros, 'categorias') ?>
        <?= lista('categorias', 'categorias', $f['categorias'], function ($i) use ($f, $erros, $inscritos, $vazio) {
            return item_categoria($i === '__i__' ? $vazio : $f, $erros, $i, $inscritos);
        }, 'Adicionar categoria') ?>
    </section>

    <section id="premiacoes" class="adm-secao">
        <h2>Premiações</h2>
        <?= lista('premiacoes', 'premiacoes', $f['premiacoes'], function ($i) use ($f, $erros, $vazio) {
            return item_premiacao($i === '__i__' ? $vazio : $f, $erros, $i);
        }, 'Adicionar premiação') ?>
    </section>

    <section id="rotas" class="adm-secao">
        <h2>Rotas</h2>
        <p class="adm-nota">No Strava: abra a rota e copie o link do navegador, ou use "..." → Embed e copie o código.</p>
        <?= lista('rotas', 'rotas', $f['rotas'], function ($i) use ($f, $erros, $vazio) {
            return item_rota($i === '__i__' ? $vazio : $f, $erros, $i);
        }, 'Adicionar rota') ?>
    </section>

    <section id="programacao" class="adm-secao">
        <h2>Programação</h2>
        <?= lista('programacao', 'programacao', $f['programacao'], function ($i) use ($f, $erros, $vazio) {
            return item_programacao($i === '__i__' ? $vazio : $f, $erros, $i);
        }, 'Adicionar horário') ?>
    </section>

    <section id="faq" class="adm-secao">
        <h2>Perguntas frequentes</h2>
        <?= lista('faq', 'faq', $f['faq'], function ($i) use ($f, $erros, $vazio) {
            return item_faq($i === '__i__' ? $vazio : $f, $erros, $i);
        }, 'Adicionar pergunta') ?>
    </section>

    <section id="patrocinadores" class="adm-secao">
        <h2>Patrocinadores</h2>
        <p class="adm-nota">Envie PNG, JPG ou WEBP de até 2 MB, de preferência com fundo transparente e uns 400 px de largura.
            A ordem aqui é a ordem do carrossel da página inicial.</p>
        <?= erro_lista($erros, 'patrocinadores') ?>
        <?= lista('patrocinadores', 'patrocinadores', $f['patrocinadores'], function ($i) use ($f, $erros, $vazio) {
            return item_patrocinador($i === '__i__' ? $vazio : $f, $erros, $i);
        }, 'Adicionar patrocinador') ?>
    </section>

    <div class="adm-salvar-barra">
        <button type="submit" class="adm-btn" data-salvar>Salvar alterações</button>
    </div>
</form>

<section id="historico" class="adm-secao">
    <h2>Histórico</h2>
    <?php if (!$historico): ?>
        <p class="adm-nota">Nenhuma versão salva ainda.</p>
    <?php else: ?>
        <div class="adm-rolagem">
            <table class="adm-tabela adm-tabela--compacta">
                <thead><tr><th>Versão</th><th>Quando</th><th>Quem</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($historico as $n => $h): ?>
                    <tr>
                        <td>
                            <?= (int) $h['id'] ?>
                            <?php if ($h['restaurada_de']): ?>
                                <small class="adm-nota">restaurada da versão <?= (int) $h['restaurada_de'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e(data_hora_br($h['salvo_em'])) ?></td>
                        <td><?= e($h['salvo_por']) ?></td>
                        <td>
                            <?php if ($n === 0): ?>
                                <span class="adm-selo adm-selo--pago">Em vigor</span>
                            <?php else: ?>
                                <form method="post" onsubmit="return confirm('Restaurar a versão <?= (int) $h['id'] ?>? O site passa a mostrar os dados dela.');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="restaurar">
                                    <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                                    <button type="submit" class="adm-btn adm-btn--claro adm-btn--mini" data-restaurar="<?= (int) $h['id'] ?>">Restaurar esta versão</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="adm-nota">São guardadas as <?= EVENTO_VERSOES_MAX ?> versões mais recentes.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<script src="../<?= e(asset('assets/js/admin-dados.js')) ?>"></script>
<?php require __DIR__ . '/_rodape.php'; ?>
