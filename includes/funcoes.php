<?php
/**
 * Funções auxiliares usadas em todas as páginas.
 */

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/patrocinadores.php';
require_once __DIR__ . '/causa.php';

// No ambiente local (debug ligado em config/banco.php), mostra os erros do PHP na tela.
(function () {
    $cfg = @include __DIR__ . '/../config/banco.php';
    if (is_array($cfg) && !empty($cfg['debug'])) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }
})();

/** Valores padrão do evento, do arquivo config/evento.php (sem o que foi salvo no painel). */
function evento_padrao(): array
{
    static $dados = null;
    if ($dados === null) {
        $dados = require __DIR__ . '/../config/evento.php';
    }
    return $dados;
}

/**
 * Dados do evento (carregados uma única vez por requisição): o arquivo de config como padrão e, por cima,
 * o que foi salvo em Painel → Dados do Evento. Se o banco falhar, o site segue com o arquivo.
 */
function evento(): array
{
    static $dados = null;
    if ($dados === null) {
        $dados = evento_padrao();
        try {
            require_once __DIR__ . '/evento_dados.php';
            $atual = evento_versao_atual(db());
            if ($atual) {
                $dados = evento_mesclar($dados, $atual['dados']);
            }
        } catch (Throwable $e) {
            error_log('Pedal Solidário – dados do evento: usando config/evento.php (' . $e->getMessage() . ')');
        }
        $dados = array_merge($dados, evento_datas((string) ($dados['data_iso'] ?? '')));
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

/**
 * Modelos da camiseta (ex.: manga curta e manga longa), cada um com o seu preço:
 * lista de ['slug' => ..., 'nome' => ..., 'valor' => float]. Dados antigos (só 'valor') viram um modelo "Camiseta".
 */
function camiseta_modelos(array $cam): array
{
    if (isset($cam['modelos']) && is_array($cam['modelos'])) {
        $modelos = [];
        foreach ($cam['modelos'] as $m) {
            if (is_array($m) && (string) ($m['slug'] ?? '') !== '') {
                $modelos[] = ['slug' => (string) $m['slug'], 'nome' => (string) ($m['nome'] ?? ''), 'valor' => (float) ($m['valor'] ?? 0)];
            }
        }
        return $modelos;
    }
    return [['slug' => 'camiseta', 'nome' => 'Camiseta', 'valor' => (float) ($cam['valor'] ?? 0)]];
}

/** Um modelo da camiseta do evento atual pelo identificador, ou null. */
function camiseta_modelo(string $slug): ?array
{
    foreach (camiseta_modelos(evento()['camiseta'] ?? []) as $m) {
        if ($m['slug'] === $slug) {
            return $m;
        }
    }
    return null;
}

/** Nome do modelo para exibir (painel, CSV): nome atual, o identificador se o modelo foi removido, ou '—'. */
function camiseta_modelo_nome(?string $slug): string
{
    if ($slug === null || $slug === '') {
        return '—';
    }
    $m = camiseta_modelo($slug);
    return $m ? $m['nome'] : $slug;
}

/** A compra da camiseta está aberta? (vale até o fim do dia do prazo) */
function camiseta_aberta(): bool
{
    $prazo = evento()['camiseta']['prazo_iso'] ?? '';
    return $prazo !== '' && date('Y-m-d') <= $prazo;
}

/** Busca uma categoria pelo identificador (slug). */
function categoria_por_slug(string $slug): ?array
{
    foreach (evento()['categorias'] as $cat) {
        if ($cat['slug'] === $slug) {
            return $cat;
        }
    }
    return null;
}

/** Lê um campo de texto do POST de forma segura (ignora arrays). */
function post_txt(string $chave): string
{
    $v = $_POST[$chave] ?? '';
    return is_scalar($v) ? trim((string) $v) : '';
}

function so_digitos(string $texto): string
{
    return preg_replace('/\D/', '', $texto) ?? '';
}

/** Valida CPF pelos dígitos verificadores. */
function cpf_valido(string $cpf): bool
{
    $cpf = so_digitos($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * ($t + 1 - $i);
        }
        $dig = (($soma * 10) % 11) % 10;
        if ($dig !== (int) $cpf[$t]) {
            return false;
        }
    }
    return true;
}

/** Token anti-CSRF guardado na sessão. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(string $token): bool
{
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Formata CPF 00000000000 → 000.000.000-00 */
function cpf_formatado(string $cpf): string
{
    $c = so_digitos($cpf);
    return strlen($c) === 11
        ? substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2)
        : $cpf;
}

/** Patrocinadores do carrossel da página inicial (logo, endereço da imagem, nome e link). */
function patrocinadores(): array
{
    return patrocinadores_de(evento(), patro_raiz());
}

/**
 * Patrocinadores a partir dos dados do evento:
 *  - com a lista 'patrocinadores' (cuidada no painel): segue a ordem dela e pula logo inválido ou que sumiu;
 *  - sem a lista (nada salvo ainda no formato novo): lê a pasta assets/img/patrocinadores/, em ordem de nome,
 *    com nome e link opcionais de 'patrocinadores_info' (formato antigo).
 * Cada item: ['logo' => caminho, 'arquivo' => endereço para o HTML, 'nome' => texto, 'url' => link ou ''].
 */
function patrocinadores_de(array $ev, string $raiz): array
{
    $link = function ($url): string {
        $url = (string) $url;
        return preg_match('#^https?://#i', $url) ? $url : '';
    };

    if (isset($ev['patrocinadores']) && is_array($ev['patrocinadores'])) {
        $lista = [];
        foreach ($ev['patrocinadores'] as $p) {
            $logo = is_array($p) ? (string) ($p['logo'] ?? '') : '';
            if (!patro_logo_valido($logo, $raiz)) {
                continue;
            }
            $nome = trim((string) ($p['nome'] ?? ''));
            $lista[] = ['logo' => $logo, 'arquivo' => patro_url($logo), 'nome' => $nome !== '' ? $nome : 'Patrocinador', 'url' => $link($p['url'] ?? '')];
        }
        return $lista;
    }

    $pasta = $raiz . '/' . PATRO_PASTA_ANTIGA;
    if (!is_dir($pasta)) {
        return [];
    }

    $permitidas = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'];
    $info       = is_array($ev['patrocinadores_info'] ?? null) ? $ev['patrocinadores_info'] : [];
    $arquivos   = [];

    foreach (scandir($pasta) ?: [] as $arq) {
        if ($arq === '' || $arq[0] === '.') {
            continue;
        }
        $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
        if (in_array($ext, $permitidas, true) && is_file($pasta . '/' . $arq)) {
            $arquivos[] = $arq;
        }
    }
    natcasesort($arquivos);

    $lista = [];
    foreach ($arquivos as $arq) {
        $nome = pathinfo($arq, PATHINFO_FILENAME);
        $nome = preg_replace('/^\d+[\s._-]*/', '', $nome) ?? $nome;   // tira o "01-" do começo
        $nome = trim(str_replace(['_', '-'], ' ', $nome));

        $extra = is_array($info[$arq] ?? null) ? $info[$arq] : [];
        $logo  = PATRO_PASTA_ANTIGA . '/' . $arq;

        $lista[] = [
            'logo'    => $logo,
            'arquivo' => patro_url($logo),
            'nome'    => (string) ($extra['nome'] ?? ($nome !== '' ? $nome : 'Patrocinador')),
            'url'     => $link($extra['url'] ?? ''),
        ];
    }
    return $lista;
}

/** Percentual da taxa de administração (ex.: 10.0). */
function taxa_percentual(): float
{
    return (float) (evento()['taxa_administracao']['percentual'] ?? 0);
}

/** "10%" */
function taxa_rotulo(): string
{
    return rtrim(rtrim(number_format(taxa_percentual(), 2, ',', ''), '0'), ',') . '%';
}

/**
 * Taxa de administração sobre o valor CHEIO da inscrição (antes do cupom).
 * Calculada em centavos para não ter diferença de arredondamento. Nunca incide sobre a camiseta.
 */
function taxa_administracao(float $base): float
{
    $centavos = (int) round(max(0.0, $base) * 100);
    return round($centavos * taxa_percentual() / 100) / 100;
}

/**
 * Valores finais de uma inscrição.
 *  - A taxa de administração é calculada sobre o valor CHEIO da inscrição.
 *  - O cupom abate apenas a inscrição.
 *  - Se o cupom cobre 100% da inscrição, a taxa também é isenta (sobra só a camiseta, se houver).
 *  - A camiseta nunca tem taxa nem desconto.
 * Retorna ['taxa' => float, 'total' => float, 'isenta' => bool].
 */
function calcular_valores(float $valor_inscricao, float $valor_desconto, float $valor_camiseta): array
{
    $isenta = $valor_inscricao > 0 && ($valor_desconto + 0.005) >= $valor_inscricao;
    $taxa   = $isenta ? 0.0 : taxa_administracao($valor_inscricao);
    $total  = round($valor_inscricao + $taxa - $valor_desconto + $valor_camiseta, 2);

    return ['taxa' => $taxa, 'total' => $total, 'isenta' => $isenta];
}

/**
 * Endereço de um arquivo do site com a "versão" (data de modificação) no final.
 * Sempre que o arquivo muda, o endereço muda, e o navegador do celular não usa CSS/JS antigos do cache.
 * Ex.: asset('assets/css/style.css') -> assets/css/style.css?v=1760551234
 */
function asset(string $caminho): string
{
    $arquivo = __DIR__ . '/../' . $caminho;
    $versao  = is_file($arquivo) ? (int) filemtime($arquivo) : 0;
    return $caminho . '?v=' . $versao;
}

/** Endereço do site (https://dominio/pasta) a partir do pedido atual, sem barra no fim (vale também de dentro de admin/). */
function site_base(array $server): string
{
    $https  = !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
    $pasta  = str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/')));
    $pasta  = preg_replace('#/admin$#', '', rtrim($pasta, '/'));
    return ($https ? 'https://' : 'http://') . ($server['HTTP_HOST'] ?? 'localhost') . $pasta;
}
