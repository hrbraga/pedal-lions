# Níveis de acesso no painel – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cada administrador só vê e abre as páginas do painel que foram liberadas para ele (ou todas, com "Acesso total").

**Architecture:** Regras puras em `includes/acessos.php` (sem sessão nem banco, testadas por script PHP). O `_bootstrap.php` lê `acesso_total`/`paginas` do banco junto com a senha em `admin_logado()` e cada página chama `admin_exigir('<chave>')`. Menu, login e a tela Administradores usam as mesmas funções. Trocar a própria senha passa para `admin/conta.php` (Minha conta), aberta a todos.

**Tech Stack:** PHP 8.2 (XAMPP; nada exclusivo de 8.3+), PDO MySQL, HTML sem framework. Testes: `C:\xampp\php\php tests/<x>_test.php` (helper `confere()`, sai com código 1 se falhar) e cenário de navegador no harness CDP do scratchpad (`node cdp.js <cenario>`, servidor `php -S localhost:8123`).

**Spec:** `docs/superpowers/specs/2026-11-26-niveis-de-acesso-design.md`

## Global Constraints

- Chaves das páginas e ordem do menu, nesta ordem: `inscritos` (Inscritos, painel.php), `cupons` (Cupons, cupons.php), `dados` (Dados do Evento, dados.php), `admins` (Administradores, administradores.php), `exportar` (Exportar CSV, exportar.php).
- Colunas novas em `admins`: `acesso_total TINYINT(1) NOT NULL DEFAULT 1` e `paginas VARCHAR(255) NOT NULL DEFAULT ''` (chaves separadas por vírgula). Os administradores que já existem ficam com acesso total.
- "Acesso total" libera qualquer chave, inclusive as que ainda não existem.
- Textos fixos: `Você não tem acesso a essa página.` · `Você não pode alterar o seu próprio acesso.` · `É preciso manter pelo menos um administrador com acesso total.` · `Marque pelo menos uma página ou "Acesso total".` · `Você ainda não tem acesso a nenhuma página do painel. Peça a um administrador para liberar.`
- Permissões lidas do banco a cada requisição (nada de cache na sessão).
- Ninguém altera o próprio acesso; sempre resta ao menos um administrador com acesso total.
- Commits só locais nesta branch (`feat/niveis-de-acesso`); sem push nem merge sem pedir.

## Review Focus

1. Administrador sem acesso total, mas com a página Administradores, tenta rebaixar ou remover o único administrador com acesso total → recusado (testes da Task 1 + cenário da Task 4).
2. Acesso retirado enquanto a pessoa está com um formulário aberto (ex.: salvar Dados do Evento, confirmar pagamento) → o POST é recusado e nada é gravado (cenário da Task 2, POST para `acao.php`).
3. Valores forjados ou antigos em `paginas` (`paginas[]=xyz`, chave que deixou de existir) → ignorados (testes de `acesso_paginas_normalizar`/`acesso_paginas_ler`).
4. Pessoa sem nenhuma página → login cai em Minha conta com o aviso, sem laço de redirecionamento (cenário da Task 3).
5. Quem tem só Exportar CSV (sem Inscritos) e a exportação falha → volta para a própria página inicial, não para `painel.php` (passo da Task 2).

## Rulings já tomadas no plano

- "Sem nenhuma página liberada, vê só um aviso e o botão Sair" (spec) é feito em `conta.php`: a pessoa vê o aviso, o formulário da própria senha e Sair. Evita uma página `sem-acesso.php` a mais; a spec já diz que Minha conta é aberta a todos.
- Criar administrador sem marcar nenhuma página é recusado (provável esquecimento); em "Editar acesso" deixar sem páginas é permitido (serve para suspender alguém).

---

### Task 1: Regras de acesso (funções puras)

**Files:**
- Create: `includes/acessos.php`
- Test: `tests/acessos_test.php`

**Interfaces:**
- Produces:
  - `const ACESSO_PAGINAS` — `chave => ['rotulo' => string, 'url' => string]`, na ordem dos Global Constraints.
  - `acesso_paginas_normalizar(array $chaves): array` — só chaves conhecidas (strings), sem repetição, na ordem de `ACESSO_PAGINAS`.
  - `acesso_paginas_ler(string $texto): array` — `'cupons,inscritos'` → `['inscritos','cupons']` (normalizado).
  - `acesso_paginas_texto(array $chaves): string` — normaliza e junta com vírgula.
  - `acesso_pode(bool $total, array $paginas, string $pagina): bool`
  - `acesso_inicio(bool $total, array $paginas): string` — URL da primeira página liberada na ordem do menu; `'conta.php'` se nenhuma.
  - `acesso_descricao(bool $total, array $paginas): string` — `'Acesso total'`, rótulos separados por `', '`, ou `'Nenhuma página'`.
  - `acesso_erro_alterar(int $meu_id, int $alvo_id, bool $alvo_total, bool $novo_total, int $qtd_total): ?string`
  - `acesso_erro_remover(int $meu_id, int $alvo_id, bool $alvo_total, int $qtd_total, int $qtd_admins): ?string`

- [ ] **Step 1: Escrever o teste** `tests/acessos_test.php` no formato de `tests/inscricoes_test.php` (`confere()`, `exit($falhas ? 1 : 0)`), com:

```php
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
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `C:\xampp\php\php tests/acessos_test.php`
Expected: erro fatal "Failed opening required ... includes/acessos.php" (arquivo ainda não existe).

- [ ] **Step 3: Implementar `includes/acessos.php`** com a constante e as funções da seção Interfaces. Sem `require` de banco ou sessão. Verificação de erro na ordem dos testes (próprio → único administrador → último total).

- [ ] **Step 4: Rodar e ver passar**

Run: `C:\xampp\php\php tests/acessos_test.php`
Expected: todas as linhas `ok -`, código de saída 0.

- [ ] **Step 5: Commit**

```bash
git add includes/acessos.php tests/acessos_test.php
git commit -m "feat(admin): regras de acesso por página (funções puras e testes)"
```

---

### Task 2: Banco, guarda das páginas, login e menu

**Files:**
- Modify: `includes/db.php` (`esquema_atualizar`), `database/schema.sql` (tabela `admins`)
- Modify: `admin/_bootstrap.php`, `admin/_topo.php`, `admin/login.php`, `admin/index.php`
- Modify (uma linha cada): `admin/painel.php`, `admin/inscrito.php`, `admin/acao.php` → `admin_exigir('inscritos')`; `admin/exportar.php` → `'exportar'`; `admin/cupons.php` → `'cupons'`; `admin/dados.php` → `'dados'`; `admin/administradores.php` → `'admins'`
- Test: scratchpad `cen-acessos.js` (não versionado)

**Interfaces:**
- Consumes: tudo da Task 1.
- Produces (em `_bootstrap.php`, que passa a fazer `require_once __DIR__ . '/../includes/acessos.php'`):
  - `admin_acesso(?array $definir = null): array` — guarda `['total' => bool, 'paginas' => array]` da requisição; padrão `['total' => false, 'paginas' => []]`. `admin_logado()` define a partir de `SELECT senha_hash, acesso_total, paginas`.
  - `admin_pode(string $pagina): bool`, `admin_inicio(): string`
  - `admin_exigir(?string $pagina = null): void` — não logado → `login.php`; logado sem a página → `flash('erro', 'Você não tem acesso a essa página.')` e redireciona para `admin_inicio()`.

- [ ] **Step 1: Escrever o cenário de navegador** `cen-acessos.js` no scratchpad. Ajudantes: `sql()` (como em `cen-strava.js`), `entrar(usuario, senha)` (chama `Network.clearBrowserCookies`, abre `login.php`, preenche e envia), `abrir(url)` que devolve `{ url: location.pathname, aviso: texto de .adm-aviso, menu: textos de .adm-menu a }`. Cria o usuário `teste-acesso` (senha `Teste-acesso-123`, hash feito com `C:\xampp\php\php -r "echo password_hash(...);"`) com `acesso_total=0, paginas='cupons'` e o apaga no `finally`. Verificações desta task:
  - `out.menu`: depois de entrar, o menu tem só `Cupons`, `Minha conta`, `Ver site`, `Sair (teste-acesso)`; a página aberta é `cupons.php`.
  - `out.painel` e `out.dados`: abrir `painel.php` e `dados.php` termina em `cupons.php` com o aviso `Você não tem acesso a essa página.`
  - `out.acao`: um `fetch` POST para `acao.php` (com `csrf` lido da página de cupons, `acao=confirmar`, `id` de uma inscrição pendente se houver) é recusado: o status dessa inscrição no banco não muda.
  - `out.na_hora`: `UPDATE admins SET paginas='cupons,dados'` → abrir `dados.php` funciona sem sair e entrar de novo.
  - `out.admin`: entrar como `admin` (senha `Pedal-536d59bf`) → menu com todas as páginas na ordem dos Global Constraints e a página aberta é `painel.php`.

- [ ] **Step 2: Rodar e ver falhar**

Run (com `php -S localhost:8123` e o MySQL no ar): `node cdp.js acessos`
Expected: `out.painel.url` é `/admin/painel.php` (sem guarda) e o menu ainda mostra todas as páginas — falha das verificações acima.

- [ ] **Step 3: Banco.** Em `esquema_atualizar`, se `SHOW COLUMNS FROM admins LIKE 'acesso_total'` não achar, `ALTER TABLE admins ADD COLUMN acesso_total ... , ADD COLUMN paginas ...` (valores dos Global Constraints). Mesmas colunas no `CREATE TABLE admins` do `schema.sql`.

- [ ] **Step 4: `_bootstrap.php`** com as funções da seção Interfaces; `admin_logado()` passa a buscar as três colunas e chama `admin_acesso([...])` antes de retornar true.

- [ ] **Step 5: Guardas nas páginas** (lista em Files). Em `exportar.php`, o redirecionamento da falha passa de `painel.php` para `admin_inicio()`.

- [ ] **Step 6: Login e entrada.** `login.php`: já logado → `admin_inicio()`; o `SELECT` do login traz `acesso_total, paginas` e o destino é `acesso_inicio((bool) $adm['acesso_total'], acesso_paginas_ler($adm['paginas']))`. `index.php`: `admin_logado() ? admin_inicio() : 'login.php'`.

- [ ] **Step 7: Menu (`_topo.php`).** Laço em `ACESSO_PAGINAS` mostrando só `admin_pode($chave)`; `aria-current` quando `$menu_ativo === $chave` (Exportar continua sem destaque); depois `Minha conta` (`conta.php`, `$menu_ativo === 'conta'`), `Ver site`, `Sair (...)`. O link da marca vai para `admin_inicio()`. O botão "Exportar CSV" de `painel.php` só aparece com `admin_pode('exportar')`.

- [ ] **Step 8: Rodar e ver passar**

Run: `node cdp.js acessos` e `C:\xampp\php\php tests/acessos_test.php`
Expected: verificações da Step 1 todas corretas, `out.erros` vazio; teste PHP todo `ok`.
(`conta.php` ainda não existe: o link aparece no menu, mas não é aberto nesta task.)

- [ ] **Step 9: Commit**

```bash
git add includes/db.php database/schema.sql admin/
git commit -m "feat(admin): cada página do painel exige a permissão correspondente"
```

---

### Task 3: Minha conta

**Files:**
- Create: `admin/conta.php`
- Modify: `admin/administradores.php` (sai a ação `minha_senha` e o formulário "Minha senha"; a mensagem de `redefinir` para si mesmo passa a citar "Minha conta")
- Test: `cen-acessos.js` (acrescentar)

**Interfaces:**
- Consumes: `admin_exigir()` sem página, `admin_acesso()`, `admin_inicio()` (Task 2).
- Produces: `conta.php` com `$menu_ativo = 'conta'`; POST `acao=minha_senha` com os campos `atual`, `senha`, `confirmar` (mesma lógica de hoje: bloqueio por tentativas, senha atual, mínimo de 10 caracteres, diferente da atual, `session_regenerate_id` e nova `admin_sig`).

- [ ] **Step 1: Acrescentar ao cenário:**
  - `out.sem_paginas`: `UPDATE admins SET paginas=''` para `teste-acesso`, entrar de novo → a página é `conta.php`, mostra `Você ainda não tem acesso a nenhuma página do painel. Peça a um administrador para liberar.`, menu só `Minha conta`, `Ver site`, `Sair (...)`.
  - `out.senha`: em `conta.php`, trocar a senha para `Teste-acesso-456` → aviso `Sua senha foi alterada.`; sair e entrar com a nova funciona.
  - `out.sem_form_antigo`: `administradores.php` (como `admin`) não tem mais `input[name=acao][value=minha_senha]`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `node cdp.js acessos`
Expected: `out.sem_paginas.url` é `/admin/conta.php`, mas a página responde 404 (não existe) → verificações falham.

- [ ] **Step 3: Criar `admin/conta.php`** (título "Minha conta"; aviso de sem páginas quando `!admin_acesso()['total'] && !admin_acesso()['paginas']`; formulário de troca de senha movido de `administradores.php`, redirecionando para `conta.php`). `SENHA_MINIMA` e `erros_senha()` passam para `_bootstrap.php`, usados pelas duas páginas.

- [ ] **Step 4: Tirar de `administradores.php`** a ação `minha_senha` e a seção "Minha senha".

- [ ] **Step 5: Rodar e ver passar**

Run: `node cdp.js acessos`
Expected: todas as verificações (Task 2 + Task 3) corretas, `out.erros` vazio.

- [ ] **Step 6: Commit**

```bash
git add admin/
git commit -m "feat(admin): página Minha conta, aberta a todos, para trocar a própria senha"
```

---

### Task 4: Tela Administradores com páginas por pessoa

**Files:**
- Modify: `admin/administradores.php`, `assets/css/admin.css` (classe `.adm-checks` para a lista de caixas)
- Test: `cen-acessos.js` (acrescentar)

**Interfaces:**
- Consumes: `ACESSO_PAGINAS`, `acesso_paginas_normalizar`, `acesso_paginas_texto`, `acesso_paginas_ler`, `acesso_descricao`, `acesso_erro_alterar`, `acesso_erro_remover` (Task 1).
- Produces: POST `acao=criar` com `acesso_total` (checkbox, valor `1`) e `paginas[]` (chaves); POST `acao=acesso` com `id`, `acesso_total`, `paginas[]`; `acao=remover` passa a usar `acesso_erro_remover`.

- [ ] **Step 1: Acrescentar ao cenário** (como `admin`, usuário de teste `teste-acesso2`, apagado no `finally`):
  - `out.criar`: criar `teste-acesso2` só com Inscritos e Exportar → na lista aparece `Inscritos, Exportar CSV`; no banco `acesso_total=0, paginas='inscritos,exportar'`.
  - `out.criar_vazio`: criar sem nenhuma caixa marcada → `Marque pelo menos uma página ou "Acesso total".` e nada criado.
  - `out.editar`: "Editar acesso" de `teste-acesso2` para Cupons → lista mostra `Cupons`.
  - `out.proprio`: a linha do `admin` não tem "Editar acesso"; POST forjado `acao=acesso` com o próprio id → `Você não pode alterar o seu próprio acesso.`
  - `out.ultimo_total`: com `teste-acesso2` recebendo `acesso_total=0, paginas='admins'` e `admin` como único total, entrar como `teste-acesso2` e mandar POST `acao=acesso` tirando o total de `admin` → `É preciso manter pelo menos um administrador com acesso total.`; mandar `acao=remover` para `admin` → mesma mensagem; `admin` continua com `acesso_total=1`.
  - `out.forjado`: criar com `paginas[]=xyz&paginas[]=cupons` → gravado `cupons`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `node cdp.js acessos`
Expected: o formulário de criar não tem caixas de páginas; `out.criar` grava `acesso_total=1` → falha.

- [ ] **Step 3: Implementar** em `administradores.php`:
  - Contagens via SQL: `SELECT COUNT(*) FROM admins WHERE acesso_total = 1`.
  - `criar`: lê `acesso_total` e `paginas[]` (normalizados); recusa com o texto fixo se nenhum dos dois; `INSERT` com `acesso_total` e `paginas` (vazio quando total). As caixas marcadas voltam marcadas se houver erro.
  - `acesso` (novo): alvo existe; `acesso_erro_alterar(...)`; `UPDATE admins SET acesso_total, paginas`; flash `Acesso de "<usuário>" atualizado.`
  - `remover`: troca as checagens atuais por `acesso_erro_remover(...)`.
  - Tabela: coluna "Acesso" com `acesso_descricao()`; nas linhas que não são a própria, um `<details><summary>Editar acesso</summary>` com o formulário (caixas já marcadas conforme o banco).
  - Texto do topo trocado: "Cada administrador só vê as páginas liberadas para ele. Quem tem a página Administradores pode criar pessoas e mudar o acesso dos outros."
  - Caixa "Acesso total (todas as páginas, inclusive as que forem criadas depois)" acima das caixas das páginas.

- [ ] **Step 4: Rodar e ver passar**

Run: `node cdp.js acessos`, `C:\xampp\php\php tests/acessos_test.php`, `C:\xampp\php\php tests/evento_dados_test.php`, `C:\xampp\php\php tests/evento_validar_test.php`, `C:\xampp\php\php tests/inscricoes_test.php`, `node --test tests/`
Expected: cenário todo correto e `out.erros` vazio; todos os testes PHP `ok`; `node --test` 9/9.

- [ ] **Step 5: Commit**

```bash
git add admin/administradores.php assets/css/admin.css
git commit -m "feat(admin): escolher as páginas de cada administrador ao criar e editar"
```
