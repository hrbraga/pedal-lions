<?php
/**
 * Exporta os inscritos em CSV (abre direto no Excel). Respeita os filtros do painel.
 */
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_filtros.php';
require_once __DIR__ . '/../includes/pagamento.php';
admin_exigir('exportar');

[$onde, $params] = montar_filtros($_GET);

/** Evita que o Excel interprete texto digitado pelo participante como fórmula. */
function celula(?string $v): string
{
    $v = (string) $v;
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

try {
    $st = db()->prepare("SELECT inscricoes.*,
            (SELECT p.metodo FROM pagamentos p WHERE p.mp_id = inscricoes.pagamento_mp_id LIMIT 1) AS pag_metodo,
            (SELECT p.parcelas FROM pagamentos p WHERE p.mp_id = inscricoes.pagamento_mp_id LIMIT 1) AS pag_parcelas
        FROM inscricoes $onde ORDER BY nome ASC");
    $st->execute($params);
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro na exportação: ' . $e->getMessage());
    flash('erro', 'Não foi possível gerar o arquivo.');
    header('Location: ' . admin_inicio());
    exit;
}

$arquivo = 'inscritos-' . date('Y-m-d-Hi') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer acentos

$cabecalho = ['Código', 'Nome', 'CPF', 'Nascimento', 'Sexo', 'E-mail', 'WhatsApp', 'Cidade', 'UF', 'Categoria', 'Equipe',
              'Modelo da camiseta', 'Camiseta', 'Contato de emergência', 'Telefone de emergência', 'Saúde',
              'Valor inscrição', 'Taxa de administração', 'Cupom', 'Desconto', 'Valor camiseta', 'Valor total', 'Situação', 'Pago em', 'Forma de pagamento', 'Alerta', 'Inscrito em'];
fputcsv($saida, $cabecalho, ';', '"', '');

while ($i = $st->fetch()) {
    $cat = categoria_por_slug($i['categoria']);
    fputcsv($saida, [
        '="' . $i['codigo'] . '"', // força texto (códigos como 12E4567890 virariam notação científica)
        celula($i['nome']),
        cpf_formatado($i['cpf']),
        data_br($i['nascimento']),
        $i['sexo'] === 'F' ? 'Feminino' : 'Masculino',
        celula($i['email']),
        telefone_formatado($i['telefone']),
        celula($i['cidade']),
        $i['uf'],
        $cat['nome'] ?? $i['categoria'],
        celula($i['equipe']),
        $i['camiseta_tamanho'] ? camiseta_modelo_nome($i['camiseta_modelo'] ?? null) : '',
        $i['camiseta_tamanho'] ?? '',
        celula($i['emerg_nome']),
        telefone_formatado($i['emerg_tel']),
        celula($i['saude']),
        number_format((float) $i['valor_inscricao'], 2, ',', ''),
        number_format((float) ($i['valor_taxa'] ?? 0), 2, ',', ''),
        celula($i['cupom_codigo'] ?? ''),
        number_format((float) ($i['valor_desconto'] ?? 0), 2, ',', ''),
        number_format((float) $i['valor_camiseta'], 2, ',', ''),
        number_format((float) $i['valor_total'], 2, ',', ''),
        STATUS_ROTULOS[$i['status']] ?? $i['status'],
        data_hora_br($i['pago_em']),
        pagamento_forma_texto($i['pag_metodo'] ?? null, $i['pag_parcelas'] ?? null),
        celula((string) ($i['pagamento_alerta'] ?? '')),
        data_hora_br($i['criado_em']),
    ], ';', '"', '');
}
fclose($saida);
exit;
