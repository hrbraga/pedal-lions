# Dados do Evento no painel – plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Página "Dados do Evento" no painel que edita tudo o que hoje está em `config/evento.php`, gravando versões no MySQL que o site passa a ler.

**Architecture:** Uma tabela `evento_versoes` guarda cada gravação como JSON; `evento()` mescla `config/evento.php` (padrão) com a versão mais recente e cai de volta no arquivo se o banco falhar. A validação e o tratamento do mapa do Strava são funções puras testadas por script PHP; a página do painel (`admin/dados.php` + `assets/js/admin-dados.js`) só monta o formulário, chama a validação e grava.

**Tech Stack:** PHP 8 + PDO/MySQL (XAMPP e HostGator), JavaScript sem bibliotecas, testes em scripts PHP (`php tests/<arquivo>.php`) e cenários de navegador via CDP (Edge headless, como nos testes do avatar).

**Spec:** `docs/superpowers/specs/2026-11-26-dados-do-evento-design.md`

## Global Constraints

- Sem bibliotecas novas. Nada de funções só do PHP 8.1+ (`array_is_list`, enums, `readonly`): a hospedagem pode estar em 8.0.
- `evento()` continua sendo o único ponto de leitura dos dados; o site **nunca** cai se o banco falhar (usa `config/evento.php`).
- Nenhum campo do painel aceita HTML; todo texto é exibido com `e()`. O mapa do Strava é montado pelo site a partir de dados extraídos.
- Todo POST do painel exige `admin_exigir()` e `csrf_valido()`.
- Mantêm-se as **50** versões mais recentes.
- Textos da interface em português, no tom das telas existentes do painel.
- Regulamento: só a frase do prazo e das vagas (linha 37 de `regulamento.php`) passa a usar os dados; o resto do texto fica como está.

## Review Focus

1. **Banco fora do ar ou tabela inexistente:** o site inteiro (home, inscrição, rotas, regulamento) abre com os dados do arquivo, sem aviso de erro ao público.
2. **Categoria com inscritos:** o identificador (`slug`) nunca muda e o POST não consegue removê-la, mesmo que alguém altere o HTML do formulário.
3. **Mapa do Strava colado com `<script>` ou atributos estranhos:** só id, token, estilo e map-hash sobrevivem; reabrir e salvar sem mexer mantém o token.
4. **Duas abas salvando:** a segunda recebe o aviso de conflito e nada é gravado.
5. **Entrada com vírgula, espaços ou vazia** (valor "70,00", WhatsApp "43 99675-9480", vagas "") normaliza ou dá erro claro, sem quebrar a página.

---

### Task 1: Armazenamento das versões e leitura pelo site

**Files:**
- Create: `includes/evento_dados.php`
- Modify: `includes/funcoes.php` (função `evento()`, linhas 19-26)
- Modify: `includes/db.php` (`esquema_atualizar()`, criar `evento_versoes`)
- Modify: `database/schema.sql` (mesma tabela)
- Modify: `config/evento.php` (comentário do topo)
- Test: `tests/evento_dados_test.php`

**Interfaces:**
- Produces:
  - `evento_mesclar(array $base, array $salvo): array` — chaves associativas mesclam recursivamente; listas (chaves 0..n-1) e valores simples são substituídos inteiros.
  - `eh_lista(array $a): bool` — substituto de `array_is_list`.
  - `evento_datas(string $iso): array` → `['data_curta' => '14/03/2027', 'data_extenso' => 'Domingo, 14 de março de 2027']`; ISO inválido → as duas chaves com o próprio `$iso`.
  - `evento_versao_atual(PDO $pdo): ?array` → `['id' => int, 'dados' => array, 'salvo_em' => string, 'salvo_por' => string]` ou null.
  - `evento_versoes(PDO $pdo, int $limite = 50): array` — lista sem `dados`, mais nova primeiro, com `restaurada_de`.
  - `evento_versao(PDO $pdo, int $id): ?array` — uma versão com `dados`.
  - `evento_salvar(PDO $pdo, array $dados, string $usuario, ?int $restaurada_de = null): int` — insere, apaga além das 50 mais recentes, devolve o novo id.
  - `evento_padrao(): array` — só o conteúdo de `config/evento.php` (usado pelo painel para comparar e preencher).
  - Tabela `evento_versoes(id INT UNSIGNED AI PK, dados LONGTEXT NOT NULL, salvo_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, salvo_por VARCHAR(50) NOT NULL, restaurada_de INT UNSIGNED NULL)`.

- [ ] **Step 0: Guardar o HTML de referência** (antes de mudar qualquer arquivo): com `php -S localhost:8123` e o MySQL ligado, salvar no scratchpad a saída de `index.php`, `rotas.php`, `programacao.php`, `faq.php`, `regulamento.php` e `inscricao.php`.

- [ ] **Step 1: Write the failing test** `tests/evento_dados_test.php`, no formato de `tests/inscricoes_test.php` (função `confere`, saída `ok -`/`FALHOU -`, `exit(1)` se falhar):

```php
confere('simples substitui', evento_mesclar(['a' => 1, 'b' => 2], ['a' => 9]), ['a' => 9, 'b' => 2]);
confere('associativo mescla', evento_mesclar(['c' => ['x' => 1, 'y' => 2]], ['c' => ['y' => 5]]), ['c' => ['x' => 1, 'y' => 5]]);
confere('lista substitui inteira', evento_mesclar(['l' => [1, 2, 3]], ['l' => [7]]), ['l' => [7]]);
confere('lista vazia substitui', evento_mesclar(['l' => [1]], ['l' => []]), ['l' => []]);
confere('chave nova entra', evento_mesclar(['a' => 1], ['z' => 2]), ['a' => 1, 'z' => 2]);
confere('datas', evento_datas('2027-03-14'), ['data_curta' => '14/03/2027', 'data_extenso' => 'Domingo, 14 de março de 2027']);
confere('datas sábado', evento_datas('2027-01-02')['data_extenso'], 'Sábado, 2 de janeiro de 2027');
confere('data inválida', evento_datas('x'), ['data_curta' => 'x', 'data_extenso' => 'x']);
```

E um bloco que só roda se `db()` conectar (senão imprime `pulado - banco indisponível`): salva uma versão com `['local_largada' => 'TESTE-LOCAL']` por `'teste'`, confere `evento_versao_atual()['dados']['local_largada']`, salva 51 versões e confere que restam 50, e no fim apaga as linhas com `salvo_por = 'teste'`. **Antes de começar, se a tabela já tiver versões de verdade, o bloco é pulado** (para não apagar o histórico de ninguém).

- [ ] **Step 2: Run test to verify it fails**

Run: `/c/xampp/php/php tests/evento_dados_test.php`
Expected: erro fatal de arquivo/função inexistente.

- [ ] **Step 3: Implement** `includes/evento_dados.php` (requer `db.php`), a tabela em `esquema_atualizar()` e em `schema.sql`, e a nova `evento()`:

```php
function evento(): array
{
    static $dados = null;
    if ($dados === null) {
        $dados = evento_padrao();
        try {
            require_once __DIR__ . '/evento_dados.php';
            $atual = evento_versao_atual(db());
            if ($atual) $dados = evento_mesclar($dados, $atual['dados']);
        } catch (Throwable $e) {
            error_log('Pedal Solidário – dados do evento: usando config/evento.php (' . $e->getMessage() . ')');
        }
        $dados = array_merge($dados, evento_datas((string) ($dados['data_iso'] ?? '')));
    }
    return $dados;
}
```

`evento_padrao()` fica em `funcoes.php` (é só o `require` do config, com cache estático). Comentário no topo de `config/evento.php`: "Depois que alguém salvar em Painel → Dados do Evento, os valores salvos lá valem por cima destes."

- [ ] **Step 4: Run test to verify it passes**

Run: `/c/xampp/php/php tests/evento_dados_test.php` (com o MySQL ligado)
Expected: todos `ok`, `todos passaram`, saída 0.

- [ ] **Step 5: Verificar o site sem versão salva e com o banco desligado**

Com o MySQL ligado e sem versões: gerar de novo o HTML das seis páginas e comparar com o do Step 0.
Expected: idêntico, exceto o `?v=` dos assets e o token CSRF da inscrição.
Com o MySQL desligado: as mesmas páginas respondem 200 e mostram os dados do arquivo.

- [ ] **Step 6: Commit**

```bash
git add includes/evento_dados.php includes/funcoes.php includes/db.php database/schema.sql config/evento.php tests/evento_dados_test.php
git commit -m "feat(evento): store event data versions in MySQL, read with file fallback"
```

---

### Task 2: Validação dos dados e mapa do Strava

**Files:**
- Create: `includes/evento_validar.php`
- Test: `tests/evento_validar_test.php`

**Interfaces:**
- Consumes: estrutura de `evento()` (Task 1).
- Produces:
  - `strava_extrair(string $colado): ?array` → `['id' => '3456363824372913536', 'token' => '...', 'style' => 'standard', 'map_hash' => '11.95/-23.19/-50.59']` (só as chaves encontradas além de `id`). Aceita link `strava.com/routes/<dígitos>` ou o código de incorporação (`data-embed-type="route"` + `data-embed-id`). Valores filtrados: id `\d{1,25}`, token `[A-Za-z0-9_-]{1,100}`, style `[a-z-]{1,20}`, map_hash `[0-9.\/-]{1,60}`. Nada reconhecível → null.
  - `strava_link(array $mapa): string` → `https://www.strava.com/routes/<id>`.
  - `strava_html(array $mapa): string` → `<div class="strava-embed-placeholder" data-embed-type="route" data-embed-id="…" data-full-width="true" …>` com cada atributo passado por `e()`; **sem** `<script>` (o script é incluído uma vez por `rotas.php`).
  - `slug_de(string $nome, array $existentes): string` — minúsculas, sem acento, hífens; colisão → sufixo `-2`, `-3`…
  - `evento_validar(array $entrada, array $atual, array $categorias_com_inscricao): array` → `['dados' => array, 'erros' => array]`.
    - `$entrada`: o `$_POST` com as chaves `geral[nome|organizador|data_iso|cidade|uf|local_largada]`, `inscricoes[abertas|prazo_iso|vagas|taxa]`, `contato[email|whatsapp|instagram]`, `camiseta[valor|prazo_iso|tamanhos][]`, `categorias[i][slug|nome|valor|resumo]`, `premiacoes[i][titulo|regra|premio]`, `rotas[i][nome|distancia|altimetria|descricao|mapa]`, `programacao[i][hora|titulo|desc]`, `faq[i][p|r]`, `patrocinadores[<arquivo>][nome|url]`. Datas chegam como `AAAA-MM-DD` (campos `type="date"`).
    - `$atual`: o `evento()` em vigor (para manter token do Strava e saber os slugs existentes).
    - `$categorias_com_inscricao`: slugs com pelo menos uma inscrição.
    - `dados` sai na estrutura de `evento()`: `nome, organizador, data_iso, cidade, uf, local_largada, inscricoes_abertas (bool), inscricoes_prazo_iso, inscricoes_vagas (int), taxa_administracao['percentual'] (float), contato[...], camiseta['valor','prazo_iso','tamanhos'], categorias[], premiacoes[], rotas[] (cada uma com 'mapa' => array|null), programacao[], faq[], patrocinadores_info[arquivo => ['nome','url']]`.
    - `erros`: `['caminho.do.campo' => 'mensagem']`, ex.: `'categorias.1.valor' => 'Valor inválido.'`.

- [ ] **Step 1: Write the failing test** `tests/evento_validar_test.php`. Monte `$valida` = entrada completa e correta a partir do `config/evento.php` atual e confira:

```php
confere('entrada válida não tem erros', $r['erros'], []);
confere('valor com vírgula vira número', $r['dados']['categorias'][0]['valor'], 70.0);
confere('whatsapp formatado', $r['dados']['contato']['whatsapp'], '(43) 99675-9480');   // entrada "43 996759480"
confere('vagas vira inteiro', $r['dados']['inscricoes_vagas'], 300);
confere('abertas desmarcado = false', $sem_abertas['dados']['inscricoes_abertas'], false);   // entrada sem a chave inscricoes[abertas]
confere('data inválida', isset(erros('geral.data_iso', 'data_iso' => '2027-02-30')), true);
confere('prazo depois do evento', isset(erros('inscricoes.prazo_iso', '2027-03-15')), true);
confere('prazo camiseta depois do evento', isset(...'camiseta.prazo_iso'...), true);
confere('valor negativo', isset(...'categorias.0.valor' com '-1'...), true);
confere('taxa acima de 100', isset(...'inscricoes.taxa' com '101'...), true);
confere('e-mail inválido', isset(...'contato.email'...), true);
confere('instagram sem https', isset(...'contato.instagram' com 'instagram.com/x'...), true);
confere('hora inválida', isset(...'programacao.0.hora' com '7h30'...), true);
confere('sem categorias', isset(...'categorias'...), true);
confere('sem tamanhos', isset(...'camiseta.tamanhos'...), true);
confere('categoria com inscritos removida', isset(...'categorias' quando falta 'rachao' e rachao está em $categorias_com_inscricao...), true);
confere('categoria nova ganha slug', $r['dados']['categorias'][4]['slug'], 'gravel');          // nome "Gravel", slug vazio
confere('slug repetido ganha sufixo', slug_de('Hard', ['hard']), 'hard-2');
confere('slug de categoria existente não muda', /* slug 'rachao' enviado com nome 'Rachão Pro' */ 'rachao', 'rachao');
confere('slug inventado no POST é ignorado', /* slug 'xyz' que não existe em $atual → gera do nome */ ...);
confere('strava link', strava_extrair('https://www.strava.com/routes/3456363824372913536'), ['id' => '3456363824372913536']);
confere('strava embed', strava_extrair($embed_do_config_rota_1), ['id' => '3456356447837639954', 'token' => '15S0cgPwjMxxv8HRKnFsKR5iP-Nu139ZdaQhXPnC5UI', 'style' => 'standard', 'map_hash' => '11.95/-23.19164/-50.59554']);
confere('strava com script e onerror', strava_extrair('<div data-embed-type="route" data-embed-id="123" onerror="x()"></div><script>alert(1)</script>'), ['id' => '123']);
confere('strava lixo', strava_extrair('olá'), null);
confere('strava html sem script', strpos(strava_html(['id' => '1', 'token' => '"><x']), '<script'), false);
confere('strava html escapa', strpos(strava_html(['id' => '1', 'token' => '"><x']), '"><x'), false);
confere('reabrir com o link mantém token', $r['dados']['rotas'][0]['mapa']['token'] ?? null, '15S0c…');  // entrada = strava_link(mapa atual)
confere('mapa vazio = sem mapa', $r['dados']['rotas'][0]['mapa'], null);
confere('mapa irreconhecível dá erro', isset(...'rotas.0.mapa' com 'olá'...), true);
confere('texto com HTML fica como texto', $r['dados']['faq'][0]['r'], '<b>oi</b>');  // guardado cru; e() escapa na exibição
```

(`erros(...)` é um auxiliar do teste que copia `$valida`, troca um campo e devolve `evento_validar(...)['erros']`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `/c/xampp/php/php tests/evento_validar_test.php`
Expected: erro fatal de arquivo inexistente.

- [ ] **Step 3: Implement** `includes/evento_validar.php`. Tamanhos máximos: nome/organizador/local 150, cidade 100, títulos 120, resumo/regra/prêmio/descrição 300, resposta do FAQ 2000, tamanho de camiseta 5 (únicos, maiúsculos). Itens de lista totalmente vazios (todos os campos em branco) são descartados antes de validar. Patrocinadores: só arquivos que existem na pasta; url vazia ou `https?://`.

- [ ] **Step 4: Run test to verify it passes**

Run: `/c/xampp/php/php tests/evento_validar_test.php`
Expected: `todos passaram`.

- [ ] **Step 5: Commit**

```bash
git add includes/evento_validar.php tests/evento_validar_test.php
git commit -m "feat(evento): validate event data and parse Strava maps safely"
```

---

### Task 3: Página "Dados do Evento" no painel

**Files:**
- Create: `admin/dados.php`
- Create: `assets/js/admin-dados.js`
- Modify: `admin/_topo.php` (item de menu `Dados do Evento`, `$menu_ativo === 'dados'`)
- Modify: `assets/css/admin.css` (seções, linhas de lista, barra de salvar fixa)
- Test: cenário CDP `cen-dados.js` (scratchpad, junto do `cdp.js`)

**Interfaces:**
- Consumes: `evento()`, `evento_padrao()`, `evento_versao_atual()`, `evento_versoes()`, `evento_versao()`, `evento_salvar()` (Task 1); `evento_validar()`, `strava_link()` (Task 2); `admin_exigir()`, `flash()`, `csrf_token()`, `csrf_valido()`, `vagas_ocupadas()`.
- Produces: `admin/dados.php` com POST `acao=salvar` (campos da Task 2 + `versao` = id em vigor ou 0) e POST `acao=restaurar` (`id`).

- [ ] **Step 1: Write the failing browser scenario** `cen-dados.js` (cria um administrador de teste direto no banco com `password_hash`, faz login, e no fim apaga esse administrador e as versões que criou; se já houver versões de verdade, para sem fazer nada). Checagens:
  - `ok_menu`: o menu do painel tem "Dados do Evento".
  - `ok_preenchido`: campos vêm com os valores atuais (nome, data, 4 categorias, rotas com o link do Strava).
  - `ok_salva`: muda o local de largada e salva → mensagem de sucesso e `index.php`/`regulamento.php` mostram o novo local.
  - `ok_lista`: adiciona uma pergunta no FAQ, sobe ela para o topo, salva → `faq.php` mostra ela primeiro.
  - `ok_erro`: valor "abc" numa categoria → nada salvo, erro junto do campo, o resto do que foi digitado continua na tela.
  - `ok_categoria_travada`: com uma inscrição de teste na categoria `light`, o item Light não tem botão remover; um POST forjado sem ela devolve erro.
  - `ok_conflito`: duas abas abertas, a primeira salva, a segunda salva → aviso de conflito e a versão em vigor é a da primeira.
  - `ok_historico_restaura`: restaurar a versão anterior → o local volta ao valor antigo e o histórico mostra "restaurada da versão N".
  - `ok_sair_sem_salvar`: com um campo alterado, `beforeunload` está ativo; depois de salvar, não.
  - `ok_sem_erros_console`.

- [ ] **Step 2: Run to verify it fails**

Run: `node cdp.js dados` (servidor `php -S localhost:8123`, MySQL ligado)
Expected: `ok_menu: false` e as demais false (página não existe).

- [ ] **Step 3: Implement** `admin/dados.php`:
  - Lê `evento()`, as categorias com inscrição (`SELECT categoria, COUNT(*) FROM inscricoes GROUP BY categoria`) e o histórico.
  - Salvar: `csrf_valido` → `GET_LOCK('pedal_evento_dados', 10)` → se `versao` ≠ id em vigor, erro de conflito → `evento_validar` → com erros, re-renderiza com a entrada da pessoa e os erros; sem erros, `evento_salvar($pdo, $dados, $_SESSION['admin_usuario'])`, `RELEASE_LOCK`, flash de sucesso (mais o aviso se `inscricoes_vagas` > 0 e menor que `vagas_ocupadas()`), redirect para `dados.php`.
  - Restaurar: confirma que o id existe → `evento_salvar($pdo, $versao['dados'], usuário, $id)` → flash → redirect.
  - Topo: "Última alteração: <data> por <usuário>" (ou "Valores iniciais do arquivo de configuração" sem versão) e o índice de seções com âncoras.
  - Formulário com as seções e campos do spec; listas renderizadas pelo PHP com índices `0..n-1`, mais um `<template>` por lista para o JavaScript clonar. Categoria com inscritos mostra "N inscritos – não pode ser excluída" no lugar do botão remover.

  `assets/js/admin-dados.js`: botões adicionar (clona o `<template>`), remover, subir e descer; antes de enviar, renumera os índices dos `name` de cada lista; marca "alterado" em qualquer `input` e liga/desliga o `beforeunload`.

- [ ] **Step 4: Run to verify it passes**

Run: `node cdp.js dados`
Expected: todas as chaves `ok_*` true e `erros: []`.

- [ ] **Step 5: Commit**

```bash
git add admin/dados.php admin/_topo.php assets/js/admin-dados.js assets/css/admin.css
git commit -m "feat(admin): Dados do Evento page with lists, validation, history and restore"
```

---

### Task 4: Site usando os dados novos

**Files:**
- Modify: `rotas.php` (mapa a partir de `$rota['mapa']`)
- Modify: `regulamento.php:37` (prazo e vagas vindos dos dados)
- Modify: `README.md` (como usar a página; `config/evento.php` vira padrão)
- Test: cenário CDP `cen-dados.js` (mais checagens) e os testes PHP

**Interfaces:**
- Consumes: `strava_html()`, `strava_extrair()` (Task 2); `evento()` (Task 1).

- [ ] **Step 1: Acrescentar ao cenário** `cen-dados.js`:
  - `ok_rotas_mapa`: depois de salvar pelo painel, `rotas.php` tem um `.strava-embed-placeholder` com o `data-embed-id` certo por rota e **um** `<script src="https://strava-embeds.com/embed.js">` na página.
  - `ok_regulamento_dinamico`: mudar vagas para 250 e o prazo para 10/03/2027 → `regulamento.php` diz "até 10/03/2027 ou até o preenchimento das 250 vagas"; com vagas 0 diz só "até 10/03/2027".
  - `ok_datas`: mudar a data do evento para 2027-03-21 → home mostra "Domingo, 21 de março de 2027" e o rodapé também.

- [ ] **Step 2: Run to verify it fails**

Run: `node cdp.js dados`
Expected: `ok_rotas_mapa` e `ok_regulamento_dinamico` false.

- [ ] **Step 3: Implement**
  - `rotas.php`: se `!empty($rota['mapa'])` → `strava_html($rota['mapa'])`; senão, se houver `mapa_embed` (só vem do arquivo de config, conteúdo de confiança), mantém a saída atual; senão `mapa_url`/aviso como hoje. O `<script>` do Strava é impresso uma vez no fim, se alguma rota usou `strava_html`.
  - `regulamento.php:37`: "As inscrições vão até <data_br(prazo)> ou até o preenchimento das <vagas> vagas, o que ocorrer primeiro." Sem prazo e sem vagas, a frase some; só um dos dois, só ele.
  - README: seção "Dados do Evento (painel)".

- [ ] **Step 4: Run all verifications**

Run: `node cdp.js dados`, `/c/xampp/php/php tests/evento_dados_test.php`, `/c/xampp/php/php tests/evento_validar_test.php`, `/c/xampp/php/php tests/inscricoes_test.php`, `node --test`
Expected: tudo verde; cenários antigos do avatar sem mudança.

- [ ] **Step 5: Commit**

```bash
git add rotas.php regulamento.php README.md
git commit -m "feat(site): Strava maps and regulation deadline from saved event data"
```
