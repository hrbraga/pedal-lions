<?php
/**
 * Dados do Evento salvos pelo painel: cada gravação é uma versão (JSON) na tabela evento_versoes.
 * O site lê por evento(), que mescla config/evento.php (padrão) com a versão mais recente.
 */
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/db.php';

const EVENTO_VERSOES_MAX = 50;

/** true se as chaves forem 0..n-1 (substituto de array_is_list, que só existe a partir do PHP 8.1). */
function eh_lista(array $a): bool
{
    return $a === [] || array_keys($a) === range(0, count($a) - 1);
}

/** Mescla os dados salvos sobre o padrão: associativos mesclam campo a campo; listas e valores simples são substituídos. */
function evento_mesclar(array $base, array $salvo): array
{
    foreach ($salvo as $chave => $valor) {
        if (is_array($valor) && !eh_lista($valor) && isset($base[$chave]) && is_array($base[$chave]) && !eh_lista($base[$chave])) {
            $base[$chave] = evento_mesclar($base[$chave], $valor);
        } else {
            $base[$chave] = $valor;
        }
    }
    return $base;
}

/** Datas exibidas, calculadas da data do evento (AAAA-MM-DD). */
function evento_datas(string $iso): array
{
    $d = DateTime::createFromFormat('!Y-m-d', $iso);
    if (!$d || $d->format('Y-m-d') !== $iso) {
        return ['data_curta' => $iso, 'data_extenso' => $iso];
    }
    $dias  = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return [
        'data_curta'   => $d->format('d/m/Y'),
        'data_extenso' => $dias[(int) $d->format('w')] . ', ' . (int) $d->format('j') . ' de ' . $meses[(int) $d->format('n') - 1] . ' de ' . $d->format('Y'),
    ];
}

function evento_versao_decodificar(array $linha): array
{
    $dados = json_decode((string) $linha['dados'], true);
    $linha['id']    = (int) $linha['id'];
    $linha['dados'] = is_array($dados) ? $dados : [];
    return $linha;
}

/** Versão em vigor (a de maior id) ou null se nada foi salvo pelo painel. */
function evento_versao_atual(PDO $pdo): ?array
{
    $linha = $pdo->query('SELECT id, dados, salvo_em, salvo_por, restaurada_de FROM evento_versoes ORDER BY id DESC LIMIT 1')->fetch();
    return $linha ? evento_versao_decodificar($linha) : null;
}

/** Uma versão pelo id, com os dados. */
function evento_versao(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT id, dados, salvo_em, salvo_por, restaurada_de FROM evento_versoes WHERE id = :id');
    $st->execute([':id' => $id]);
    $linha = $st->fetch();
    return $linha ? evento_versao_decodificar($linha) : null;
}

/** Histórico, mais nova primeiro (sem os dados). */
function evento_versoes(PDO $pdo, int $limite = EVENTO_VERSOES_MAX): array
{
    $st = $pdo->prepare('SELECT id, salvo_em, salvo_por, restaurada_de FROM evento_versoes ORDER BY id DESC LIMIT :n');
    $st->bindValue(':n', $limite, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** Grava uma nova versão, apaga as que passarem das 50 mais recentes e devolve o id. */
function evento_salvar(PDO $pdo, array $dados, string $usuario, ?int $restaurada_de = null): int
{
    // Sem JSON_UNESCAPED_UNICODE: acentos e emojis viram \uXXXX, então a gravação funciona com qualquer charset do banco
    $json = json_encode($dados, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $st = $pdo->prepare('INSERT INTO evento_versoes (dados, salvo_por, restaurada_de) VALUES (:d, :u, :r)');
    $st->execute([':d' => $json, ':u' => $usuario, ':r' => $restaurada_de]);
    $id = (int) $pdo->lastInsertId();
    $pdo->exec('DELETE FROM evento_versoes WHERE id NOT IN (
                    SELECT id FROM (SELECT id FROM evento_versoes ORDER BY id DESC LIMIT ' . EVENTO_VERSOES_MAX . ') AS recentes)');
    return $id;
}
