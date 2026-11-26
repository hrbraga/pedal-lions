<?php
/**
 * Níveis de acesso do painel: quais páginas cada administrador pode abrir.
 * Só regras (sem sessão nem banco), para poderem ser testadas sozinhas.
 */

/** Páginas com acesso controlado, na ordem do menu. A chave é o que fica gravado no banco. */
const ACESSO_PAGINAS = [
    'inscritos' => ['rotulo' => 'Inscritos', 'url' => 'painel.php'],
    'cupons'    => ['rotulo' => 'Cupons', 'url' => 'cupons.php'],
    'dados'     => ['rotulo' => 'Dados do Evento', 'url' => 'dados.php'],
    'admins'    => ['rotulo' => 'Administradores', 'url' => 'administradores.php'],
    'exportar'  => ['rotulo' => 'Exportar CSV', 'url' => 'exportar.php'],
];

/** Mantém só chaves conhecidas, sem repetição, na ordem do menu. */
function acesso_paginas_normalizar(array $chaves): array
{
    $marcadas = [];
    foreach ($chaves as $c) {
        if (is_string($c)) {
            $marcadas[trim($c)] = true;
        }
    }
    return array_values(array_filter(array_keys(ACESSO_PAGINAS), fn($k) => isset($marcadas[$k])));
}

/** Texto gravado no banco ("cupons,dados") → lista de chaves. */
function acesso_paginas_ler(string $texto): array
{
    return acesso_paginas_normalizar(explode(',', $texto));
}

/** Lista de chaves → texto para gravar no banco. */
function acesso_paginas_texto(array $chaves): string
{
    return implode(',', acesso_paginas_normalizar($chaves));
}

/** "Acesso total" libera qualquer página, inclusive as criadas depois. */
function acesso_pode(bool $total, array $paginas, string $pagina): bool
{
    return $total || in_array($pagina, $paginas, true);
}

/**
 * Primeira página liberada, na ordem do menu; sem nenhuma, a página Minha conta.
 * Exportar CSV não conta: é um download, não uma página para "cair" depois do login.
 */
function acesso_inicio(bool $total, array $paginas): string
{
    foreach (ACESSO_PAGINAS as $chave => $p) {
        if ($chave !== 'exportar' && acesso_pode($total, $paginas, $chave)) {
            return $p['url'];
        }
    }
    return 'conta.php';
}

function acesso_descricao(bool $total, array $paginas): string
{
    if ($total) {
        return 'Acesso total';
    }
    $rotulos = array_map(fn($k) => ACESSO_PAGINAS[$k]['rotulo'], acesso_paginas_normalizar($paginas));
    return $rotulos ? implode(', ', $rotulos) : 'Nenhuma página';
}

/** Motivo para recusar a mudança de acesso de alguém, ou null se puder. $qtd_total = administradores com acesso total hoje. */
function acesso_erro_alterar(int $meu_id, int $alvo_id, bool $alvo_total, bool $novo_total, int $qtd_total): ?string
{
    if ($meu_id === $alvo_id) {
        return 'Você não pode alterar o seu próprio acesso.';
    }
    if ($alvo_total && !$novo_total && $qtd_total <= 1) {
        return 'É preciso manter pelo menos um administrador com acesso total.';
    }
    return null;
}

/** Motivo para recusar a remoção de um administrador, ou null se puder. */
function acesso_erro_remover(int $meu_id, int $alvo_id, bool $alvo_total, int $qtd_total, int $qtd_admins): ?string
{
    if ($meu_id === $alvo_id) {
        return 'Você não pode remover o seu próprio acesso.';
    }
    if ($qtd_admins <= 1) {
        return 'É preciso manter pelo menos um administrador.';
    }
    if ($alvo_total && $qtd_total <= 1) {
        return 'É preciso manter pelo menos um administrador com acesso total.';
    }
    return null;
}
