<?php
/**
 * Validação dos Dados do Evento enviados pelo painel e tratamento seguro do mapa do Strava.
 * Funções puras (não usam o banco); testadas em tests/evento_validar_test.php.
 */
require_once __DIR__ . '/evento_dados.php';

const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

/* ---------------------------------------------------------------------------
   Mapa do Strava: do link ou do código colado, guarda só os dados da rota
   --------------------------------------------------------------------------- */

/** Extrai id (e, se houver, token, estilo e map-hash) de um link de rota ou do código de incorporação do Strava. */
function strava_extrair(string $colado): ?array
{
    $colado = trim($colado);
    if ($colado === '') {
        return null;
    }
    $mapa = null;
    if (preg_match('/data-embed-type="route"/i', $colado) && preg_match('/data-embed-id="(\d{1,25})"/i', $colado, $m)) {
        $mapa = ['id' => $m[1]];
        $filtros = [
            'token'    => ['data-token', '/^[A-Za-z0-9_-]{1,100}$/'],
            'style'    => ['data-style', '/^[a-z-]{1,20}$/'],
            'map_hash' => ['data-map-hash', '/^[0-9.\/-]{1,60}$/'],
        ];
        foreach ($filtros as $chave => [$atributo, $permitido]) {
            if (preg_match('/' . $atributo . '="([^"]*)"/i', $colado, $m) && preg_match($permitido, $m[1])) {
                $mapa[$chave] = $m[1];
            }
        }
    } elseif (preg_match('#^(?:https?://)?(?:www\.)?strava\.com/routes/(\d{1,25})(?:[/?\#].*)?$#i', $colado, $m)) {
        $mapa = ['id' => $m[1]];
    }
    return $mapa;
}

/** Link da rota no Strava (o que aparece no campo do painel). */
function strava_link(array $mapa): string
{
    return 'https://www.strava.com/routes/' . $mapa['id'];
}

/** Marcador que o script do Strava transforma no mapa. O <script> é incluído uma vez pela página. */
function strava_html(array $mapa): string
{
    $atributos = [
        'data-embed-type' => 'route',
        'data-embed-id'   => $mapa['id'],
        'data-full-width' => 'true',
        'data-style'      => $mapa['style'] ?? 'standard',
    ];
    if (!empty($mapa['map_hash'])) {
        $atributos['data-map-hash'] = $mapa['map_hash'];
    }
    if (!empty($mapa['token'])) {
        $atributos['data-token'] = $mapa['token'];
    }
    $html = '<div class="strava-embed-placeholder"';
    foreach ($atributos as $nome => $valor) {
        $html .= ' ' . $nome . '="' . e((string) $valor) . '"';
    }
    return $html . '></div>';
}

/** Mapas já conhecidos (por id), para manter o token quando a pessoa só reenvia o link. */
function mapas_conhecidos(array $atual): array
{
    $conhecidos = [];
    foreach ($atual['rotas'] ?? [] as $rota) {
        $m = $rota['mapa'] ?? null;
        if (!$m) {
            $m = strava_extrair((string) ($rota['mapa_embed'] ?? '')) ?? strava_extrair((string) ($rota['mapa_url'] ?? ''));
        }
        if ($m) {
            $conhecidos[$m['id']] = $m;
        }
    }
    return $conhecidos;
}

/* ---------------------------------------------------------------------------
   Auxiliares
   --------------------------------------------------------------------------- */

/** Identificador de categoria: minúsculas, sem acento, com hífens; sem repetir os já usados. */
function slug_de(string $nome, array $existentes): string
{
    $s = strtr(mb_strtolower(trim($nome)), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ]);
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    if ($s === '') {
        $s = 'categoria';
    }
    $s = substr($s, 0, 26);
    $base = $s;
    for ($n = 2; in_array($s, $existentes, true); $n++) {
        $s = $base . '-' . $n;
    }
    return $s;
}

/** Texto de um campo do POST, sem espaços nas pontas. */
function campo(array $origem, string $chave): string
{
    $v = $origem[$chave] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** "70", "70,5" ou "70,50" → 70.5; inválido → null. */
function dinheiro(string $texto): ?float
{
    $texto = str_replace(',', '.', $texto);
    return preg_match('/^\d{1,6}(\.\d{1,2})?$/', $texto) ? (float) $texto : null;
}

function data_valida(string $iso): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $iso);
    return $d && $d->format('Y-m-d') === $iso;
}

/** Lista do POST como itens, descartando os totalmente em branco. */
function itens_de(array $entrada, string $secao, array $campos): array
{
    $itens = [];
    $lista = $entrada[$secao] ?? [];
    if (!is_array($lista)) {
        return [];
    }
    foreach (array_values($lista) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $limpo = [];
        $vazio = true;
        foreach ($campos as $c) {
            $limpo[$c] = campo($item, $c);
            if ($c !== 'slug' && $limpo[$c] !== '') {
                $vazio = false;
            }
        }
        if (!$vazio) {
            $itens[] = $limpo;
        }
    }
    return $itens;
}

/** Converte os dados do evento no formato do formulário do painel (o mesmo que evento_validar recebe). */
function evento_para_formulario(array $ev): array
{
    $dinheiro = function ($v) {
        return number_format((float) $v, 2, ',', '');
    };
    // Lista salva como está (inclusive item cujo arquivo sumiu: o painel mostra e pede o logo de novo, em vez de
    // apagar o patrocinador sem aviso no próximo Salvar). Sem lista salva: os logos da pasta antiga, já com nome.
    if (isset($ev['patrocinadores']) && is_array($ev['patrocinadores'])) {
        $patro = [];
        foreach ($ev['patrocinadores'] as $p) {
            if (is_array($p)) {
                $patro[] = ['logo' => (string) ($p['logo'] ?? ''), 'nome' => (string) ($p['nome'] ?? ''), 'url' => (string) ($p['url'] ?? '')];
            }
        }
    } else {
        $patro = array_map(function ($p) {
            return ['logo' => $p['logo'], 'nome' => $p['nome'], 'url' => $p['url']];
        }, patrocinadores_de($ev, patro_raiz()));
    }
    $taxa = (float) ($ev['taxa_administracao']['percentual'] ?? 0);
    return [
        'geral' => [
            'nome' => $ev['nome'] ?? '', 'organizador' => $ev['organizador'] ?? '', 'data_iso' => $ev['data_iso'] ?? '',
            'cidade' => $ev['cidade'] ?? '', 'uf' => $ev['uf'] ?? '', 'local_largada' => $ev['local_largada'] ?? '',
        ],
        'inscricoes' => array_merge(
            !empty($ev['inscricoes_abertas']) ? ['abertas' => '1'] : [],
            [
                'prazo_iso' => (string) ($ev['inscricoes_prazo_iso'] ?? ''),
                'inicio'    => (string) ($ev['inscricoes_inicio'] ?? ''),
                'vagas'     => (string) ($ev['inscricoes_vagas'] ?? 0),
                'taxa'      => rtrim(rtrim(number_format($taxa, 2, ',', ''), '0'), ','),
            ]
        ),
        'contato' => [
            'email' => $ev['contato']['email'] ?? '', 'whatsapp' => $ev['contato']['whatsapp'] ?? '',
            'instagram' => $ev['contato']['instagram'] ?? '', 'grupo_whatsapp' => $ev['contato']['grupo_whatsapp'] ?? '',
        ],
        'camiseta' => [
            'modelos' => array_map(function ($m) use ($dinheiro) {
                return ['slug' => $m['slug'], 'nome' => $m['nome'], 'valor' => $dinheiro($m['valor'])];
            }, camiseta_modelos($ev['camiseta'] ?? [])),
            'prazo_iso' => $ev['camiseta']['prazo_iso'] ?? '',
            'tamanhos' => array_values($ev['camiseta']['tamanhos'] ?? []),
        ],
        'categorias' => array_map(function ($c) use ($dinheiro) {
            return ['slug' => $c['slug'], 'nome' => $c['nome'], 'valor' => $dinheiro($c['valor']), 'resumo' => $c['resumo'] ?? ''];
        }, $ev['categorias'] ?? []),
        'premiacoes' => array_map(function ($p) {
            return ['titulo' => $p['titulo'] ?? '', 'regra' => $p['regra'] ?? '', 'premio' => $p['premio'] ?? ''];
        }, $ev['premiacoes'] ?? []),
        'rotas' => array_map(function ($r) {
            $m = $r['mapa'] ?? strava_extrair((string) ($r['mapa_embed'] ?? '')) ?? strava_extrair((string) ($r['mapa_url'] ?? ''));
            return [
                'nome' => $r['nome'] ?? '', 'distancia' => $r['distancia'] ?? '', 'altimetria' => $r['altimetria'] ?? '',
                'descricao' => $r['descricao'] ?? '', 'mapa' => $m ? strava_link($m) : '',
            ];
        }, $ev['rotas'] ?? []),
        'programacao' => array_map(function ($p) {
            return ['hora' => $p['hora'] ?? '', 'titulo' => $p['titulo'] ?? '', 'desc' => $p['desc'] ?? ''];
        }, $ev['programacao'] ?? []),
        'faq' => array_map(function ($p) {
            return ['p' => $p['p'] ?? '', 'r' => $p['r'] ?? ''];
        }, $ev['faq'] ?? []),
        'patrocinadores' => $patro,
    ];
}

/* ---------------------------------------------------------------------------
   Validação
   --------------------------------------------------------------------------- */

/**
 * Confere e normaliza o que veio do formulário do painel.
 * Devolve ['dados' => estrutura de evento(), 'erros' => ['secao.campo' => 'mensagem']].
 */
function evento_validar(array $entrada, array $atual, array $categorias_com_inscricao): array
{
    $erros = [];
    $dados = [];

    // Textos obrigatórios/opcionais com tamanho máximo
    $texto = function (array $origem, string $chave, string $caminho, int $max, bool $obrigatorio, string $rotulo) use (&$erros): string {
        $v = campo($origem, $chave);
        if ($obrigatorio && $v === '') {
            $erros[$caminho] = "Informe $rotulo.";
        } elseif (mb_strlen($v) > $max) {
            $erros[$caminho] = "Use no máximo $max caracteres.";
        }
        return $v;
    };

    // Geral
    $g = is_array($entrada['geral'] ?? null) ? $entrada['geral'] : [];
    $dados['nome']          = $texto($g, 'nome', 'geral.nome', 150, true, 'o nome do evento');
    $dados['organizador']   = $texto($g, 'organizador', 'geral.organizador', 150, true, 'o organizador');
    $dados['data_iso']      = campo($g, 'data_iso');
    if (!data_valida($dados['data_iso'])) {
        $erros['geral.data_iso'] = 'Informe uma data válida.';
    }
    $dados['cidade']        = $texto($g, 'cidade', 'geral.cidade', 100, true, 'a cidade');
    $dados['uf']            = strtoupper(campo($g, 'uf'));
    if (!in_array($dados['uf'], UFS, true)) {
        $erros['geral.uf'] = 'Escolha o estado.';
    }
    $dados['local_largada'] = $texto($g, 'local_largada', 'geral.local_largada', 150, true, 'o local de largada');
    $data_evento = isset($erros['geral.data_iso']) ? null : $dados['data_iso'];

    // Inscrições
    $i = is_array($entrada['inscricoes'] ?? null) ? $entrada['inscricoes'] : [];
    $dados['inscricoes_abertas'] = !empty($i['abertas']);
    $prazo = campo($i, 'prazo_iso');
    if ($prazo !== '' && !data_valida($prazo)) {
        $erros['inscricoes.prazo_iso'] = 'Informe uma data válida (ou deixe em branco para não ter prazo).';
    } elseif ($prazo !== '' && $data_evento && $prazo > $data_evento) {
        $erros['inscricoes.prazo_iso'] = 'O último dia de inscrição não pode ser depois do evento.';
    }
    $dados['inscricoes_prazo_iso'] = $prazo;
    // Início programado: as inscrições abrem sozinhas nesse dia e hora (vazio = sem início programado)
    $inicio = campo($i, 'inicio');
    $d_inicio = DateTime::createFromFormat('Y-m-d\TH:i', $inicio);
    if ($inicio !== '' && (!$d_inicio || $d_inicio->format('Y-m-d\TH:i') !== $inicio)) {
        $erros['inscricoes.inicio'] = 'Informe a data e a hora do início (ou deixe em branco).';
    } elseif ($inicio !== '' && $prazo !== '' && !isset($erros['inscricoes.prazo_iso']) && substr($inicio, 0, 10) > $prazo) {
        $erros['inscricoes.inicio'] = 'O início não pode ser depois do último dia de inscrição.';
    }
    $dados['inscricoes_inicio'] = $inicio;
    $vagas = campo($i, 'vagas');
    if (!preg_match('/^\d{1,6}$/', $vagas)) {
        $erros['inscricoes.vagas'] = 'Informe o número de vagas (0 = sem limite).';
    }
    $dados['inscricoes_vagas'] = (int) $vagas;
    $taxa = dinheiro(campo($i, 'taxa'));
    if ($taxa === null || $taxa > 100) {
        $erros['inscricoes.taxa'] = 'Informe uma taxa de 0 a 100.';
    }
    $dados['taxa_administracao'] = ['percentual' => (float) $taxa];

    // Contatos
    $c = is_array($entrada['contato'] ?? null) ? $entrada['contato'] : [];
    $email = mb_strtolower(campo($c, 'email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erros['contato.email'] = 'Informe um e-mail válido.';
    }
    $zap = so_digitos(campo($c, 'whatsapp'));
    if (strlen($zap) === 11) {
        $zap_fmt = sprintf('(%s) %s-%s', substr($zap, 0, 2), substr($zap, 2, 5), substr($zap, 7));
    } elseif (strlen($zap) === 10) {
        $zap_fmt = sprintf('(%s) %s-%s', substr($zap, 0, 2), substr($zap, 2, 4), substr($zap, 6));
    } else {
        $zap_fmt = campo($c, 'whatsapp');
        $erros['contato.whatsapp'] = 'Informe o WhatsApp com DDD.';
    }
    $insta = campo($c, 'instagram');
    if ($insta !== '' && (!preg_match('#^https?://#i', $insta) || !filter_var($insta, FILTER_VALIDATE_URL) || mb_strlen($insta) > 200)) {
        $erros['contato.instagram'] = 'Use o link completo, começando com https://';
    }
    $grupo = campo($c, 'grupo_whatsapp');
    if ($grupo !== '' && (!preg_match('#^https://chat\.whatsapp\.com/[A-Za-z0-9]+([?\#].*)?$#', $grupo) || mb_strlen($grupo) > 300)) {
        $erros['contato.grupo_whatsapp'] = 'Cole o link de convite do grupo (começa com https://chat.whatsapp.com/).';
    }
    $dados['contato'] = ['email' => $email, 'whatsapp' => $zap_fmt, 'instagram' => $insta, 'grupo_whatsapp' => $grupo];

    // Camiseta
    $cam = is_array($entrada['camiseta'] ?? null) ? $entrada['camiseta'] : [];
    // Modelos (ex.: manga curta / manga longa), cada um com o seu preço; o identificador de um modelo existente não muda
    $modelos = [];
    $slugs_usados = array_column(camiseta_modelos($atual['camiseta'] ?? []), 'slug');
    $slugs_lista = [];
    foreach (itens_de($cam, 'modelos', ['slug', 'nome', 'valor']) as $n => $m) {
        $p = "camiseta.modelos.$n";
        $nome_m = $texto($m, 'nome', "$p.nome", 40, true, 'o nome do modelo');
        $valor_m = dinheiro($m['valor']);
        if ($valor_m === null) {
            $erros["$p.valor"] = 'Informe um valor válido (ex.: 130,00).';
        }
        $slug_m = preg_match('/^[a-z0-9-]{1,20}$/', $m['slug']) && !in_array($m['slug'], $slugs_lista, true)
            ? $m['slug'] : substr(slug_de($nome_m !== '' ? $nome_m : 'modelo', array_merge($slugs_usados, $slugs_lista)), 0, 20);
        $slugs_lista[] = $slug_m;
        $modelos[] = ['slug' => $slug_m, 'nome' => $nome_m, 'valor' => (float) $valor_m];
    }
    if (!$modelos) {
        $erros['camiseta.modelos'] = 'Informe pelo menos um modelo de camiseta.';
    }
    $prazo_cam = campo($cam, 'prazo_iso');
    if (!data_valida($prazo_cam)) {
        $erros['camiseta.prazo_iso'] = 'Informe uma data válida.';
    } elseif ($data_evento && $prazo_cam > $data_evento) {
        $erros['camiseta.prazo_iso'] = 'O último dia para comprar não pode ser depois do evento.';
    }
    $tamanhos = [];
    foreach ((is_array($cam['tamanhos'] ?? null) ? $cam['tamanhos'] : []) as $t) {
        $t = is_string($t) ? strtoupper(trim($t)) : '';
        if ($t === '') {
            continue;
        }
        if (mb_strlen($t) > 4) {
            $erros['camiseta.tamanhos'] = 'Cada tamanho pode ter no máximo 4 caracteres.';
        } elseif (in_array($t, $tamanhos, true)) {
            $erros['camiseta.tamanhos'] = "O tamanho $t está repetido.";
        }
        $tamanhos[] = $t;
    }
    if (!$tamanhos) {
        $erros['camiseta.tamanhos'] = 'Informe pelo menos um tamanho.';
    }
    $dados['camiseta'] = ['modelos' => $modelos, 'prazo_iso' => $prazo_cam, 'tamanhos' => $tamanhos];

    // Categorias: o identificador (slug) de uma existente nunca muda; uma nova ganha um gerado do nome
    $existentes = array_column($atual['categorias'] ?? [], 'slug');
    $usados = $existentes;
    $atribuidos = [];
    $dados['categorias'] = [];
    foreach (itens_de($entrada, 'categorias', ['slug', 'nome', 'valor', 'resumo']) as $n => $cat) {
        $p = "categorias.$n";
        if (in_array($cat['slug'], $existentes, true) && !in_array($cat['slug'], $atribuidos, true)) {
            $slug = $cat['slug'];
        } else {
            $slug = slug_de($cat['nome'], $usados);
            $usados[] = $slug;
        }
        $atribuidos[] = $slug;
        $nome = $texto($cat, 'nome', "$p.nome", 60, true, 'o nome da categoria');
        $valor = dinheiro($cat['valor']);
        if ($valor === null) {
            $erros["$p.valor"] = 'Valor inválido (ex.: 60,00).';
        }
        $dados['categorias'][] = [
            'slug'   => $slug,
            'nome'   => $nome,
            'valor'  => (float) $valor,
            'resumo' => $texto($cat, 'resumo', "$p.resumo", 300, false, 'o resumo'),
        ];
    }
    if (!$dados['categorias']) {
        $erros['categorias'] = 'Cadastre pelo menos uma categoria.';
    }
    foreach ($categorias_com_inscricao as $slug) {
        if (in_array($slug, $existentes, true) && !in_array($slug, $atribuidos, true)) {
            $nome = '';
            foreach ($atual['categorias'] as $antiga) {
                if ($antiga['slug'] === $slug) {
                    $nome = $antiga['nome'];
                }
            }
            $erros['categorias'] = "A categoria $nome tem inscrições e não pode ser removida.";
        }
    }

    // Premiações
    $dados['premiacoes'] = [];
    foreach (itens_de($entrada, 'premiacoes', ['titulo', 'regra', 'premio']) as $n => $it) {
        $dados['premiacoes'][] = [
            'titulo' => $texto($it, 'titulo', "premiacoes.$n.titulo", 120, true, 'o título'),
            'regra'  => $texto($it, 'regra', "premiacoes.$n.regra", 300, false, 'a regra'),
            'premio' => $texto($it, 'premio', "premiacoes.$n.premio", 300, false, 'o prêmio'),
        ];
    }

    // Rotas: o mapa é guardado como dados extraídos; o link reenviado mantém o token já conhecido
    $conhecidos = mapas_conhecidos($atual);
    $dados['rotas'] = [];
    foreach (itens_de($entrada, 'rotas', ['nome', 'distancia', 'altimetria', 'descricao', 'mapa']) as $n => $it) {
        $mapa = null;
        if ($it['mapa'] !== '') {
            $mapa = strava_extrair($it['mapa']);
            if (!$mapa) {
                $erros["rotas.$n.mapa"] = 'Cole o link da rota no Strava (strava.com/routes/...) ou o código de incorporação dela.';
            } elseif (count($mapa) === 1 && isset($conhecidos[$mapa['id']])) {
                $mapa = $conhecidos[$mapa['id']];
            }
        }
        $dados['rotas'][] = [
            'nome'       => $texto($it, 'nome', "rotas.$n.nome", 120, true, 'o nome da rota'),
            'distancia'  => $texto($it, 'distancia', "rotas.$n.distancia", 30, false, 'a distância'),
            'altimetria' => $texto($it, 'altimetria', "rotas.$n.altimetria", 30, false, 'a altimetria'),
            'descricao'  => $texto($it, 'descricao', "rotas.$n.descricao", 300, false, 'a descrição'),
            'mapa'       => $mapa,
        ];
    }

    // Programação
    $dados['programacao'] = [];
    foreach (itens_de($entrada, 'programacao', ['hora', 'titulo', 'desc']) as $n => $it) {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $it['hora']) && !preg_match('/^\d:[0-5]\d$/', $it['hora'])) {
            $erros["programacao.$n.hora"] = 'Use o formato HH:MM (ex.: 07:30).';
        }
        $dados['programacao'][] = [
            'hora'   => $it['hora'],
            'titulo' => $texto($it, 'titulo', "programacao.$n.titulo", 120, true, 'o título'),
            'desc'   => $texto($it, 'desc', "programacao.$n.desc", 300, false, 'a descrição'),
        ];
    }

    // Perguntas frequentes
    $dados['faq'] = [];
    foreach (itens_de($entrada, 'faq', ['p', 'r']) as $n => $it) {
        $dados['faq'][] = [
            'p' => $texto($it, 'p', "faq.$n.p", 200, true, 'a pergunta'),
            'r' => $texto($it, 'r', "faq.$n.r", 2000, true, 'a resposta'),
        ];
    }

    // Patrocinadores: lista na ordem do carrossel; o logo só pode ser um arquivo enviado pelo painel ou da pasta antiga
    $dados['patrocinadores'] = [];
    foreach (itens_de($entrada, 'patrocinadores', ['logo', 'nome', 'url']) as $n => $it) {
        $p = "patrocinadores.$n";
        if (!patro_logo_valido($it['logo'], patro_raiz())) {
            $erros["$p.logo"] = 'Envie o logo do patrocinador.';
        }
        $item = ['logo' => $it['logo'], 'nome' => $texto($it, 'nome', "$p.nome", 100, true, 'o nome')];
        $url = $it['url'];
        if ($url !== '' && (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 300)) {
            $erros["$p.url"] = 'Use o link completo, começando com https://';
        }
        if ($url !== '') {
            $item['url'] = $url;
        }
        $dados['patrocinadores'][] = $item;
    }

    return ['dados' => $dados, 'erros' => $erros];
}
