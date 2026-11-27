# Pagamento pelo Mercado Pago — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O atleta paga a inscrição com Pix ou cartão (até 2x) num modal do site; o Mercado Pago aprova e a inscrição vira paga sozinha (e-mail de confirmação + Financeiro); pendente sem pagamento expira em 6 h e libera a vaga.

**Architecture:** `includes/mercadopago.php` fala com a API REST do Mercado Pago por cURL (sem SDK) e confere a assinatura do aviso. `includes/pagamento.php` tem as regras sobre o banco (CPF ativo, vaga, gerar Pix, processar um pagamento vindo da API). Três endpoints públicos finos (`pagamento_aviso.php`, `pagamento_status.php`, `pagamento_cartao.php`, mais `pagamento_pix.php` para tentar de novo) chamam essas regras. A expiração é um `UPDATE` feito em `db()` a cada requisição, então todo leitor (vagas, painel, dashboard, cupons) já enxerga as expiradas.

**Tech Stack:** PHP 8.2/8.3, PDO MySQL, cURL, PHPMailer (já no projeto), Card Payment Brick (`https://sdk.mercadopago.com/js/v2`), testes `tests/*_test.php` (`confere`) e cenários CDP no scratchpad (`node cdp.js <cenário>`, site local em `http://localhost:8123/`).

**Spec:** docs/superpowers/specs/2026-11-27-pagamento-mercado-pago-design.md (esboço do modal: docs/superpowers/specs/2026-11-27-pagamento-modal-esboco.html)

## Global Constraints

- Prazo: **6 horas** — constante única `PAGAMENTO_PRAZO_HORAS = 6` em `includes/pagamento.php`. `expira_em` é gravado pelo MySQL (`NOW() + INTERVAL 6 HOUR`).
- Cartão: no máximo **2 parcelas** (Brick `maxInstallments: 2`; servidor recusa `installments` fora de 1..2).
- Formas: só `pix` e cartão de crédito. Sem boleto, sem Checkout Pro, sem SDK PHP, sem Composer.
- Valor cobrado é sempre `inscricoes.valor_total` lido do banco; nada do navegador define valor.
- Status novo `expirado`, rótulo **`Não paga (expirada)`**. "Ativa" = `status IN ('pendente','pago')`.
- Um CPF só pode ter uma inscrição ativa; expiradas e canceladas não bloqueiam.
- Credenciais em `config/mercadopago.php` (no `.gitignore`), modelo `config/mercadopago.exemplo.php` com `access_token`, `public_key`, `webhook_secret` e `api` (padrão `https://api.mercadopago.com`). Nunca no git nem no chat. Sem o arquivo: comportamento de hoje (sem modal, sem `expira_em`, aviso "instruções de pagamento em breve").
- Nunca confiar no corpo do aviso: sempre `GET /v1/payments/{id}` antes de decidir.
- E-mail de confirmação no máximo uma vez (`email_pago_em`), como hoje.
- Destaques no painel (coluna `pagamento_alerta`), textos exatos: `paga sem vaga — devolver o dinheiro`, `pagamento em duplicidade — devolver o dinheiro`, `estornado no Mercado Pago`.
- Testes nunca usam a conta admin real; cenários que mexem em `config/mercadopago.php` ou `config/email.php` guardam e restauram o arquivo.
- Não publicar (merge/push em `main`) sem "pode publicar" do usuário.

## Review Focus

1. Aviso falso ou com valor diferente (alguém chama `pagamento_aviso.php` com um id qualquer, ou a API devolve outro valor) → não confirma, só loga. (Task 3: `aviso sem assinatura válida`, `valor divergente não confirma`)
2. O mesmo pagamento chega duas vezes (aviso repetido, aviso + consulta da página ao mesmo tempo) → uma confirmação e um e-mail só. (Task 3: `aviso repetido não reenvia`)
3. Atleta abre a aba Cartão com 5h59 e paga com 6h01 → servidor recusa; nada é cobrado. (Task 5: `cartão depois do prazo é recusado`)
4. MySQL da Hostinger em UTC e PHP em America/Sao_Paulo → horas do prazo erradas em 3 h. `db()` fixa o fuso da sessão. (Task 1: `relógio do banco = relógio do PHP`)
5. Mercado Pago fora do ar na hora da inscrição → inscrição gravada, modal oferece "Tentar de novo", e-mail sai só com o botão. (Task 4: `Mercado Pago fora do ar não perde a inscrição`)

---

### Task 1: Banco, expiração e "inscrição ativa"

**Files:**
- Modify: `includes/db.php` (fuso, colunas/tabela, `inscricoes_expirar`), `database/schema.sql`
- Modify: `includes/inscricoes.php:34-38` (`vagas_ocupadas`), `includes/cupons.php:18`, `admin/cupons.php:184-185`, `admin/painel.php:14,26,30`
- Modify: `admin/_bootstrap.php:27` (`STATUS_ROTULOS`), `assets/css/admin.css` (`.adm-selo--expirado`), `inscricao_recebida.php` (`$rotulos`)
- Modify: `inscricao.php:143-160` (CPF ativo, dentro da trava)
- Modify: `admin/acao.php` (`pago` em expirada; `pendente` zera `expira_em`), `admin/inscrito.php:63-72`
- Create: `includes/pagamento.php`
- Test: `tests/pagamento_test.php` (usa o banco local; linhas de teste com código `TSTPG…` e CPFs falsos, apagadas no `finally`)

**Interfaces:**
- Produces:
  - Em `includes/db.php`: `db()` passa a executar, nesta ordem, `SET time_zone = '<date('P')>'`, `esquema_atualizar($pdo)`, `inscricoes_expirar($pdo)`. `inscricoes_expirar(PDO $pdo): int` → linhas expiradas (`UPDATE inscricoes SET status='expirado' WHERE status='pendente' AND expira_em IS NOT NULL AND expira_em < NOW()`), nunca lança (loga).
  - Esquema (idempotente em `esquema_atualizar`, espelhado em `schema.sql`):
    - `inscricoes.status` ENUM com `'expirado'`; `cpf` deixa de ser UNIQUE (`DROP INDEX cpf`, `ADD INDEX idx_cpf (cpf)`).
    - novas colunas em `inscricoes`: `expira_em DATETIME NULL`, `pagamento_mp_id VARCHAR(30) NULL` (o pagamento que quitou), `pagamento_alerta VARCHAR(255) NULL`, `email_instr_em DATETIME NULL`, `email_instr_erro VARCHAR(255) NULL`.
    - tabela `pagamentos`: `id`, `inscricao_id INT UNSIGNED NOT NULL`, `mp_id VARCHAR(30) NOT NULL UNIQUE`, `metodo VARCHAR(20) NOT NULL` (`pix` ou a bandeira do cartão), `parcelas TINYINT UNSIGNED NULL`, `status VARCHAR(30) NOT NULL`, `status_detalhe VARCHAR(60) NULL`, `valor DECIMAL(8,2) NOT NULL`, `pix_copia_cola TEXT NULL`, `pix_qr_base64 MEDIUMTEXT NULL`, `pix_expira_em DATETIME NULL`, `consultado_em DATETIME NULL`, `criado_em`, `atualizado_em`, `INDEX (inscricao_id)`.
  - Em `includes/inscricoes.php`: `vagas_ocupadas(PDO $pdo): int` conta `status IN ('pendente','pago')`; nova `travar_vagas(PDO $pdo): bool` (o `GET_LOCK('pedal_solidario_vagas', 10)` que hoje está inline em `inscricao.php`).
  - Em `includes/pagamento.php`: `const PAGAMENTO_PRAZO_HORAS = 6;` `pagamento_cpf_ativo(PDO $pdo, string $cpf, int $exceto_id = 0): ?array` → `['id','status','expira_em']` da inscrição ativa ou `null`. `pagamento_tem_vaga(PDO $pdo, int $vagas): bool` → `$vagas <= 0 || vagas_ocupadas($pdo) < $vagas`.

- [ ] **Step 1: Write the failing test** — `tests/pagamento_test.php`:
  - `relógio do banco = relógio do PHP`: `abs(strtotime(db()->query('SELECT NOW()')->fetchColumn()) - time()) <= 5`.
  - insere pendente A com `expira_em = NOW() - INTERVAL 1 MINUTE` e pendente B com `expira_em = NOW() + INTERVAL 1 HOUR` e pendente C com `expira_em NULL`; `inscricoes_expirar(db())` → A vira `expirado`, B e C continuam `pendente`.
  - `vagas_ocupadas` antes/depois: diminui 1 quando A expira.
  - cupom de teste com `quantidade` 1 usado por A: após expirar, `cupom_usos(db(), id)` (a função de `includes/cupons.php:18`) === 0.
  - `pagamento_cpf_ativo(db(), cpfA)` === null depois de expirar; `pagamento_cpf_ativo(db(), cpfB)['status']` === `'pendente'`.
  - duas inscrições com o mesmo CPF (uma expirada, uma pendente) gravam sem erro de duplicidade.
  - `pagamento_tem_vaga(db(), 0)` === true; `pagamento_tem_vaga(db(), vagas_ocupadas(db()))` === false; `+1` === true.

- [ ] **Step 2: Run it to verify it fails**
  Run: `C:/xampp/php/php tests/pagamento_test.php`
  Expected: erro fatal (arquivo `includes/pagamento.php` / coluna `expira_em` não existe).

- [ ] **Step 3: Implement** o esquema, `inscricoes_expirar`, o fuso em `db()`, `travar_vagas`, `vagas_ocupadas`, `includes/pagamento.php` e troque todo `status <> 'cancelado'` listado em Files por `status IN ('pendente','pago')`. `STATUS_ROTULOS['expirado'] = 'Não paga (expirada)'`; o resumo do painel ganha a chave `expirado` e um cartão "Não pagas (expiradas)". `.adm-selo--expirado` em cinza (`#E4E7EE`, texto `var(--suave)`).

- [ ] **Step 4: Run it to verify it passes**
  Run: `C:/xampp/php/php tests/pagamento_test.php`
  Expected: `Tudo certo`.

- [ ] **Step 5: Inscrição e ações do painel** (sem teste PHP; coberto pelo cenário da Task 6):
  - `inscricao.php`: a busca de CPF sai de antes da trava e passa para depois de `travar_vagas`, usando `pagamento_cpf_ativo`. Pendente ativa → `Já existe uma inscrição com este CPF aguardando pagamento até 28/11 às 14h30. Use o link enviado para o seu e-mail.` (data/hora de `expira_em`, formato `d/m \à\s H\hi`); paga → a mensagem de hoje.
  - `admin/acao.php` `pago`: se a inscrição está `expirado`, pega `travar_vagas`; recusa com `Não há vaga para confirmar esta inscrição.` se `!pagamento_tem_vaga($pdo, (int) evento()['inscricoes_vagas'])`, ou `Este CPF já tem outra inscrição ativa.` se `pagamento_cpf_ativo($pdo, $cpf, $id)`; senão confirma como hoje. `pendente`: também `expira_em = NULL` (pendente feita à mão não expira).
  - `admin/inscrito.php`: "Voltar para pendente" não aparece para `expirado`.

- [ ] **Step 6: Run the whole suite** — todos os `tests/*_test.php` e `sh rodar-tudo.sh` no scratchpad.
  Expected: tudo verde (os cenários existentes não usam expiração).

- [ ] **Step 7: Commit**
  `git add -A includes database admin assets/css inscricao.php inscricao_recebida.php tests/pagamento_test.php && git commit -m "feat(pagamento): status expirado, inscrição ativa por CPF e fuso do banco"`

---

### Task 2: Cliente da API do Mercado Pago

**Files:**
- Create: `includes/mercadopago.php`, `config/mercadopago.exemplo.php`, `tests/mp_falso.php`, `tests/mercadopago_test.php`
- Modify: `.gitignore` (`config/mercadopago.php`)

**Interfaces:**
- Produces (`includes/mercadopago.php`):
  - `mp_config(?string $arquivo = null): ?array` → `null` se o arquivo não existe, tem erro (try/catch Throwable + log) ou falta `access_token`/`public_key`; padrão `api = 'https://api.mercadopago.com'`, `webhook_secret = ''`.
  - `mp_requisicao(array $cfg, string $metodo, string $caminho, ?array $corpo = null, ?string $idempotencia = null): array` → `['http' => int, 'dados' => ?array, 'erro' => ?string]`; `Authorization: Bearer`, `X-Idempotency-Key` quando dado, timeout de conexão 5 s e total 15 s; erro de rede → `http 0` e `erro` curto (≤255). Nunca lança.
  - `mp_criar_pix(array $cfg, array $insc, string $expira_iso): array` → resultado de `mp_requisicao` em `POST /v1/payments` com `transaction_amount` (float de `valor_total`), `description` `Inscrição <codigo> – <nome do evento>`, `payment_method_id: 'pix'`, `date_of_expiration` (`Y-m-d\TH:i:s.vP`), `external_reference` = código, `payer` (`email`, `first_name`, `last_name`, `identification: {type: 'CPF', number}`), idempotência `pix-<codigo>-<expira_iso>`.
  - `mp_criar_cartao(array $cfg, array $insc, array $form): array` → `POST /v1/payments` com `transaction_amount` do banco, `token`, `installments`, `payment_method_id`, `issuer_id`, `payer` (`email` e `identification` do formulário), `external_reference`, `description`; idempotência `cartao-<codigo>-<sha1(token)>`.
  - `mp_buscar_pagamento(array $cfg, string $id): array` → `GET /v1/payments/{id}`.
  - `mp_assinatura_valida(string $segredo, string $x_signature, string $x_request_id, string $data_id): bool` → separa `ts` e `v1` de `ts=…,v1=…`; manifesto `id:<data_id em minúsculas>;request-id:<x_request_id>;ts:<ts>;` (omite a parte `request-id:…;` se vazio); `hash_equals(hash_hmac('sha256', manifesto, segredo), v1)`; segredo vazio → `false`.
- Produces (`tests/mp_falso.php`, roteador do `php -S 127.0.0.1:<porta> tests/mp_falso.php`, estado em `sys_get_temp_dir()/mp_falso_<porta>.json`):
  - `POST /v1/payments`: sem `Bearer` → 401. Mesma `X-Idempotency-Key` → devolve o mesmo pagamento. `pix` → `status: 'pending'`, `point_of_interaction.transaction_data.qr_code` = `00020126FALSO<id>`, `qr_code_base64` = PNG 1×1 em base64. Cartão pelo `token`: `APRO` → `approved`, `OTHE` → `rejected` (`cc_rejected_other_reason`), `CONT` → `in_process`. Ecoa `transaction_amount`, `installments`, `payment_method_id`, `external_reference`, `date_of_expiration`. Guarda cada pedido (corpo e cabeçalhos) em `pedidos`.
  - `GET /v1/payments/{id}` → o guardado ou 404.
  - `POST /__mudar/{id}` com JSON parcial (`status`, `transaction_amount`, …) → altera o guardado (só para os testes).

- [ ] **Step 1: Write the failing test** — `tests/mercadopago_test.php` (sobe o falso com `proc_open` como `email_envio_test.php` faz com o SMTP, porta 25261):
  - `mp_config` de arquivo inexistente → null; com erro de sintaxe → null; sem `public_key` → null; completo → `api` padrão preenchido.
  - `mp_criar_pix` → `http` 201, `dados.status` `pending`, `qr_code` começa com `00020126FALSO`; o pedido guardado tem `transaction_amount` 92.7, `payment_method_id` `pix`, `external_reference` = código, `X-Idempotency-Key` presente.
  - chamar `mp_criar_pix` de novo com o mesmo prazo → mesmo `dados.id`.
  - `mp_criar_cartao` com `APRO` / `OTHE` / `CONT` → `approved` / `rejected` / `in_process`; `transaction_amount` do pedido = valor do `$insc`, mesmo que `$form['transaction_amount']` seja 1.00.
  - `mp_buscar_pagamento` de id existente → mesmo status; inexistente → `http` 404.
  - porta fechada → `http` 0, `erro` não vazio, ≤255, em menos de 20 s.
  - assinatura: com segredo `s3cr3t`, `data_id` `123`, request-id `req-1`, ts `1700000000`, `v1 = hash_hmac('sha256', 'id:123;request-id:req-1;ts:1700000000;', 's3cr3t')` → true; `v1` trocado → false; segredo vazio → false; `data_id` `ABC` assinado com `id:abc;…` → true.

- [ ] **Step 2: Run it to verify it fails**
  Run: `C:/xampp/php/php tests/mercadopago_test.php`
  Expected: erro fatal (`includes/mercadopago.php` não existe).

- [ ] **Step 3: Implement** `includes/mercadopago.php`, `tests/mp_falso.php`, o modelo de configuração (comentários dizendo onde achar cada credencial no painel de desenvolvedor do Mercado Pago) e a linha no `.gitignore`.

- [ ] **Step 4: Run it to verify it passes**
  Run: `C:/xampp/php/php tests/mercadopago_test.php`
  Expected: `Tudo certo`.

- [ ] **Step 5: Commit**
  `git add includes/mercadopago.php config/mercadopago.exemplo.php .gitignore tests/mp_falso.php tests/mercadopago_test.php && git commit -m "feat(pagamento): cliente da API do Mercado Pago e servidor falso para testes"`

---

### Task 3: Processar pagamento, aviso e consulta de status

**Files:**
- Modify: `includes/pagamento.php`
- Create: `pagamento_aviso.php`, `pagamento_status.php`
- Test: `tests/pagamento_test.php` (acrescentar), usando `tests/mp_falso.php`

**Interfaces:**
- Consumes: Task 1 (`pagamento_cpf_ativo`, `pagamento_tem_vaga`, `travar_vagas`, `liberar_trava_vagas`), Task 2 (`mp_buscar_pagamento`, `mp_assinatura_valida`, `mp_config`), `email_pago_enviar` e `email_config` de `includes/email_pagamento.php`.
- Produces (`includes/pagamento.php`):
  - `pagamento_registrar(PDO $pdo, int $inscricao_id, array $mp): void` → insere/atualiza `pagamentos` por `mp_id` (status, detalhe, valor, método: `pix` ou `payment_method_id`, parcelas, e para Pix copia e cola, QR base64 e `date_of_expiration`).
  - `pagamento_processar(PDO $pdo, array $mp, int $vagas, string $site, ?array $cfg_email): string` → um de `pago`, `pago_atrasado`, `sem_vaga`, `duplicidade`, `repetido`, `estornado`, `valor_divergente`, `desconhecido`, `atualizado`. Regras:
    - acha a inscrição por `external_reference` = `codigo` (`desconhecido` + log se não acha); chama `pagamento_registrar`.
    - `approved`: `round(transaction_amount, 2)` ≠ `valor_total` → `valor_divergente` + log. Senão, com `travar_vagas` e a linha travada (`SELECT … FOR UPDATE` dentro de transação):
      - `pagamento_mp_id` = este id → `repetido`;
      - `pendente` → `pago`, `pago_em = NOW()`, `pagamento_mp_id` = id → `pago`;
      - `expirado` com `pagamento_tem_vaga` e sem outra ativa do CPF → igual ao anterior → `pago_atrasado`;
      - `expirado` sem vaga ou com outra ativa → `status = 'pago'`, `pago_em = NOW()`, `pagamento_mp_id` = id, alerta `paga sem vaga — devolver o dinheiro` → `sem_vaga`;
      - `pago` com outro id, ou `cancelado` → só alerta `pagamento em duplicidade — devolver o dinheiro (Mercado Pago <id>)` → `duplicidade`.
      - Depois do commit, em `pago`/`pago_atrasado`: `email_pago_enviar` se `email_pago_em` está vazio.
    - `refunded`/`charged_back` com `pagamento_mp_id` = id → alerta `estornado no Mercado Pago` → `estornado` (status não muda).
    - qualquer outro → `atualizado`.
    - Alerta acrescenta (separado por ` · `) se já houver outro; não repete o mesmo texto.
  - `pagamento_consultar(PDO $pdo, array $cfg_mp, array $insc, int $vagas, string $site, ?array $cfg_email): void` → se a inscrição está `pendente` e tem pagamento Pix `pending` com `consultado_em` nulo ou de mais de 10 s atrás, atualiza `consultado_em`, busca na API e chama `pagamento_processar`.
- Produces (endpoints):
  - `pagamento_aviso.php`: aceita POST; `data_id` de `$_GET['data_id']` (o PHP troca `data.id` por `data_id`) ou do JSON `data.id`; tipo `payment` (`$_GET['type']` ou JSON `type`), senão 200 e sai. Assinatura inválida → 401 + log. Válida → `mp_buscar_pagamento`; falha da API → 500 (o Mercado Pago tenta de novo); sucesso → `pagamento_processar` e 200. Sem `config/mercadopago.php` → 503.
  - `pagamento_status.php?c=CODIGO` → JSON `{"status": "pendente"|"pago"|"expirado"|"cancelado"}`; código inválido/inexistente → 404 `{"status": null}`; chama `pagamento_consultar` antes de responder; cabeçalho `Cache-Control: no-store`.

- [ ] **Step 1: Write the failing tests** (acrescentar a `tests/pagamento_test.php`; o pagamento falso é criado com `mp_criar_pix`/`mp_criar_cartao` no servidor falso e mudado com `/__mudar`; `$cfg_email = null`):
  - `pendente + approved → pago`: retorno `pago`, `status` `pago`, `pago_em` preenchido, `pagamento_mp_id` = id, linha em `pagamentos`.
  - `aviso repetido não reenvia`: segunda chamada → `repetido`, `pago_em` igual ao da primeira, `email_pago_erro` continua o da primeira (só uma tentativa de envio).
  - `valor divergente não confirma`: `/__mudar` `transaction_amount` 1.00 → `valor_divergente`, continua `pendente`.
  - `expirada com vaga → pago_atrasado`; `expirada sem vaga` (`$vagas = vagas_ocupadas(db())`) → `sem_vaga`, status `pago`, alerta contém `paga sem vaga — devolver o dinheiro`.
  - `expirada com o CPF já ativo noutra inscrição` → `sem_vaga`.
  - `segundo pagamento numa paga` → `duplicidade`, alerta contém `pagamento em duplicidade — devolver o dinheiro`, `pagamento_mp_id` não muda.
  - `cancelada + approved` → `duplicidade`, continua `cancelado`.
  - `refunded do pagamento que quitou` → `estornado`, status continua `pago`, alerta contém `estornado no Mercado Pago`.
  - `referência desconhecida` → `desconhecido`.
  - `pagamento_consultar` com Pix aprovado no falso → inscrição `pago`; chamada logo em seguida não faz novo pedido ao falso (conta `pedidos` GET).
  - `aviso sem assinatura válida` (o teste sobe o site com `proc_open` de `php -S 127.0.0.1:25262 -t <raiz>` e chama o endpoint com `file_get_contents` + `ignore_errors`) → 401 e status inalterado; com assinatura válida feita com o `webhook_secret` do config de teste → 200 e `pago`. O teste escreve um `config/mercadopago.php` temporário apontando `api` para o falso e restaura o original no `finally`.
  - `pagamento_status.php` de código inexistente → 404; de inscrição paga → `{"status":"pago"}`.

- [ ] **Step 2: Run them to verify they fail**
  Run: `C:/xampp/php/php tests/pagamento_test.php`
  Expected: FALHOU/erro fatal (`pagamento_processar` não existe).

- [ ] **Step 3: Implement** as funções e os dois endpoints. Os endpoints ficam na raiz (públicos; o `.htaccess` não bloqueia `.php` da raiz).

- [ ] **Step 4: Run them to verify they pass**
  Run: `C:/xampp/php/php tests/pagamento_test.php`
  Expected: `Tudo certo`.

- [ ] **Step 5: Commit**
  `git add includes/pagamento.php pagamento_aviso.php pagamento_status.php tests/pagamento_test.php && git commit -m "feat(pagamento): processar pagamentos do Mercado Pago (aviso e consulta)"`

---

### Task 4: Pix na inscrição e e-mail de instruções

**Files:**
- Modify: `inscricao.php` (depois do commit), `includes/pagamento.php`, `includes/email_pagamento.php`
- Create: `pagamento_pix.php`
- Test: `tests/pagamento_test.php`, `tests/email_pagamento_test.php`, `tests/email_envio_test.php`

**Interfaces:**
- Consumes: Task 2 (`mp_criar_pix`), Task 3 (`pagamento_registrar`).
- Produces:
  - `pagamento_gerar_pix(PDO $pdo, array $cfg_mp, int $inscricao_id): ?string` → `null` se criou (ou já existe Pix `pending` não vencido), senão o motivo curto. Recusa se a inscrição não está `pendente` ou `expira_em` já passou. `date_of_expiration` = `expira_em` com o fuso do PHP.
  - `pagamento_pix_atual(PDO $pdo, int $inscricao_id): ?array` → `['copia_cola','qr_base64','expira_em']` do Pix `pending` mais recente, ou null.
  - `email_instrucoes_mensagem(array $insc, array $ev, string $site, ?array $pix): array` → `['assunto','texto','html','imagens_dados']`. Assunto exato: `Pague sua inscrição até 28/11 às 14h30 – <nome do evento>` (data/hora de `expira_em`). Mesmo cabeçalho do e-mail de confirmação (logo, `Olá, Atleta <nome>!`); total; prazo; com Pix: QR (`<img src="cid:qr-pix">`) e o copia e cola em caixa monoespaçada; botão ouro `Clique aqui para pagar (Pix ou cartão)` → `<site>/inscricao_recebida.php?c=<codigo>&pagar=1`; frase `Depois do prazo, a inscrição expira e a vaga é liberada.` Sem Pix: só o botão. `imagens_dados` = `['qr-pix' => ['dados' => <bytes do PNG>, 'tipo' => 'image/png']]` quando há Pix.
  - `email_enviar` passa a anexar também `$msg['imagens_dados']` com `addStringEmbeddedImage`.
  - `email_instrucoes_enviar(PDO $pdo, int $id, string $site, ?array $cfg): ?string` → grava `email_instr_em` ou `email_instr_erro`.
  - `inscricao.php`: se `mp_config()` não é null e o total > 0, o INSERT grava `expira_em = NOW() + INTERVAL 6 HOUR` (use `PAGAMENTO_PRAZO_HORAS`); depois do commit e de soltar a trava: `pagamento_gerar_pix`, `email_instrucoes_enviar` (falhas só logam) e redireciona para `inscricao_recebida.php?c=<codigo>&pagar=1`. Sem configuração: igual a hoje.
  - `pagamento_pix.php` (POST `c`) → JSON `{"ok": true, "pix": {...}}` ou `{"ok": false, "erro": "..."}`; usa `pagamento_gerar_pix` + `pagamento_pix_atual`.

- [ ] **Step 1: Write the failing tests**
  - `tests/email_pagamento_test.php`: assunto com `Pague sua inscrição até` e `às`; texto contém o copia e cola, `R$ 92,70`, o link `…/inscricao_recebida.php?c=<codigo>&pagar=1`; HTML contém `cid:qr-pix` e `Clique aqui para pagar (Pix ou cartão)`; sem Pix: nem `cid:qr-pix` nem copia e cola, botão presente; nome com `<b>&` escapado no HTML.
  - `tests/email_envio_test.php`: mensagem com `imagens_dados` → recebida contém `Content-ID: <qr-pix>`.
  - `tests/pagamento_test.php`: `pagamento_gerar_pix` numa pendente com prazo → null e `pagamento_pix_atual` com `copia_cola` começando por `00020126FALSO`; segunda chamada não cria outro (um só pedido POST no falso); numa expirada → motivo não nulo; `Mercado Pago fora do ar não perde a inscrição`: `api` numa porta fechada → motivo não nulo, inscrição continua `pendente`, nenhuma linha nova em `pagamentos`.

- [ ] **Step 2: Run to verify they fail** — os três arquivos de teste. Expected: FALHOU/erro fatal.

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run to verify they pass** — os três. Expected: `Tudo certo` em cada um.

- [ ] **Step 5: Commit**
  `git add inscricao.php includes pagamento_pix.php tests && git commit -m "feat(pagamento): Pix gerado na inscrição e e-mail de instruções de pagamento"`

---

### Task 5: Modal de pagamento e cartão

**Files:**
- Modify: `inscricao_recebida.php`, `assets/css/style.css`
- Create: `assets/js/pagamento.js`, `pagamento_cartao.php`
- Test: `tests/pagamento_test.php` (endpoint do cartão); cenário `cen-pagamento.js` no scratchpad

**Interfaces:**
- Consumes: Task 3 (`pagamento_processar`, `pagamento_status.php`), Task 4 (`pagamento_pix_atual`, `pagamento_pix.php`), Task 2 (`mp_criar_cartao`, `mp_config`).
- Produces:
  - `pagamento_cartao.php` (POST JSON `{codigo, token, installments, payment_method_id, issuer_id, payer: {email, identification: {type, number}}}`) → JSON `{"status": "approved"|"rejected"|"in_process"|"erro", "mensagem": "..."}`. Recusa (`erro`) se a inscrição não está `pendente`, se `expira_em` ≤ `NOW()` (`O prazo de pagamento terminou.`) ou `installments` fora de 1..2. Aprovado → `pagamento_processar` (com `evento()['inscricoes_vagas']`). Mensagens: recusado `Pagamento recusado. Confira os dados ou tente outro cartão.`; em análise `Pagamento em análise pelo Mercado Pago. Avisaremos por e-mail assim que for aprovado.`
  - `inscricao_recebida.php`, quando pendente com `expira_em` e `mp_config()` presente: aviso com o prazo e botão `Pagar agora`; o modal (marcação do esboço aprovado: cabeçalho azul com `assets/img/logo.png`, `Pagamento da inscrição`, código · nome, `Total a pagar`, abas `Pix` | `Cartão de crédito`, Pix primeiro) fica no HTML com `data-codigo`, `data-expira` (ISO com fuso), `data-public-key`, `data-total`; abre sozinho com `pagar=1`. Expirada: `O prazo de pagamento terminou e a vaga foi liberada.` + botão `Fazer nova inscrição` (só se `inscricoes_fechamento()` é null). `$scripts_extra = ['assets/js/pagamento.js']`.
  - `assets/js/pagamento.js`: abrir/fechar (×, Esc, clique no véu; foco preso no modal; `role="dialog"`, `aria-modal`); contagem `faltam Xh Ymin` a cada 30 s; `Copiar` com `navigator.clipboard` e retorno `Copiado!`; sem Pix na página → botão `Tentar de novo` chama `pagamento_pix.php`; consulta `pagamento_status.php` a cada 5 s com o modal aberto → `pago` mostra a tela "Pagamento confirmado!" (e-mail mascarado vindo do HTML), `expirado` recarrega a página; aba Cartão carrega `https://sdk.mercadopago.com/js/v2` só na primeira vez e cria o `cardPayment` Brick com `initialization.amount` = total e `customization.paymentMethods.maxInstallments = 2`, `onSubmit` → `pagamento_cartao.php`.
  - CSS: classes `.pag-modal*`, `.pag-aba*`, `.pag-qr`, `.pag-copia`, seguindo o esboço; tela pequena (≤ 480 px) ocupa a tela toda.

- [ ] **Step 1: Write the failing tests**
  - `tests/pagamento_test.php` (endpoint pelo `php -S` da Task 3, `config/mercadopago.php` temporário): token `APRO` → `approved` e inscrição `pago`; `OTHE` → `rejected`, continua `pendente`; `installments` 3 → `erro`; `cartão depois do prazo é recusado` (`expira_em` no passado, mas ainda `pendente` — chame antes de `db()` expirar, ajustando `expira_em = NOW() - INTERVAL 1 SECOND` logo antes) → `erro`, nenhum pedido novo no falso; inscrição paga → `erro`.
  - `cen-pagamento.js` (CDP; guarda/restaura `config/mercadopago.php` apontando para o falso na porta 25263; inscrição criada pelo próprio formulário com CPF de teste e apagada no fim): `ok_modal_abre` (`pagar=1` abre o diálogo), `ok_pix_primeiro`, `ok_qr` (img do QR visível), `ok_copia_cola` (campo com `00020126FALSO`), `ok_contagem` (texto `faltam`), `ok_fecha_esc`, `ok_pagar_agora_reabre`, `ok_confirma_sozinho` (`/__mudar` para `approved` → em ≤ 15 s aparece `Pagamento confirmado!`), `ok_expirada` (outra inscrição com `expira_em` no passado mostra `O prazo de pagamento terminou`), `ok_mobile` (viewport 375 px sem rolagem horizontal).

- [ ] **Step 2: Run to verify they fail**
  Run: `C:/xampp/php/php tests/pagamento_test.php` e `node cdp.js pagamento` (scratchpad)
  Expected: FALHOU (endpoint e modal não existem).

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run to verify they pass** — os mesmos comandos. Expected: `Tudo certo` e todos `ok_*` true. Tire um print do modal (Pix e confirmado, desktop e 375 px) e compare com o esboço.

- [ ] **Step 5: Commit**
  `git add inscricao_recebida.php pagamento_cartao.php assets tests && git commit -m "feat(pagamento): modal de pagamento com Pix e cartão em até 2x"`

---

### Task 6: Painel, dashboard e guia de configuração

**Files:**
- Modify: `admin/painel.php`, `admin/inscrito.php`, `admin/exportar.php`, `assets/css/admin.css`, `README.md`
- Test: cenário `cen-pagamento-painel.js` no scratchpad; incluir `pagamento` e `pagamento-painel` em `rodar-tudo.sh`

**Interfaces:**
- Consumes: colunas da Task 1, `pagamentos` da Task 3.
- Produces:
  - Lista do painel: selo `Não paga (expirada)`; filtro com a opção (vem de `STATUS_ROTULOS`); selo `adm-selo--alerta` com o texto de `pagamento_alerta` quando houver.
  - `admin/inscrito.php`: linhas `Pagamento` (`Pix` ou `Cartão <bandeira> em Nx`, com o id do Mercado Pago), `Prazo para pagar` (`expira_em`, só pendente/expirada), `E-mail de instruções` (enviado em … / erro), e o alerta em destaque (`adm-aviso adm-aviso--erro`).
  - `admin/exportar.php`: coluna `Situação` usa o rótulo novo; colunas novas `Forma de pagamento` e `Alerta`.
  - Dashboard: nada a mudar no código (já conta `pago`/`pendente`, e a expiração roda em `db()`); o cenário confere.
  - `README.md`, seção "Pagamento (Mercado Pago)": criar `config/mercadopago.php` na Hostinger a partir do modelo; no painel do Mercado Pago, Webhooks → URL `https://lionsclubecornelio.com/pagamento_aviso.php`, evento "Pagamentos", copiar a assinatura secreta para `webhook_secret`; trocar credenciais de teste pelas de produção; quem paga os juros do parcelamento é configurado na conta do Mercado Pago.

- [ ] **Step 1: Write the failing scenario** — `cen-pagamento-painel.js` (admin de teste `teste-pag` com acesso total, apagado no fim; inscrições de teste inseridas por SQL): `ok_selo_expirada`, `ok_filtro_expirada` (filtro mostra só a expirada), `ok_resumo_expiradas`, `ok_alerta_lista` e `ok_alerta_detalhe` (`paga sem vaga — devolver o dinheiro`), `ok_forma_pagamento` (`Cartão visa em 2x`), `ok_confirmar_expirada_sem_vaga` (com vagas = ocupadas, "Confirmar pagamento" numa expirada mostra `Não há vaga para confirmar esta inscrição.`), `ok_sem_voltar_pendente` (expirada não tem o botão), `ok_cpf_pendente_bloqueia` (formulário público com o CPF de uma pendente ativa mostra `aguardando pagamento até`), `ok_cpf_expirado_libera` (CPF de expirada consegue se inscrever), `ok_dashboard_sem_expiradas`, `ok_exportar` (CSV tem `Não paga (expirada)`).

- [ ] **Step 2: Run to verify it fails**
  Run: `node cdp.js pagamento-painel`
  Expected: `ok_*` false.

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run to verify it passes**, e depois a suíte inteira: todos os `tests/*_test.php` e `sh rodar-tudo.sh`.
  Expected: tudo verde.

- [ ] **Step 5: Commit**
  `git add admin assets/css README.md && git commit -m "feat(pagamento): painel mostra expiradas, forma de pagamento e alertas; guia do Mercado Pago"`

---

### Depois do plano (com o usuário)

Não faz parte da execução automática: publicar só com "pode publicar"; o usuário cria `config/mercadopago.php` na Hostinger com as credenciais de teste e cadastra o webhook; teste ponta a ponta no site com Pix e cartões de teste do Mercado Pago (`APRO`, `OTHE`); depois trocar para as credenciais de produção.
