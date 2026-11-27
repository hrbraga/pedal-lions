<?php
/**
 * Ajudantes dos testes para subir o Mercado Pago falso (tests/mp_falso.php) e ler o que ele recebeu.
 */

/** Sobe o servidor falso na porta e espera ficar de pé. Devolve o processo (feche com mp_falso_parar). */
function mp_falso_subir(int $porta)
{
    @unlink(sys_get_temp_dir() . '/mp_falso_' . $porta . '.json');
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $porta, __DIR__ . '/mp_falso.php'],
                      [['pipe', 'r'], ['file', sys_get_temp_dir() . '/mp_falso_' . $porta . '.log', 'a'], ['file', sys_get_temp_dir() . '/mp_falso_' . $porta . '.log', 'a']], $pipes);
    for ($k = 0; $k < 50 && !($s = @fsockopen('127.0.0.1', $porta, $en, $es, 0.2)); $k++) {
        usleep(100000);
    }
    if (!empty($s)) {
        fclose($s);
    }
    return $proc;
}

function mp_falso_parar($proc, int $porta): void
{
    // No Windows o proc_terminate não derruba o php -S filho: encerra pela porta
    $st = proc_get_status($proc);
    if (PHP_OS_FAMILY === 'Windows') {
        exec('taskkill /F /T /PID ' . (int) $st['pid'] . ' 2>NUL');
    }
    proc_terminate($proc);
    proc_close($proc);
    @unlink(sys_get_temp_dir() . '/mp_falso_' . $porta . '.json');
}

/** Estado do falso: ['pagamentos' => [...], 'pedidos' => [...]]. */
function mp_falso_estado(int $porta): array
{
    $a = sys_get_temp_dir() . '/mp_falso_' . $porta . '.json';
    return is_file($a) ? (json_decode((string) file_get_contents($a), true) ?: []) : [];
}

/** Muda campos de um pagamento guardado no falso (ex.: ['status' => 'approved']). */
function mp_falso_mudar(int $porta, $id, array $campos): void
{
    file_get_contents('http://127.0.0.1:' . $porta . '/__mudar/' . $id, false, stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => json_encode($campos), 'ignore_errors' => true,
    ]]));
}
