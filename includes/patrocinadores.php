<?php
/**
 * Logos dos patrocinadores enviados pelo painel: conferência do arquivo, onde fica guardado e limpeza.
 * Os envios ficam em uploads/patrocinadores/ com nome sorteado pelo site; o nome original nunca vira nome de arquivo.
 */

const PATRO_ENVIOS    = 'uploads/patrocinadores';
const PATRO_MAX_BYTES = 2 * 1024 * 1024;
const PATRO_PASTA_ANTIGA = 'assets/img/patrocinadores';

/** Raiz do site (pasta acima de includes/). */
function patro_raiz(): string
{
    return dirname(__DIR__);
}

/**
 * Tipo real da imagem, lido do conteúdo (não da extensão): 'png', 'jpg', 'webp' ou null.
 * getimagesize só lê o cabeçalho e funciona sem a extensão GD.
 */
function patro_tipo_imagem(string $caminho): ?string
{
    if (!is_file($caminho)) {
        return null;
    }
    $info = @getimagesize($caminho);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
    }
    $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
    return $tipos[$info[2]] ?? null;
}

/**
 * Mensagem para um envio de imagem recusado, ou null se estiver tudo certo.
 * $coisa: 'o logo' ou 'a foto' (para as mensagens); $max em bytes.
 */
function envio_erro(int $erro_upload, int $tamanho, ?string $tipo, int $max, string $coisa): ?string
{
    [$artigo, $nome] = explode(' ', $coisa, 2);
    if (in_array($erro_upload, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $tamanho > $max) {
        return ucfirst($coisa) . ' passa de ' . (int) round($max / 1048576) . ' MB.';
    }
    if ($erro_upload !== UPLOAD_ERR_OK) {
        return 'O envio ' . ($artigo === 'a' ? 'da ' : 'do ') . $nome . ' falhou. Tente de novo.';
    }
    if ($tipo === null) {
        return 'Use PNG, JPG ou WEBP.';
    }
    return null;
}

/** Mensagem para um logo recusado, ou null se estiver tudo certo. */
function patro_erro_envio(int $erro_upload, int $tamanho, ?string $tipo): ?string
{
    return envio_erro($erro_upload, $tamanho, $tipo, PATRO_MAX_BYTES, 'o logo');
}

/** O caminho aponta para um logo enviado pelo painel ou para um da pasta antiga, e o arquivo existe? */
function patro_logo_valido(string $logo, string $raiz): bool
{
    $enviado = (bool) preg_match('#^' . PATRO_ENVIOS . '/[a-f0-9]{16}\.(png|jpg|webp)$#', $logo);
    $antigo  = false;
    if (preg_match('#^' . PATRO_PASTA_ANTIGA . '/([^/\\\\]+)$#', $logo, $m)) {
        $arq = $m[1];
        $antigo = $arq[0] !== '.' && strpos($arq, '..') === false
            && in_array(strtolower(pathinfo($arq, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'], true);
    }
    return ($enviado || $antigo) && is_file($raiz . '/' . $logo);
}

/** Endereço do logo para usar no HTML (o nome do arquivo pode ter espaço). */
function patro_url(string $logo): string
{
    return dirname($logo) . '/' . rawurlencode(basename($logo));
}

/**
 * Guarda um logo enviado (uma entrada de $_FILES: name, tmp_name, error, size).
 * Devolve [] se nenhum arquivo foi escolhido, ['logo' => caminho] se guardou, ou ['erro' => mensagem].
 */
function patro_guardar_envio(array $arquivo, string $raiz): array
{
    $r = envio_guardar($arquivo, $raiz, PATRO_ENVIOS, PATRO_MAX_BYTES, 'o logo');
    return isset($r['caminho']) ? ['logo' => $r['caminho']] : $r;
}

/**
 * Guarda uma imagem enviada pelo painel em $pasta (relativa à raiz), com nome sorteado.
 * Devolve [] se nenhum arquivo foi escolhido, ['caminho' => ...] se guardou, ou ['erro' => mensagem].
 */
function envio_guardar(array $arquivo, string $raiz, string $pasta_rel, int $max, string $coisa): array
{
    $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($erro === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    $tmp     = (string) ($arquivo['tmp_name'] ?? '');
    $existe  = $erro === UPLOAD_ERR_OK && $tmp !== '' && is_file($tmp);
    $tamanho = max((int) ($arquivo['size'] ?? 0), $existe ? (int) filesize($tmp) : 0);
    $tipo    = $existe ? patro_tipo_imagem($tmp) : null;

    $msg = envio_erro($erro, $tamanho, $tipo, $max, $coisa);
    if ($msg !== null) {
        return ['erro' => $msg];
    }
    if (!is_uploaded_file($tmp)) {
        return ['erro' => envio_erro(UPLOAD_ERR_PARTIAL, 0, null, $max, $coisa)];
    }

    $pasta = $raiz . '/' . $pasta_rel;
    if (!is_dir($pasta) && !@mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        return ['erro' => 'Não foi possível guardar ' . $coisa . ' no servidor.'];
    }
    $caminho = $pasta_rel . '/' . bin2hex(random_bytes(8)) . '.' . $tipo;
    if (!@move_uploaded_file($tmp, $raiz . '/' . $caminho)) {
        return ['erro' => 'Não foi possível guardar ' . $coisa . ' no servidor.'];
    }
    @chmod($raiz . '/' . $caminho, 0644);
    return ['caminho' => $caminho];
}

/**
 * Arquivos que podem ser apagados: nenhuma versão guardada os cita e têm pelo menos $idade_min segundos
 * (os mais novos podem estar num formulário que ainda vai ser salvo).
 * $arquivos: nome => data de modificação (timestamp).
 */
function patro_orfaos(array $arquivos, array $textos_versoes, int $agora, int $idade_min = 86400): array
{
    $orfaos = [];
    foreach ($arquivos as $nome => $mtime) {
        $nome = (string) $nome;
        if ($agora - (int) $mtime < $idade_min) {
            continue;
        }
        $citado = false;
        foreach ($textos_versoes as $texto) {
            if (strpos((string) $texto, $nome) !== false) {
                $citado = true;
                break;
            }
        }
        if (!$citado) {
            $orfaos[] = $nome;
        }
    }
    return $orfaos;
}

/** Apaga da pasta de envios os logos que nenhuma versão usa mais. Devolve quantos apagou. */
function patro_limpar_envios(PDO $pdo, string $raiz): int
{
    return envio_limpar_pasta($pdo, $raiz, PATRO_ENVIOS);
}

/** Apaga de uma pasta de envios do painel as imagens que nenhuma versão dos Dados do Evento cita. */
function envio_limpar_pasta(PDO $pdo, string $raiz, string $pasta_rel): int
{
    $pasta = $raiz . '/' . $pasta_rel;
    if (!is_dir($pasta)) {
        return 0;
    }
    $arquivos = [];
    foreach (scandir($pasta) ?: [] as $nome) {
        if (preg_match('/^[a-f0-9]{16}\.(png|jpg|webp)$/', $nome)) {
            $arquivos[$nome] = (int) filemtime($pasta . '/' . $nome);
        }
    }
    if (!$arquivos) {
        return 0;
    }
    $textos = $pdo->query('SELECT dados FROM evento_versoes')->fetchAll(PDO::FETCH_COLUMN);
    $apagados = 0;
    foreach (patro_orfaos($arquivos, $textos, time()) as $nome) {
        if (@unlink($pasta . '/' . $nome)) {
            $apagados++;
        }
    }
    return $apagados;
}

/**
 * Recebe os logos enviados no formulário de Dados do Evento (campos patrocinadores[N][arquivo]).
 * Cada logo guardado entra no item (patrocinadores[N][logo]); itens totalmente em branco saem da lista,
 * para que os números dos itens batam com os da validação (itens_de também descarta os em branco).
 * Devolve [entrada com a lista pronta, erros 'patrocinadores.N.logo' (ou 'patrocinadores' para item em branco)].
 */
function patro_receber_envios(array $post, array $files, string $raiz): array
{
    $itens = is_array($post['patrocinadores'] ?? null) ? $post['patrocinadores'] : [];
    $f = is_array($files['patrocinadores'] ?? null) ? $files['patrocinadores'] : null;
    $lista = [];
    $erros = [];
    foreach ($itens as $chave => $item) {
        if (!is_array($item)) {
            continue;
        }
        $item = [
            'logo' => trim((string) ($item['logo'] ?? '')),
            'nome' => (string) ($item['nome'] ?? ''),
            'url'  => (string) ($item['url'] ?? ''),
        ];
        $envio = [];
        if ($f !== null && isset($f['error'][$chave]['arquivo']) && !is_array($f['error'][$chave]['arquivo'])) {
            $envio = patro_guardar_envio([
                'name'     => (string) ($f['name'][$chave]['arquivo'] ?? ''),
                'tmp_name' => (string) ($f['tmp_name'][$chave]['arquivo'] ?? ''),
                'error'    => (int) $f['error'][$chave]['arquivo'],
                'size'     => (int) ($f['size'][$chave]['arquivo'] ?? 0),
            ], $raiz);
        }
        if (isset($envio['logo'])) {
            $item['logo'] = $envio['logo'];
        }
        if ($item['logo'] === '' && trim($item['nome']) === '' && trim($item['url']) === '') {
            if (isset($envio['erro'])) {
                $erros['patrocinadores'] = 'Um logo foi recusado: ' . $envio['erro'] . ' Esse patrocinador estava sem nome e não foi incluído.';
            }
            continue;
        }
        if (isset($envio['erro'])) {
            $erros['patrocinadores.' . count($lista) . '.logo'] = $envio['erro'];
        }
        $lista[] = $item;
    }
    $post['patrocinadores'] = $lista;
    return [$post, $erros];
}
