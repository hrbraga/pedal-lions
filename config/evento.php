<?php
/**
 * DADOS DO EVENTO
 * Tudo que aparece no site (datas, valores, rotas, programação, FAQ) vem daqui.
 * Para alterar uma informação, edite apenas este arquivo.
 *
 * Itens marcados com "A confirmar" ainda precisam ser definidos pela organização.
 */
$taxa_pct = 10; // taxa de administração (%), cobrada sobre o valor da inscrição (não inclui a camiseta)

return [

    'nome'         => '2º Pedal Solidário',
    'organizador'  => 'Lions Clube de Cornélio Procópio',
    'cidade'       => 'Cornélio Procópio',
    'uf'           => 'PR',
    'data_iso'     => '2027-03-14',
    'data_curta'   => '14/03/2027',
    'data_extenso' => 'Domingo, 14 de março de 2027',
    'local_largada' => 'A confirmar',

    // Quando a inscrição estiver encerrada, troque para false.
    'inscricoes_abertas' => true,

    // Contatos exibidos no rodapé e no FAQ.
    'contato' => [
        'email'    => 'contato@exemplo.com.br',   // A confirmar
        'whatsapp' => '(43) 00000-0000',          // A confirmar
        'instagram' => '',                        // Ex.: https://instagram.com/seuperfil
    ],

    // Patrocinadores: os logos são lidos da pasta assets/img/patrocinadores/ (veja o LEIAME.txt dela).
    // Opcional: nome (texto alternativo) e link de cada patrocinador, pelo NOME DO ARQUIVO. Exemplo:
    //   '01-empresa-x.png' => ['nome' => 'Empresa X', 'url' => 'https://www.empresax.com.br'],
    'patrocinadores_info' => [
    ],

    // Taxa de administração: calculada sobre o valor CHEIO da inscrição (o cupom não a reduz).
    // A camiseta é isenta. Para mudar o percentual, altere $taxa_pct no topo deste arquivo.
    'taxa_administracao' => [
        'percentual' => $taxa_pct,
    ],

    // Camiseta oficial (compra opcional, à parte da inscrição)
    'camiseta' => [
        'valor'      => 130.00,
        // Último dia para comprar (inclusive). Para alterar, mude apenas esta data (AAAA-MM-DD).
        'prazo_iso'  => '2027-02-20',
        'tamanhos'   => ['PP', 'P', 'M', 'G', 'GG', 'XG', 'XXG'],
        // Coloque os arquivos em assets/img/ com estes nomes. Enquanto não existirem, aparece um aviso no lugar.
        'arte'       => 'camiseta.png',
        'tabela'     => 'tabela-tamanhos.png',
    ],

    // Modalidades e valores
    'categorias' => [
        [
            'slug'   => 'rachao',
            'nome'   => 'Rachão',
            'valor'  => 70.00,
            'resumo' => 'Categoria competitiva, com premiação até o 5º colocado geral.',
        ],
        [
            'slug'   => 'hard',
            'nome'   => 'Hard',
            'valor'  => 60.00,
            'resumo' => 'Percurso mais longo e exigente, para quem já pedala com frequência.',
        ],
        [
            'slug'   => 'light',
            'nome'   => 'Light',
            'valor'  => 60.00,
            'resumo' => 'Percurso mais curto e tranquilo, para pedalar em ritmo leve.',
        ],
        [
            'slug'   => 'ebike-hard',
            'nome'   => 'E-bike Hard',
            'valor'  => 60.00, // A confirmar: valor da categoria E-bike
            'resumo' => 'Bicicletas elétricas no percurso Hard.',
        ],
    ],

    // Premiações
    'premiacoes' => [
        [
            'titulo' => 'Rachão',
            'regra'  => '1º ao 5º lugar geral, masculino e feminino',
            'premio' => 'Troféu + premiação',
        ],
        [
            'titulo' => 'Hard e Light',
            'regra'  => '1º ao 5º lugar geral, masculino e feminino',
            'premio' => 'Troféu',
        ],
        [
            'titulo' => 'E-bikes Hard',
            'regra'  => '1º ao 3º lugar geral, masculino e feminino',
            'premio' => 'Troféu',
        ],
        [
            'titulo' => 'Maiores equipes',
            'regra'  => '1º ao 3º lugar',
            'premio' => 'Troféu',
        ],
    ],

    // Rotas (distâncias e altimetria ainda não definidas)
    'rotas' => [
        [
            'nome'       => 'Light',
            'distancia'  => '24 km',
            'altimetria' => '490 m',
            'descricao'  => 'Percurso para pedalar em ritmo leve, aberto a quem está começando.',
            
            'mapa_embed' => <<<'HTML'

<div class="strava-embed-placeholder" data-embed-type="route" data-embed-id="3456356447837639954" data-full-width="true" data-style="standard" data-map-hash="11.95/-23.19164/-50.59554" data-from-embed="true" data-token="15S0cgPwjMxxv8HRKnFsKR5iP-Nu139ZdaQhXPnC5UI"></div><script src="https://strava-embeds.com/embed.js"></script>


HTML,
            'mapa_url'   => '',
        ],
        [
            'nome'       => 'Hard / Rachão / E-bikes',
            'distancia'  => '42 km',
            'altimetria' => '860 m',
            'descricao'  => 'Percurso mais longo e com maior exigência física.',
            'mapa_embed' => <<<'HTML'

<div class="strava-embed-placeholder" data-embed-type="route" data-embed-id="3456363824372913536" data-full-width="true" data-style="standard" data-from-embed="false" data-token="l13f0AIscPIsA_34ehX9L-n_FDQqmDkEVxv70Sxa3rc"></div><script src="https://strava-embeds.com/embed.js"></script>
HTML,
            'mapa_url'   => 'https://www.strava.com/routes/3456363824372913536',
        ],
    ],

    // Programação (horários a definir pela organização)
    'programacao' => [
        ['hora' => '6:00', 'titulo' => 'Retirada de kits',           'desc' => 'Apresente um documento com foto e o comprovante de inscrição.'],
        ['hora' => '6:30', 'titulo' => 'Café da Manhã',   'desc' => 'Chegada dos participantes, check-in e aquecimento.'],
        ['hora' => '7:15', 'titulo' => 'Briefing de segurança',      'desc' => 'Orientações sobre o percurso, sinalização e pontos de apoio.'],
        ['hora' => '7:30', 'titulo' => 'Largada Rachão',             'desc' => ''],
        ['hora' => '7:40', 'titulo' => 'Largada Hard e E-bikes',     'desc' => ''],
        ['hora' => '7:50', 'titulo' => 'Largada Light',              'desc' => ''],
        ['hora' => '10:30', 'titulo' => 'Sorteios', 'desc' => 'Sorteio de Brindes para os participantes que completarem o percurso.'],
        ['hora' => '10:45', 'titulo' => 'Premiação',                  'desc' => 'Entrega de troféus e premiações por categoria e equipes.'],
        ['hora' => '12:00', 'titulo' => 'Encerramento',                  'desc' => 'Finalização do evento e agradecimentos aos participantes.'],

    ],

    // Perguntas frequentes
    'faq' => [
        [
            'p' => 'Quem pode participar?',
            'r' => 'Qualquer pessoa que consiga completar o percurso da categoria escolhida. Menores de 18 anos precisam de autorização assinada pelo responsável (veja o regulamento).',
        ],
        [
            'p' => 'Qual categoria devo escolher?',
            'r' => 'Se você está começando ou prefere pedalar sem pressa, escolha a Light. A Hard é mais longa e exigente. O Rachão é a categoria competitiva. Bicicletas elétricas participam na categoria E-bike Hard.',
        ],
        [
            'p' => 'Quanto custa a inscrição?',
            'r' => 'R$ 60,00 para Hard e Light e R$ 70,00 para o Rachão. Sobre o valor da inscrição incide uma taxa de administração de ' . $taxa_pct . '%, somada no pagamento. A camiseta (opcional, R$ 130,00) não tem taxa nem desconto. O resumo da página de inscrição mostra o total exato.',
        ],
        [
            'p' => 'Minha inscrição já está confirmada depois de preencher o formulário?',
            'r' => 'Não. A inscrição só é efetivada após a confirmação do pagamento. Enquanto isso, ela fica com o status pendente.',
        ],
        [
            'p' => 'Como e quando retiro meu kit?',
            'r' => 'As informações de local e horário da retirada serão divulgadas na página de Programação e enviadas por e-mail aos inscritos com pagamento confirmado.',
        ],
        [
            'p' => 'O capacete é obrigatório?',
            'r' => 'Sim. O uso de capacete durante todo o percurso é obrigatório. Consulte o regulamento para os demais itens de segurança.',
        ],
        [
            'p' => 'Posso participar com bicicleta elétrica?',
            'r' => 'Sim, na categoria E-bike Hard, que tem classificação e premiação próprias.',
        ],
        [
            'p' => 'Como funciona a premiação por equipes?',
            'r' => 'As três maiores equipes recebem premiação. Para contar para a sua equipe, informe o mesmo nome de equipe no momento da inscrição.',
        ],
        [
            'p' => 'Posso transferir ou cancelar minha inscrição?',
            'r' => 'A política de cancelamento e transferência será definida no regulamento. A confirmar.',
        ],
        [
            'p' => 'O que acontece em caso de chuva?',
            'r' => 'A organização avaliará as condições de segurança e comunicará qualquer alteração pelos canais oficiais do evento. A confirmar.',
        ],
        [
            'p' => 'Para onde vai o valor arrecadado?',
            'r' => 'O pedal é uma ação solidária promovida pelo Lions Clube de Cornélio Procópio. A destinação do valor arrecadado será informada aqui. A confirmar.',
        ],
    ],
];
