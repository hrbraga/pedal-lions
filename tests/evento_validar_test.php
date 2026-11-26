<?php
/**
 * Testes da validação dos Dados do Evento e do tratamento do mapa do Strava.
 * Rode com: php tests/evento_validar_test.php   (no XAMPP: C:\xampp\php\php tests\evento_validar_test.php)
 * Não usa o banco: parte dos valores de config/evento.php.
 */
require __DIR__ . '/../includes/evento_validar.php';

$falhas = 0;
function confere(string $nome, $obtido, $esperado): void
{
    global $falhas;
    if ($obtido === $esperado) {
        echo "ok - $nome\n";
    } else {
        $falhas++;
        echo "FALHOU - $nome: esperado " . var_export($esperado, true) . ', obtido ' . var_export($obtido, true) . "\n";
    }
}

$atual = evento_padrao();
$atual = array_merge($atual, evento_datas($atual['data_iso']));

/** Monta um POST completo e válido a partir dos dados do evento (como o formulário do painel enviaria). */
function entrada_de(array $ev): array
{
    $mapa = function (array $r) {
        $m = $r['mapa'] ?? strava_extrair((string) ($r['mapa_embed'] ?? '')) ?? strava_extrair((string) ($r['mapa_url'] ?? ''));
        return $m ? strava_link($m) : '';
    };
    return [
        'geral' => [
            'nome' => $ev['nome'], 'organizador' => $ev['organizador'], 'data_iso' => $ev['data_iso'],
            'cidade' => $ev['cidade'], 'uf' => $ev['uf'], 'local_largada' => $ev['local_largada'],
        ],
        'inscricoes' => [
            'abertas' => '1', 'prazo_iso' => $ev['inscricoes_prazo_iso'], 'vagas' => (string) $ev['inscricoes_vagas'],
            'taxa' => (string) $ev['taxa_administracao']['percentual'], 'inicio' => '2026-12-01T12:00',
        ],
        'contato' => ['email' => $ev['contato']['email'], 'whatsapp' => '43 996759480', 'instagram' => '',
                      'grupo_whatsapp' => $ev['contato']['grupo_whatsapp'] ?? ''],
        'camiseta' => [
            'modelos' => [
                ['slug' => 'curta', 'nome' => 'Manga curta', 'valor' => '130,00'],
                ['slug' => 'longa', 'nome' => 'Manga longa', 'valor' => '150,00'],
            ],
            'prazo_iso' => $ev['camiseta']['prazo_iso'], 'tamanhos' => $ev['camiseta']['tamanhos'],
        ],
        'categorias' => array_map(function ($c) {
            return ['slug' => $c['slug'], 'nome' => $c['nome'], 'valor' => number_format($c['valor'], 2, ',', ''), 'resumo' => $c['resumo']];
        }, $ev['categorias']),
        'premiacoes' => $ev['premiacoes'],
        'rotas' => array_map(function ($r) use ($mapa) {
            return ['nome' => $r['nome'], 'distancia' => $r['distancia'], 'altimetria' => $r['altimetria'],
                    'descricao' => $r['descricao'], 'mapa' => $mapa($r)];
        }, $ev['rotas']),
        'programacao' => $ev['programacao'],
        'faq' => $ev['faq'],
        'patrocinadores' => [['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => 'Royal', 'url' => '']],
    ];
}

$valida = entrada_de($atual);
$v = function (array $entrada, array $com_inscricao = []) use ($atual) {
    return evento_validar($entrada, $atual, $com_inscricao);
};
/** Copia a entrada válida, troca um campo (caminho "secao.chave" ou "secao.i.chave") e devolve os erros. */
$erros = function (string $caminho, $valor, array $com_inscricao = []) use ($valida, $v) {
    $e = $valida;
    $ref = &$e;
    foreach (explode('.', $caminho) as $parte) {
        $ref = &$ref[$parte];
    }
    $ref = $valor;
    unset($ref);
    return $v($e, $com_inscricao)['erros'];
};

$r = $v($valida);
confere('entrada válida não tem erros', $r['erros'], []);
confere('valor com vírgula vira número', $r['dados']['categorias'][0]['valor'], 70.0);
confere('camiseta: modelos com valor em vírgula', $r['dados']['camiseta']['modelos'], [
    ['slug' => 'curta', 'nome' => 'Manga curta', 'valor' => 130.0],
    ['slug' => 'longa', 'nome' => 'Manga longa', 'valor' => 150.0],
]);
confere('camiseta: não grava mais o valor único', array_key_exists('valor', $r['dados']['camiseta']), false);
confere('camiseta: modelo sem nome', isset($erros('camiseta.modelos.0.nome', '')['camiseta.modelos.0.nome']), true);
confere('camiseta: valor inválido', isset($erros('camiseta.modelos.1.valor', 'abc')['camiseta.modelos.1.valor']), true);
confere('camiseta: sem modelos', $erros('camiseta.modelos', [])['camiseta.modelos'] ?? null, 'Informe pelo menos um modelo de camiseta.');
$regata = $valida;
$regata['camiseta']['modelos'][] = ['slug' => '', 'nome' => 'Regata', 'valor' => '99,90'];
confere('camiseta: modelo novo ganha identificador do nome', $v($regata)['dados']['camiseta']['modelos'][2]['slug'] ?? null, 'regata');
confere('camiseta: formato antigo vira um modelo', camiseta_modelos(['valor' => 130]), [['slug' => 'camiseta', 'nome' => 'Camiseta', 'valor' => 130.0]]);
confere('camiseta: modelos guardados são lidos', array_column(camiseta_modelos(['modelos' => [['slug' => 'longa', 'nome' => 'Manga longa', 'valor' => 150]]]), 'valor', 'slug'), ['longa' => 150.0]);
confere('whatsapp formatado', $r['dados']['contato']['whatsapp'], '(43) 99675-9480');
confere('vagas vira inteiro', $r['dados']['inscricoes_vagas'], 300);
confere('taxa vira número', $r['dados']['taxa_administracao'], ['percentual' => 10.0]);
confere('abertas marcado = true', $r['dados']['inscricoes_abertas'], true);
confere('datas e local saem no nível de cima', [$r['dados']['data_iso'], $r['dados']['local_largada']], ['2027-03-14', 'Bosque Municipal de Cornélio Procópio']);

$sem_abertas = $valida;
unset($sem_abertas['inscricoes']['abertas']);
confere('abertas desmarcado = false', $v($sem_abertas)['dados']['inscricoes_abertas'], false);

confere('nome vazio', isset($erros('geral.nome', '  ')['geral.nome']), true);
confere('data inválida', isset($erros('geral.data_iso', '2027-02-30')['geral.data_iso']), true);
confere('UF inválida', isset($erros('geral.uf', 'XX')['geral.uf']), true);
confere('prazo depois do evento', isset($erros('inscricoes.prazo_iso', '2027-03-15')['inscricoes.prazo_iso']), true);
confere('prazo vazio = sem prazo', $erros('inscricoes.prazo_iso', ''), []);
confere('início guardado', $v($valida)['dados']['inscricoes_inicio'], '2026-12-01T12:00');
confere('início vazio = sem início programado', $erros('inscricoes.inicio', ''), []);
confere('início inválido', isset($erros('inscricoes.inicio', '2026-13-01T12:00')['inscricoes.inicio']), true);
confere('início sem hora', isset($erros('inscricoes.inicio', '2026-12-01')['inscricoes.inicio']), true);
confere('início depois do último dia', isset($erros('inscricoes.inicio', '2027-03-13T08:00')['inscricoes.inicio']), true);
confere('formulário traz o início', evento_para_formulario(array_merge($atual, ['inscricoes_inicio' => '2026-12-01T12:00']))['inscricoes']['inicio'], '2026-12-01T12:00');
confere('prazo camiseta depois do evento', isset($erros('camiseta.prazo_iso', '2027-04-01')['camiseta.prazo_iso']), true);
confere('vagas vazio', isset($erros('inscricoes.vagas', '')['inscricoes.vagas']), true);
confere('vagas negativo', isset($erros('inscricoes.vagas', '-5')['inscricoes.vagas']), true);
confere('valor negativo', isset($erros('categorias.0.valor', '-1')['categorias.0.valor']), true);
confere('valor texto', isset($erros('categorias.0.valor', 'abc')['categorias.0.valor']), true);
confere('taxa acima de 100', isset($erros('inscricoes.taxa', '101')['inscricoes.taxa']), true);
confere('e-mail inválido', isset($erros('contato.email', 'fulano@')['contato.email']), true);
confere('whatsapp curto', isset($erros('contato.whatsapp', '9967')['contato.whatsapp']), true);
confere('instagram sem https', isset($erros('contato.instagram', 'instagram.com/x')['contato.instagram']), true);
confere('instagram com https', $erros('contato.instagram', 'https://instagram.com/pedal'), []);
$grupo = 'https://chat.whatsapp.com/HyYQvEQrZI3EgD4SsUKgoY?s=sw&p=a&mlu=4&ilr=4';
confere('grupo do whatsapp vem do config', $atual['contato']['grupo_whatsapp'] ?? null, $grupo);
confere('grupo do whatsapp salvo', $r['dados']['contato']['grupo_whatsapp'] ?? null, $grupo);
confere('grupo do whatsapp vazio = sem quadro', $erros('contato.grupo_whatsapp', ''), []);
confere('grupo do whatsapp de outro site', isset($erros('contato.grupo_whatsapp', 'https://exemplo.com/x')['contato.grupo_whatsapp']), true);
confere('grupo do whatsapp sem https', isset($erros('contato.grupo_whatsapp', 'chat.whatsapp.com/abc')['contato.grupo_whatsapp']), true);
confere('formulário traz o grupo', evento_para_formulario($atual)['contato']['grupo_whatsapp'] ?? null, $grupo);
confere('hora inválida', isset($erros('programacao.0.hora', '7h30')['programacao.0.hora']), true);
confere('hora 24:00 inválida', isset($erros('programacao.0.hora', '24:00')['programacao.0.hora']), true);
confere('sem categorias', isset($erros('categorias', [])['categorias']), true);
confere('sem tamanhos', isset($erros('camiseta.tamanhos', [])['camiseta.tamanhos']), true);
confere('tamanho com 5 caracteres não cabe no banco (VARCHAR 4)', isset($erros('camiseta.tamanhos', ['XXXGG'])['camiseta.tamanhos']), true);
confere('tamanho com 4 caracteres', $erros('camiseta.tamanhos', ['XXGG']), []);
confere('tamanho repetido',isset($erros('camiseta.tamanhos', ['P', 'p'])['camiseta.tamanhos']), true);
confere('pergunta sem resposta', isset($erros('faq.0.r', '')['faq.0.r']), true);

$tamanhos = $valida;
$tamanhos['camiseta']['tamanhos'] = [' gg ', 'P', ''];
confere('tamanhos em maiúsculas e sem vazios', $v($tamanhos)['dados']['camiseta']['tamanhos'], ['GG', 'P']);

$item_vazio = $valida;
$item_vazio['faq'][] = ['p' => ' ', 'r' => ''];
confere('item de lista todo em branco é descartado', count($v($item_vazio)['dados']['faq']), count($valida['faq']));

$sem_rachao = $valida;
array_shift($sem_rachao['categorias']);
confere('categoria com inscritos removida', isset($v($sem_rachao, ['rachao'])['erros']['categorias']), true);
confere('categoria sem inscritos pode sair', $v($sem_rachao, ['hard'])['erros'], []);

$nova = $valida;
$nova['categorias'][] = ['slug' => '', 'nome' => 'Gravel Ação', 'valor' => '80', 'resumo' => ''];
confere('categoria nova ganha slug', $v($nova)['dados']['categorias'][4]['slug'], 'gravel-acao');
confere('slug repetido ganha sufixo', slug_de('Hard', ['hard']), 'hard-2');
confere('slug com sufixo ocupado', slug_de('Hard', ['hard', 'hard-2']), 'hard-3');
confere('slug de nome sem letras', slug_de('!!!', []), 'categoria');

$renomeada = $valida;
$renomeada['categorias'][0]['nome'] = 'Rachão Pro';
confere('slug de categoria existente não muda', $v($renomeada)['dados']['categorias'][0]['slug'], 'rachao');

$inventado = $valida;
$inventado['categorias'][3]['slug'] = 'xyz';
confere('slug inventado no POST é ignorado', $v($inventado)['dados']['categorias'][3]['slug'], 'e-bike-hard');

$duplicado = $valida;
$duplicado['categorias'][1]['slug'] = 'rachao';
confere('slug existente repetido no POST vira novo', $v($duplicado)['dados']['categorias'][1]['slug'], 'hard-2');

$embed1 = $atual['rotas'][0]['mapa_embed'];
confere('strava link', strava_extrair('https://www.strava.com/routes/3456363824372913536'), ['id' => '3456363824372913536']);
confere('strava link com barra e parâmetros', strava_extrair('strava.com/routes/123/?x=1'), ['id' => '123']);
confere('strava embed', strava_extrair($embed1), [
    'id' => '3456356447837639954', 'token' => '15S0cgPwjMxxv8HRKnFsKR5iP-Nu139ZdaQhXPnC5UI',
    'style' => 'standard', 'map_hash' => '11.95/-23.19164/-50.59554',
]);
confere('strava com script e onerror', strava_extrair('<div data-embed-type="route" data-embed-id="123" onerror="x()"></div><script>alert(1)</script>'), ['id' => '123']);
confere('strava token malicioso é descartado', strava_extrair('<div data-embed-type="route" data-embed-id="5" data-token="a&quot;><script>"></div>'), ['id' => '5']);
confere('strava de atividade (não rota) é recusado', strava_extrair('https://www.strava.com/activities/123'), null);
confere('strava lixo', strava_extrair('olá'), null);
$html = strava_html(['id' => '1', 'token' => '"><x', 'style' => 'standard']);
confere('strava html sem script', strpos($html, '<script'), false);
confere('strava html escapa', strpos($html, '"><x'), false);
confere('strava html tem o id', strpos($html, 'data-embed-id="1"') !== false, true);
confere('strava link canônico', strava_link(['id' => '9', 'token' => 't']), 'https://www.strava.com/routes/9');

confere('reabrir com o link mantém token', $r['dados']['rotas'][0]['mapa']['token'] ?? null, '15S0cgPwjMxxv8HRKnFsKR5iP-Nu139ZdaQhXPnC5UI');
confere('rota sai sem o código colado antigo', array_keys($r['dados']['rotas'][0]), ['nome', 'distancia', 'altimetria', 'descricao', 'mapa']);
confere('mapa vazio = sem mapa', $v((function ($e) { $e['rotas'][0]['mapa'] = ''; return $e; })($valida))['dados']['rotas'][0]['mapa'], null);
confere('mapa irreconhecível dá erro', isset($erros('rotas.0.mapa', 'olá')['rotas.0.mapa']), true);

$html_texto = $valida;
$html_texto['faq'][0]['r'] = '<b>oi</b>';
confere('texto com HTML fica como texto', $v($html_texto)['dados']['faq'][0]['r'], '<b>oi</b>');

$patro = $valida;
$patro['patrocinadores'] = [
    ['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => 'Royal', 'url' => 'https://royal.com.br'],
    ['logo' => 'assets/img/patrocinadores/vital.jpg', 'nome' => 'Vital', 'url' => ''],
];
confere('patrocinadores: lista na ordem, link só quando há', $v($patro)['dados']['patrocinadores'], [
    ['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => 'Royal', 'url' => 'https://royal.com.br'],
    ['logo' => 'assets/img/patrocinadores/vital.jpg', 'nome' => 'Vital'],
]);
confere('patrocinadores: não grava mais o formato antigo', array_key_exists('patrocinadores_info', $v($patro)['dados']), false);
foreach (['../config/banco.php', 'assets/img/patrocinadores/../../config/banco.php', 'uploads/patrocinadores/0123456789abcdef.php', 'https://x.com/a.png', ''] as $forjado) {
    confere("logo forjado recusado: $forjado", $erros('patrocinadores.0.logo', $forjado)['patrocinadores.0.logo'] ?? null, 'Envie o logo do patrocinador.');
}
confere('patrocinador sem nome', isset($erros('patrocinadores.0.nome', '')['patrocinadores.0.nome']), true);
confere('patrocinador com link inválido', isset($erros('patrocinadores.0.url', 'javascript:alert(1)')['patrocinadores.0.url']), true);

confere('site: lista salva na ordem, pula logo sumido, nome padrão',
    array_map(fn($p) => [$p['arquivo'], $p['nome']], patrocinadores_de(['patrocinadores' => [
        ['logo' => 'assets/img/patrocinadores/vital.jpg', 'nome' => 'Vital'],
        ['logo' => 'uploads/patrocinadores/ffffffffffffffff.png', 'nome' => 'Sumiu'],
        ['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => ''],
    ]], patro_raiz())),
    [['assets/img/patrocinadores/vital.jpg', 'Vital'], ['assets/img/patrocinadores/royal.png', 'Patrocinador']]);
confere('site: lista vazia salva = nenhum patrocinador', patrocinadores_de(['patrocinadores' => []], patro_raiz()), []);
confere('site: sem lista = pasta antiga', count(patrocinadores_de(['patrocinadores_info' => []], patro_raiz())), 4);
confere('site: pasta antiga com nome e link do formato antigo',
    array_values(array_filter(patrocinadores_de(['patrocinadores_info' => ['royal.png' => ['nome' => 'Royal Ltda', 'url' => 'https://royal.com.br']]], patro_raiz()),
        fn($p) => $p['logo'] === 'assets/img/patrocinadores/royal.png'))[0]['url'] ?? null, 'https://royal.com.br');

$form = evento_para_formulario($atual);
$ida_volta = $v($form);
confere('formulário do evento atual valida sem erros', $ida_volta['erros'], []);
confere('ida e volta mantém categorias', $ida_volta['dados']['categorias'], $atual['categorias']);
confere('ida e volta mantém programação e FAQ', [$ida_volta['dados']['programacao'], $ida_volta['dados']['faq']], [$atual['programacao'], $atual['faq']]);
confere('ida e volta mantém o token do mapa', $ida_volta['dados']['rotas'][1]['mapa']['token'] ?? null, 'l13f0AIscPIsA_34ehX9L-n_FDQqmDkEVxv70Sxa3rc');
confere('formulário traz os logos da pasta antiga, com nome', array_column($form['patrocinadores'], 'logo'),
    ['assets/img/patrocinadores/Galdini.png', 'assets/img/patrocinadores/pebaaobruto.png', 'assets/img/patrocinadores/royal.png', 'assets/img/patrocinadores/vital.jpg']);
confere('formulário: nome de cada patrocinador preenchido', count(array_filter(array_column($form['patrocinadores'], 'nome'))), 4);
confere('ida e volta mantém os modelos de camiseta', $ida_volta['dados']['camiseta']['modelos'], camiseta_modelos($atual['camiseta']));
confere('ida e volta mantém os patrocinadores', array_column($ida_volta['dados']['patrocinadores'], 'logo'), array_column($form['patrocinadores'], 'logo'));

// Logo cujo arquivo sumiu do servidor (ex.: mudança de hospedagem): o patrocinador continua no formulário,
// com nome e link, e o Salvar pede o logo de novo em vez de apagar o patrocinador sem aviso
$sumiu = $atual;
$sumiu['patrocinadores'] = [
    ['logo' => 'uploads/patrocinadores/ffffffffffffffff.png', 'nome' => 'Sumiu', 'url' => 'https://sumiu.com.br'],
    ['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => 'Royal'],
];
$form_sumiu = evento_para_formulario($sumiu);
confere('logo sumido: patrocinador continua no formulário', $form_sumiu['patrocinadores'], [
    ['logo' => 'uploads/patrocinadores/ffffffffffffffff.png', 'nome' => 'Sumiu', 'url' => 'https://sumiu.com.br'],
    ['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => 'Royal', 'url' => ''],
]);
confere('logo sumido: salvar pede o logo de novo', $v($form_sumiu)['erros']['patrocinadores.0.logo'] ?? null, 'Envie o logo do patrocinador.');
confere('taxa sem casas desnecessárias', $form['inscricoes']['taxa'], '10');
$fechado = $atual;
$fechado['inscricoes_abertas'] = false;
confere('inscrições fechadas = checkbox desmarcado', isset(evento_para_formulario($fechado)['inscricoes']['abertas']), false);

echo $falhas ? "\n$falhas falha(s)\n" : "\ntodos passaram\n";
exit($falhas ? 1 : 0);
