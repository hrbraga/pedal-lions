<?php
/**
 * Seção "Onde o valor arrecadado será usado?" da página inicial: título, texto curto e carrossel de fotos,
 * cuidados em Dados do Evento. As fotos ficam em uploads/causa/ com nome sorteado pelo site
 * (o painel já reduz a foto no navegador antes de enviar).
 * Precisa de includes/patrocinadores.php (envio_guardar, patro_tipo_imagem).
 */

const CAUSA_ENVIOS        = 'uploads/causa';
const CAUSA_MAX_BYTES     = 8 * 1024 * 1024;
const CAUSA_MAX_FOTOS     = 12;
const CAUSA_TITULO_PADRAO = 'Onde o valor arrecadado será usado?';

/** O caminho aponta para uma foto enviada pelo painel e o arquivo existe? */
function causa_foto_valida(string $foto, string $raiz): bool
{
    return (bool) preg_match('#^' . CAUSA_ENVIOS . '/[a-f0-9]{16}\.(png|jpg|webp)$#', $foto) && is_file($raiz . '/' . $foto);
}

/**
 * O que a página inicial mostra: ['titulo', 'texto', 'fotos' => [['foto', 'arquivo' (endereço para o HTML), 'legenda']]].
 * Pula foto inválida ou que sumiu do servidor; sem fotos, a seção não aparece.
 */
function causa_de(array $ev, string $raiz): array
{
    $c = is_array($ev['causa'] ?? null) ? $ev['causa'] : [];
    $titulo = trim((string) ($c['titulo'] ?? ''));
    $fotos = [];
    foreach (is_array($c['fotos'] ?? null) ? $c['fotos'] : [] as $f) {
        $foto = is_array($f) ? (string) ($f['foto'] ?? '') : '';
        if (!causa_foto_valida($foto, $raiz)) {
            continue;
        }
        $fotos[] = ['foto' => $foto, 'arquivo' => $foto, 'legenda' => trim((string) ($f['legenda'] ?? ''))];
    }
    return [
        'titulo' => $titulo !== '' ? $titulo : CAUSA_TITULO_PADRAO,
        'texto'  => trim((string) ($c['texto'] ?? '')),
        'fotos'  => $fotos,
    ];
}

/**
 * Recebe as fotos enviadas no formulário de Dados do Evento (campos causa_fotos[N][arquivo]).
 * Cada foto guardada entra no item (causa_fotos[N][foto]); itens totalmente em branco saem da lista,
 * para que os números batam com os da validação. Devolve [entrada com a lista pronta, erros].
 */
function causa_receber_envios(array $post, array $files, string $raiz): array
{
    $itens = is_array($post['causa_fotos'] ?? null) ? $post['causa_fotos'] : [];
    $f = is_array($files['causa_fotos'] ?? null) ? $files['causa_fotos'] : null;
    $lista = [];
    $erros = [];
    foreach ($itens as $chave => $item) {
        if (!is_array($item)) {
            continue;
        }
        $item = ['foto' => trim((string) ($item['foto'] ?? '')), 'legenda' => (string) ($item['legenda'] ?? '')];
        $envio = [];
        if ($f !== null && isset($f['error'][$chave]['arquivo']) && !is_array($f['error'][$chave]['arquivo'])) {
            $envio = envio_guardar([
                'name'     => (string) ($f['name'][$chave]['arquivo'] ?? ''),
                'tmp_name' => (string) ($f['tmp_name'][$chave]['arquivo'] ?? ''),
                'error'    => (int) $f['error'][$chave]['arquivo'],
                'size'     => (int) ($f['size'][$chave]['arquivo'] ?? 0),
            ], $raiz, CAUSA_ENVIOS, CAUSA_MAX_BYTES, 'a foto');
        }
        if (isset($envio['caminho'])) {
            $item['foto'] = $envio['caminho'];
        }
        if ($item['foto'] === '' && trim($item['legenda']) === '') {
            if (isset($envio['erro'])) {
                $erros['causa_fotos'] = 'Uma foto foi recusada: ' . $envio['erro'];
            }
            continue;
        }
        if (isset($envio['erro'])) {
            $erros['causa_fotos.' . count($lista) . '.foto'] = $envio['erro'];
        }
        $lista[] = $item;
    }
    $post['causa_fotos'] = $lista;
    return [$post, $erros];
}
