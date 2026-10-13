<?php
/**
 * DADOS DO EVENTO
 * Tudo que aparece no site (datas, valores, rotas, programação, FAQ) vem daqui.
 * Para alterar uma informação, edite apenas este arquivo.
 *
 * Itens marcados com "A confirmar" ainda precisam ser definidos pela organização.
 */
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

    // Camiseta oficial (compra opcional, à parte da inscrição)
    'camiseta' => [
        'valor'      => 130.00,
        // Último dia para comprar (inclusive). Para alterar, mude apenas esta data (AAAA-MM-DD).
        'prazo_iso'  => '2027-02-20',
        'tamanhos'   => ['PP', 'P', 'M', 'G', 'GG', 'XG', 'XXG'],
        // Coloque os arquivos em assets/img/ com estes nomes. Enquanto não existirem, aparece um aviso no lugar.
        'arte'       => 'camiseta.jpg',
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
            'premio' => '',
        ],
        [
            'titulo' => 'E-bikes Hard',
            'regra'  => '1º ao 3º lugar geral, masculino e feminino',
            'premio' => '',
        ],
        [
            'titulo' => 'Maiores equipes',
            'regra'  => '1º ao 3º lugar',
            'premio' => '',
        ],
    ],

    // Rotas (distâncias e altimetria ainda não definidas)
    'rotas' => [
        [
            'nome'       => 'Light',
            'distancia'  => 'A confirmar',
            'altimetria' => 'A confirmar',
            'terreno'    => 'A confirmar',
            'descricao'  => 'Percurso para pedalar em ritmo leve, aberto a quem está começando.',
            'mapa_url'   => '', // Cole aqui o link de incorporação (Google My Maps, Strava, Ride with GPS...)
        ],
        [
            'nome'       => 'Hard',
            'distancia'  => 'A confirmar',
            'altimetria' => 'A confirmar',
            'terreno'    => 'A confirmar',
            'descricao'  => 'Percurso mais longo e com maior exigência física. Também é o percurso das e-bikes.',
            'mapa_url'   => '',
        ],
        [
            'nome'       => 'Rachão',
            'distancia'  => 'A confirmar',
            'altimetria' => 'A confirmar',
            'terreno'    => 'A confirmar',
            'descricao'  => 'Percurso competitivo, para quem vai disputar a classificação geral.',
            'mapa_url'   => '',
        ],
    ],

    // Programação (horários a definir pela organização)
    'programacao' => [
        ['hora' => 'A definir', 'titulo' => 'Retirada de kits',           'desc' => 'Apresente um documento com foto e o comprovante de inscrição.'],
        ['hora' => 'A definir', 'titulo' => 'Abertura da concentração',   'desc' => 'Chegada dos participantes, check-in e aquecimento.'],
        ['hora' => 'A definir', 'titulo' => 'Briefing de segurança',      'desc' => 'Orientações sobre o percurso, sinalização e pontos de apoio.'],
        ['hora' => 'A definir', 'titulo' => 'Largada Rachão',             'desc' => ''],
        ['hora' => 'A definir', 'titulo' => 'Largada Hard e E-bikes',     'desc' => ''],
        ['hora' => 'A definir', 'titulo' => 'Largada Light',              'desc' => ''],
        ['hora' => 'A definir', 'titulo' => 'Chegada e confraternização', 'desc' => 'Hidratação, lanche e encontro dos participantes.'],
        ['hora' => 'A definir', 'titulo' => 'Premiação',                  'desc' => 'Entrega de troféus e premiações por categoria e equipes.'],
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
            'r' => 'R$ 60,00 para Hard e Light e R$ 70,00 para o Rachão. Consulte a página de inscrição para ver o valor da categoria escolhida.',
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
