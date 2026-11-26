# Logos dos patrocinadores pelo painel – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enviar, trocar, editar, reordenar e excluir patrocinadores (logo + nome + link) na seção Patrocinadores de Dados do Evento, salvos com o resto e guardados no Histórico.

**Architecture:** Regras de arquivo em `includes/patrocinadores.php` (tipo real da imagem, limite, caminho válido, órfãos), testadas por script PHP. Os dados ganham a lista `patrocinadores` (`logo`, `nome`, `url`); sem lista salva, o site segue lendo a pasta antiga com `patrocinadores_info`. `admin/dados.php` grava os envios em `uploads/patrocinadores/` antes de validar, injeta o caminho no item e valida tudo junto.

**Tech Stack:** PHP 8.2 (sem GD: `getimagesize` lê o cabeçalho sem a extensão), PDO MySQL, JS sem framework. Testes: `C:\xampp\php\php tests/<x>_test.php` (`confere()`), cenário CDP no scratchpad (`node cdp.js logos`, servidor `php -S localhost:8123`), Apache do XAMPP em `http://[::1]/pedal-solidario/` para o `.htaccess`.

**Spec:** `docs/superpowers/specs/2026-11-26-logos-patrocinadores-design.md`

## Global Constraints

- Tipos aceitos no envio: PNG, JPEG, WEBP, detectados pelo conteúdo (`getimagesize` → `IMAGETYPE_PNG|JPEG|WEBP`); extensão gravada `png|jpg|webp` conforme o tipo detectado. SVG e GIF recusados no envio.
- Limite: 2 MB por arquivo (`PATRO_MAX_BYTES = 2 * 1024 * 1024`).
- Pasta de envios: `uploads/patrocinadores/` (relativa à raiz do site), nome `bin2hex(random_bytes(8)) . '.' . ext` (16 hex). Fora do git; `uploads/.htaccess` versionado.
- Caminhos de logo válidos: `uploads/patrocinadores/[a-f0-9]{16}\.(png|jpg|webp)` ou `assets/img/patrocinadores/<arquivo>` (pasta antiga, extensões png|jpg|jpeg|webp|svg|gif, sem `/`, `\`, `..` nem ponto inicial), e o arquivo precisa existir.
- Item: `nome` obrigatório (até 100), `url` opcional (`https?://`, até 300, mesma regra de hoje), `logo` obrigatório.
- Limpeza: depois de cada gravação (Salvar e Restaurar), apaga de `uploads/patrocinadores/` os arquivos que nenhuma versão guardada cita e com mais de 86400 s. Falha na limpeza nunca impede a gravação.
- Textos fixos: `Envie o logo do patrocinador.` · `Use PNG, JPG ou WEBP.` · `O logo passa de 2 MB.` · `O envio do logo falhou. Tente de novo.` · `Não foi possível guardar o logo no servidor.` · `Os arquivos enviados passam do limite do servidor. Envie menos logos de cada vez.`
- Página exige `admin_exigir('dados')` (já existe). Sem push nem merge sem pedir.

## Review Focus

1. Formulário com erro em outro campo depois de enviar um logo → o logo enviado continua no item e é salvo no próximo envio (cenário Task 3).
2. Envio total acima do `post_max_size` (o PHP descarta `$_POST`, inclusive o csrf) → aviso específico, não "Sessão expirada" (passo da Task 3).
3. Arquivo `.php` ou texto renomeado para `.png`, SVG com script → recusado, nada gravado na pasta (testes Task 1 + cenário Task 3).
4. Restaurar versão antiga (inclusive anterior à lista, só com `patrocinadores_info`) → site volta a mostrar os logos daquela versão (cenário Task 3).
5. Valor forjado em `patrocinadores[n][logo]` (`../config/banco.php`, `uploads/patrocinadores/x.php`, URL externa) → erro "Envie o logo", nada salvo (testes Task 2).

---

### Task 1: Regras dos arquivos de logo

**Files:**
- Create: `includes/patrocinadores.php`
- Test: `tests/patrocinadores_test.php`

**Interfaces:**
- Produces:
  - `const PATRO_ENVIOS = 'uploads/patrocinadores'`, `const PATRO_MAX_BYTES = 2097152`
  - `patro_raiz(): string` — raiz do site (`dirname(__DIR__)`).
  - `patro_tipo_imagem(string $caminho): ?string` — `'png'|'jpg'|'webp'` ou null.
  - `patro_erro_envio(int $erro_upload, int $tamanho, ?string $tipo): ?string` — mensagem fixa ou null. `UPLOAD_ERR_INI_SIZE|FORM_SIZE` ou `$tamanho > PATRO_MAX_BYTES` → "passa de 2 MB"; outro erro ≠ OK → "envio falhou"; `$tipo === null` → "Use PNG…".
  - `patro_logo_valido(string $logo, string $raiz): bool`
  - `patro_url(string $logo): string` — `dirname/rawurlencode(basename)`.
  - `patro_guardar_envio(array $arquivo, string $raiz): array` — uma entrada de `$_FILES` normalizada (`name,tmp_name,error,size`); devolve `[]` (sem arquivo), `['logo' => caminho]` ou `['erro' => mensagem]`. Cria a pasta se faltar (`mkdir 0755, recursive`); usa `move_uploaded_file`; falha ao mover/criar → "Não foi possível guardar…".
  - `patro_orfaos(array $arquivos, array $textos_versoes, int $agora, int $idade_min = 86400): array` — `$arquivos` = `nome => mtime`; devolve os nomes não citados em nenhum texto e com `$agora - mtime >= $idade_min`.
  - `patro_limpar_envios(PDO $pdo, string $raiz): int` — lê nomes/mtime da pasta e `SELECT dados FROM evento_versoes`, apaga os órfãos, devolve quantos.

- [ ] **Step 1: Escrever `tests/patrocinadores_test.php`** (formato de `tests/inscricoes_test.php`). Cria uma pasta temporária (`sys_get_temp_dir()`) com fixtures em base64 e apaga no fim:
  - PNG 1×1 `iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=`
  - WEBP 1×1 `UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA`
  - JPEG: cabeçalho mínimo válido para `getimagesize` (SOI + APP0 + SOF0 1×1); se a fixture escolhida não for reconhecida, trocar por outra (ruling no ledger).
  - `falso.png` com `<?php echo 1;`, `logo.svg` com `<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>`.

```php
confere('png reconhecido', patro_tipo_imagem($dir . '/a.png'), 'png');
confere('webp reconhecido', patro_tipo_imagem($dir . '/a.webp'), 'webp');
confere('jpeg reconhecido', patro_tipo_imagem($dir . '/a.jpg'), 'jpg');
confere('php renomeado recusado', patro_tipo_imagem($dir . '/falso.png'), null);
confere('svg recusado', patro_tipo_imagem($dir . '/logo.svg'), null);
confere('envio ok', patro_erro_envio(UPLOAD_ERR_OK, 1000, 'png'), null);
confere('envio grande', patro_erro_envio(UPLOAD_ERR_OK, PATRO_MAX_BYTES + 1, 'png'), 'O logo passa de 2 MB.');
confere('envio grande pelo php.ini', patro_erro_envio(UPLOAD_ERR_INI_SIZE, 0, null), 'O logo passa de 2 MB.');
confere('envio parcial', patro_erro_envio(UPLOAD_ERR_PARTIAL, 10, null), 'O envio do logo falhou. Tente de novo.');
confere('tipo errado', patro_erro_envio(UPLOAD_ERR_OK, 10, null), 'Use PNG, JPG ou WEBP.');
// patro_logo_valido com $raiz = $dir, onde o teste cria uploads/patrocinadores/0123456789abcdef.png e assets/img/patrocinadores/Velho Logo.png
confere('logo enviado válido', patro_logo_valido('uploads/patrocinadores/0123456789abcdef.png', $dir), true);
confere('logo da pasta antiga válido', patro_logo_valido('assets/img/patrocinadores/Velho Logo.png', $dir), true);
confere('logo inexistente', patro_logo_valido('uploads/patrocinadores/ffffffffffffffff.png', $dir), false);
confere('nome fora do padrão', patro_logo_valido('uploads/patrocinadores/logo.png', $dir), false);
confere('php na pasta de envios', patro_logo_valido('uploads/patrocinadores/0123456789abcdef.php', $dir), false);
confere('subindo pasta', patro_logo_valido('assets/img/patrocinadores/../../config/banco.php', $dir), false);
confere('url externa', patro_logo_valido('https://x.com/a.png', $dir), false);
confere('url do logo com espaço', patro_url('assets/img/patrocinadores/Velho Logo.png'), 'assets/img/patrocinadores/Velho%20Logo.png');
confere('órfãos: citado fica, novo fica, velho sem citação sai',
    patro_orfaos(['aaaaaaaaaaaaaaaa.png' => 0, 'bbbbbbbbbbbbbbbb.png' => 0, 'cccccccccccccccc.png' => 99000],
        ['{"patrocinadores":[{"logo":"uploads/patrocinadores/aaaaaaaaaaaaaaaa.png"}]}'], 100000),
    ['bbbbbbbbbbbbbbbb.png']);
confere('guardar sem arquivo', patro_guardar_envio(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0], $dir), []);
confere('guardar arquivo que não veio de envio', isset(patro_guardar_envio(['name' => 'a.png', 'tmp_name' => $dir . '/a.png', 'error' => 0, 'size' => 100], $dir)['erro']), true);
```

- [ ] **Step 2: Rodar e ver falhar** — `C:\xampp\php\php tests/patrocinadores_test.php` → Expected: erro fatal, `includes/patrocinadores.php` não existe.
- [ ] **Step 3: Implementar `includes/patrocinadores.php`** com as funções da seção Interfaces.
- [ ] **Step 4: Rodar e ver passar** — mesmo comando → Expected: tudo `ok`, saída 0.
- [ ] **Step 5: Commit** — `git add includes/patrocinadores.php tests/patrocinadores_test.php` · `feat(patrocinadores): regras dos arquivos de logo (tipo, tamanho, caminho, órfãos)`

---

### Task 2: Lista de patrocinadores nos dados e no site

**Files:**
- Modify: `includes/funcoes.php` (`patrocinadores()`), `includes/evento_validar.php` (`evento_para_formulario`, `evento_validar`, remove `patrocinadores_arquivos`), `config/evento.php` (comentário), `assets/img/patrocinadores/LEIAME.txt`
- Test: `tests/evento_validar_test.php` (troca os testes de `patrocinadores_info` pelos da lista)

**Interfaces:**
- Consumes: `patro_logo_valido`, `patro_url`, `patro_raiz` (Task 1).
- Produces:
  - `patrocinadores(): array` — cada item `['logo' => caminho, 'arquivo' => patro_url(logo), 'nome' => string, 'url' => string]`. Se `evento()['patrocinadores']` for array: segue a lista, pulando itens com logo inválido/inexistente; nome vazio → `'Patrocinador'`. Senão: comportamento antigo da pasta + `patrocinadores_info`, acrescentando `'logo'`.
  - `evento_para_formulario()['patrocinadores']` — lista `[['logo','nome','url'], ...]` a partir de `patrocinadores()`.
  - `evento_validar()` lê `itens_de($entrada, 'patrocinadores', ['logo', 'nome', 'url'])`; erros `patrocinadores.N.logo|nome|url`; grava `$dados['patrocinadores']` (lista; `url` só quando preenchida) e não grava mais `patrocinadores_info`.

- [ ] **Step 1: Trocar no teste** o bloco de `patrocinadores_info` (linhas ~178–188) e a linha "formulário traz os logos da pasta" por:

```php
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
confere('formulário traz os logos da pasta antiga, com nome', array_column($form['patrocinadores'], 'logo'),
    ['assets/img/patrocinadores/Galdini.png', 'assets/img/patrocinadores/pebaaobruto.png', 'assets/img/patrocinadores/royal.png', 'assets/img/patrocinadores/vital.jpg']);
confere('ida e volta mantém os patrocinadores', array_column($ida_volta['dados']['patrocinadores'], 'logo'), array_column($form['patrocinadores'], 'logo'));
```
  Para o ajudante `$erros('patrocinadores.0.x', …)` alcançar um item, `entrada_de()` passa a trazer
  `'patrocinadores' => [['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => 'Royal', 'url' => '']]`.
  O site é testado sem depender de `evento()`: `patrocinadores()` passa a chamar `patrocinadores_de(array $ev, string $raiz): array`, testada assim:

```php
confere('site: lista salva na ordem, pula logo sumido, nome padrão',
    array_map(fn($p) => [$p['arquivo'], $p['nome']], patrocinadores_de(['patrocinadores' => [
        ['logo' => 'assets/img/patrocinadores/vital.jpg', 'nome' => 'Vital'],
        ['logo' => 'uploads/patrocinadores/ffffffffffffffff.png', 'nome' => 'Sumiu'],
        ['logo' => 'assets/img/patrocinadores/royal.png', 'nome' => ''],
    ]], patro_raiz())),
    [['assets/img/patrocinadores/vital.jpg', 'Vital'], ['assets/img/patrocinadores/royal.png', 'Patrocinador']]);
confere('site: lista vazia salva = nenhum patrocinador', patrocinadores_de(['patrocinadores' => []], patro_raiz()), []);
confere('site: sem lista = pasta antiga', count(patrocinadores_de(['patrocinadores_info' => []], patro_raiz())), 4);
```

- [ ] **Step 2: Rodar e ver falhar** — `C:\xampp\php\php tests/evento_validar_test.php` → Expected: falhas nos testes novos (função `patrocinadores_de` inexistente ou `patrocinadores_info` ainda gravado).
- [ ] **Step 3: Implementar** `patrocinadores_de(array $ev, string $raiz): array` em `includes/funcoes.php` (`require_once` de `includes/patrocinadores.php`), `patrocinadores()` = `patrocinadores_de(evento(), patro_raiz())`; ajustar `evento_para_formulario` e `evento_validar` conforme Interfaces; remover `patrocinadores_arquivos`. Comentário de `config/evento.php` e `LEIAME.txt`: "os patrocinadores agora são cuidados no painel (Dados do Evento → Patrocinadores); esta pasta só guarda os logos antigos".
- [ ] **Step 4: Rodar e ver passar** — os 4 scripts `tests/*_test.php` → Expected: todos `ok`. Abrir `http://localhost:8123/index.php` (curl) → Expected: os 4 `<img` do carrossel continuam (`grep -c patrocinio__item` = 4).
- [ ] **Step 5: Commit** — `feat(patrocinadores): lista de patrocinadores nos dados do evento, com a pasta antiga como padrão`

---

### Task 3: Envio na página Dados do Evento

**Files:**
- Modify: `admin/dados.php`, `assets/js/admin-dados.js`, `assets/css/admin.css`
- Create: `uploads/.htaccess`, `.gitignore`
- Test: scratchpad `cen-logos.js` (não versionado); curl no Apache para o `.htaccess`

**Interfaces:**
- Consumes: Task 1 (`patro_guardar_envio`, `patro_limpar_envios`, `patro_url`, `patro_raiz`, `PATRO_MAX_BYTES`), Task 2 (formato da lista).
- Produces (HTML): cada item `fieldset.adm-item.adm-patro[data-item]` com `img[data-logo-previa]`, `input[type=hidden][name="patrocinadores[N][logo]"]`, `input[type=file][name="patrocinadores[N][arquivo]"][accept="image/png,image/jpeg,image/webp"][data-logo-arquivo]`, campos `nome` e `url`; lista `lista('patrocinadores', 'patrocinadores', …)` com "Adicionar patrocinador"; `<form … enctype="multipart/form-data">`.

- [ ] **Step 1: Escrever `cen-logos.js`** no scratchpad (mesmo formato de `cen-acessos.js`). No início: copia `evento_versoes` (`CREATE TABLE evento_versoes_copia_cdp AS SELECT * …`) e lista os arquivos de `uploads/patrocinadores/`; no `finally`: `DELETE FROM evento_versoes; INSERT … SELECT * FROM copia; DROP`, apaga arquivos novos da pasta. Fixtures: o PNG 1×1 da Task 1 em `fotos/logo-teste.png`, `fotos/falso.png` (`<?php echo 1;`), `fotos/grande.png` (PNG válido com 2,1 MB de bytes extras no fim). Login como `admin`. Verificações (cada uma um `ok_*`):
  - `ok_lista_inicial`: seção mostra 4 itens com os logos da pasta antiga e nomes preenchidos.
  - `ok_novo`: "Adicionar patrocinador", escolher `logo-teste.png` (`DOM.setFileInputFiles`), nome `Teste CDP`, link `https://exemplo.com.br`, Salvar → aviso de sucesso; arquivo novo em `uploads/patrocinadores/` com 16 hex + `.png`; `index.php` tem 5 itens e o último é `Teste CDP` com o link.
  - `ok_previa`: depois de escolher o arquivo, `img[data-logo-previa]` do item tem `src` começando com `blob:`.
  - `ok_erro_mantem_logo`: item novo com `logo-teste.png` e nome `Mantido`, e `geral[nome]` vazio → Salvar volta com erro; o hidden `logo` do item aponta para `uploads/patrocinadores/…png`; preencher `geral[nome]` de novo e Salvar → salvo com o item `Mantido` e seu logo.
  - `ok_reordenar`: subir o último item uma posição, Salvar → ordem na home muda igual.
  - `ok_trocar`: trocar o logo de `Teste CDP` → hidden muda para outro arquivo, home mostra o novo `src`.
  - `ok_excluir`: remover `Teste CDP`, Salvar → home sem ele.
  - `ok_falso`: item com `falso.png` → erro `Use PNG, JPG ou WEBP.` no item; nenhum arquivo novo na pasta.
  - `ok_grande`: item com `grande.png` → erro `O logo passa de 2 MB.`
  - `ok_restaurar`: Restaurar a primeira versão salva no teste → home volta a ter `Teste CDP`.
  - `ok_limite_post`: `fetch` POST multipart com corpo maior que `post_max_size` (o `php -S` usa o php.ini do XAMPP, 40M; mandar 41 MB) → página mostra `Os arquivos enviados passam do limite do servidor. Envie menos logos de cada vez.`
- [ ] **Step 2: Rodar e ver falhar** — `node cdp.js logos` → Expected: `ok_lista_inicial` false (seção ainda no formato antigo, sem lista) e as demais false.
- [ ] **Step 3: `admin/dados.php`:**
  - No POST, antes do csrf: se `$_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0` → `$erros['_']` com o texto do limite e não processa.
  - No ramo de salvar, antes de `evento_validar`: `$entrada = $_POST`; para cada índice N de `$_FILES['patrocinadores']['error']` com `[N]['arquivo']`, montar a entrada normalizada e chamar `patro_guardar_envio`; `['logo']` → `$entrada['patrocinadores'][N]['logo']`; `['erro']` → `$erros_envio["patrocinadores.N.logo"]`. Validar `$entrada`; `$erros = $erros_envio + $r['erros']`; com erro, `$form = $entrada`.
  - Depois de cada `evento_salvar` (salvar e restaurar): `try { patro_limpar_envios($pdo, patro_raiz()); } catch (Throwable $e) { error_log(...) }`.
  - Reexibição com erro: `patrocinadores` sai do laço de seções e entra em `$listas` com `['logo', 'nome', 'url']`.
  - `item_patrocinador(array $f, array $erros, string $i): string` (prévia com `../` + `patro_url(logo)` ou vazia, hidden, arquivo com rótulo "Enviar logo"/"Trocar logo", erro de `patrocinadores.N.logo`, nome, link) e a seção com nota: "Envie PNG, JPG ou WEBP de até 2 MB, de preferência com fundo transparente e uns 400 px de largura. A ordem aqui é a ordem do carrossel." `onde_esta_o_erro` trata `patrocinadores.N.x` como as outras listas ("Patrocinadores, item N+1").
  - `<form>` ganha `enctype="multipart/form-data"`.
- [ ] **Step 4: JS e CSS.** Em `admin-dados.js`, `change` em `[data-logo-arquivo]` → `img[data-logo-previa]` do mesmo item recebe `URL.createObjectURL(arquivo)`; se `arquivo.size > 2097152`, mostra no item "O logo passa de 2 MB." (o servidor também recusa). CSS: prévia com altura fixa (~64 px), fundo xadrez claro para ver transparência.
- [ ] **Step 5: `uploads/.htaccess` e `.gitignore`.** `.htaccess`: `Options -Indexes`; `RemoveHandler`/`RemoveType` para `.php .phtml .phar`; com `mod_authz_core`: `Require all denied` e `<FilesMatch "(?i)\.(png|jpe?g|webp)$"> Require all granted`. `.gitignore`: `uploads/patrocinadores/`.
- [ ] **Step 6: Rodar e ver passar** — `node cdp.js logos` → Expected: todos `ok_*` true, `erros` vazio. Apache: copiar o PNG de teste para `uploads/patrocinadores/0123456789abcdef.png` e um `teste.php` para a mesma pasta; `curl -s -o NUL -w "%{http_code}" "http://[::1]/pedal-solidario/uploads/patrocinadores/<arquivo>"` → Expected: PNG `200`, PHP `403`, a pasta `403`; apagar os dois arquivos depois. Todos `tests/*_test.php` `ok`; `node --test tests/avatar-geometria.test.js` 9/9; cenário de acessos (`ETAPAS=2,3,4,5,6 sh rodar-acessos.sh`) continua verde.
- [ ] **Step 7: Commit** — `git add admin/dados.php assets/js/admin-dados.js assets/css/admin.css uploads/.htaccess .gitignore` · `feat(admin): enviar, trocar, reordenar e excluir patrocinadores em Dados do Evento`
