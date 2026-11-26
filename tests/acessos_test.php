<?php
/**
 * Testes das regras de acesso por página do painel.
 * Rode com: php tests/acessos_test.php   (no XAMPP: C:\xampp\php\php tests\acessos_test.php)
 */
require __DIR__ . '/../includes/acessos.php';

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

confere('normaliza: ordem do menu, sem repetidas, ignora desconhecidas',
    acesso_paginas_normalizar(['exportar', 'xyz', 'inscritos', 'exportar', 7, ['a']]), ['inscritos', 'exportar']);
confere('lê texto do banco', acesso_paginas_ler('cupons,inscritos'), ['inscritos', 'cupons']);
confere('lê texto vazio', acesso_paginas_ler(''), []);
confere('lê texto com espaços e chave antiga', acesso_paginas_ler(' dados , financeiro_velho '), ['dados']);
confere('grava texto', acesso_paginas_texto(['admins', 'cupons', 'zzz']), 'cupons,admins');
confere('acesso total pode tudo, até página futura', acesso_pode(true, [], 'financeiro'), true);
confere('pode página liberada', acesso_pode(false, ['cupons'], 'cupons'), true);
confere('não pode página não liberada', acesso_pode(false, ['cupons'], 'inscritos'), false);
confere('início com acesso total', acesso_inicio(true, []), 'painel.php');
confere('início na ordem do menu', acesso_inicio(false, ['exportar', 'dados']), 'dados.php');
confere('início sem páginas', acesso_inicio(false, []), 'conta.php');
// Exportar CSV é um download, não uma página: não pode ser a página inicial (evita baixar arquivo no login e laço de redirecionamento)
confere('início só com Exportar CSV', acesso_inicio(false, ['exportar']), 'conta.php');
confere('início com Exportar e Cupons', acesso_inicio(false, ['exportar', 'cupons']), 'cupons.php');
confere('descrição total', acesso_descricao(true, ['cupons']), 'Acesso total');
confere('descrição lista', acesso_descricao(false, ['dados', 'inscritos']), 'Inscritos, Dados do Evento');
confere('descrição vazia', acesso_descricao(false, []), 'Nenhuma página');
confere('alterar: próprio acesso', acesso_erro_alterar(1, 1, true, true, 3), 'Você não pode alterar o seu próprio acesso.');
confere('alterar: tirar o último total', acesso_erro_alterar(2, 1, true, false, 1), 'É preciso manter pelo menos um administrador com acesso total.');
confere('alterar: tirar um de dois totais', acesso_erro_alterar(2, 1, true, false, 2), null);
confere('alterar: dar total a quem não tinha', acesso_erro_alterar(2, 3, false, true, 1), null);
confere('remover: a si mesmo', acesso_erro_remover(1, 1, true, 2, 3), 'Você não pode remover o seu próprio acesso.');
confere('remover: único administrador', acesso_erro_remover(2, 1, false, 0, 1), 'É preciso manter pelo menos um administrador.');
confere('remover: último total', acesso_erro_remover(2, 1, true, 1, 2), 'É preciso manter pelo menos um administrador com acesso total.');
confere('remover: permitido', acesso_erro_remover(2, 3, false, 1, 3), null);

// Só quem tem acesso total mexe em quem tem acesso total (senha, acesso, remoção) ou dá acesso total
$sobre_total = 'Só um administrador com acesso total pode alterar, redefinir a senha ou remover outro com acesso total.';
$dar_total   = 'Só um administrador com acesso total pode dar acesso total.';
confere('total: limitado mexe em quem tem total', acesso_erro_total(false, true, false), $sobre_total);
confere('total: limitado mexe em total mantendo total', acesso_erro_total(false, true, true), $sobre_total);
confere('total: limitado dá total', acesso_erro_total(false, false, true), $dar_total);
confere('total: limitado mexe em limitado', acesso_erro_total(false, false, false), null);
confere('total: quem tem total pode tudo', acesso_erro_total(true, true, false), null);
confere('total: quem tem total dá total', acesso_erro_total(true, false, true), null);

// Quem não tem acesso total só libera páginas que ele mesmo tem
$f = 'acesso_erro_paginas';
confere('páginas: total libera qualquer uma', $f(true, [], [], ['dados', 'cupons']), null);
confere('páginas: libera uma que tem', $f(false, ['admins', 'cupons'], [], ['cupons']), null);
confere('páginas: libera uma que não tem', $f(false, ['admins', 'cupons'], [], ['cupons', 'dados']),
    'Você só pode liberar páginas que você também acessa. Sem acesso: Dados do Evento.');
confere('páginas: lista todas as que não tem, na ordem do menu', $f(false, ['admins'], [], ['exportar', 'inscritos']),
    'Você só pode liberar páginas que você também acessa. Sem acesso: Inscritos, Exportar CSV.');
confere('páginas: manter uma que o outro já tinha', $f(false, ['admins', 'cupons'], ['dados'], ['dados', 'cupons']), null);
confere('páginas: tirar uma que não tem', $f(false, ['admins'], ['dados', 'cupons'], ['cupons']), null);

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
