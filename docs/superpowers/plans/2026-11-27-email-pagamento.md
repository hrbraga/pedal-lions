# E-mail de pagamento confirmado — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ao confirmar o pagamento, enviar ao atleta o e-mail "Pagamento confirmado" pela caixa do domínio (SMTP Hostinger), registrar o envio ou o motivo da falha e permitir reenviar pelo painel.

**Architecture:** `includes/email_pagamento.php` monta a mensagem (função pura) e envia (PHPMailer copiado em `includes/vendor/PHPMailer/`), lendo `config/email.php` (fora do git). `admin/acao.php` chama o envio ao confirmar e numa ação nova de reenvio; duas colunas em `inscricoes` guardam quando saiu e o último erro.

**Tech Stack:** PHP 8.2/8.3, PDO MySQL, PHPMailer 7.2.0 (sem Composer), testes em `tests/*_test.php` e cenário CDP no scratchpad.

**Spec:** docs/superpowers/specs/2026-11-27-email-pagamento-design.md

## Global Constraints

- Remetente: a caixa configurada em `config/email.php` (`usuario`), nome `Lions Clube de Cornélio Procópio` por padrão.
- `config/email.php` no `.gitignore`; modelo em `config/email.exemplo.php` (host smtp.hostinger.com, porta 465, seguranca ssl). Senha nunca no git.
- Sem `config/email.php` → não envia; erro registrado: `e-mail não configurado`.
- Assunto exato: `Pagamento confirmado – 2º Pedal Solidário` (usa `$ev['nome']`: `Pagamento confirmado – {nome}`).
- Falha no envio nunca desfaz o pagamento.
- Reconfirmar (pendente → pago de novo) não reenvia se `email_pago_em` já estiver preenchido.
- Nenhum código só de teste no site; os testes apontam a configuração para um SMTP falso.
- Testes nunca usam a conta admin real.

## Review Focus

1. Nome do atleta com `<`, `&` ou aspas → HTML do e-mail escapado; o texto puro mantém o nome como está. (Task 1, teste de escape)
2. E-mail da inscrição inválido/vazio → PHPMailer recusa; erro registrado, pagamento continua pago, sem exceção vazando. (Task 2, teste `endereco invalido`)
3. Servidor SMTP fora do ar ou lento → erro em segundos (Timeout 15 s), mensagem de erro curta (≤255 chars) gravada. (Task 2, teste porta fechada; Task 3, cenário)
4. Inscrição sem camiseta / modelo removido → linha "Camiseta" omitida ou com o slug; nada quebra. (Task 1)
5. Clique duplo em Reenviar / ação de e-mail numa inscrição não paga → só pagas recebem; a ação recusa as outras. (Task 3, cenário `ok_reenvio_recusado_pendente`)

---

### Task 1: Montagem da mensagem

**Files:**
- Create: `includes/email_pagamento.php`
- Test: `tests/email_pagamento_test.php`

**Interfaces:**
- Consumes: `e()`, `moeda()`, `camiseta_modelos()` de `includes/funcoes.php` (requerer com `require_once`).
- Produces:
  - `email_pago_mensagem(array $insc, array $ev, string $site): array` → `['assunto' => string, 'texto' => string, 'html' => string]`. `$insc` = linha de `inscricoes`; `$ev` = `evento()`; `$site` = endereço base sem barra final (ex.: `https://lionsclubecornelio.com`).
  - `email_site_base(array $server): string` → `https://` (ou `http://` sem HTTPS) + `HTTP_HOST` + pasta do site (a pasta do `SCRIPT_NAME` sem `/admin` no fim), sem barra final.

- [ ] **Step 1: Write the failing test** — `tests/email_pagamento_test.php`, no formato dos outros testes (`confere`), com `$ev` montado à mão (nome, data_extenso, local_largada, categorias light/hard, camiseta.modelos curta/longa, contato.whatsapp, contato.grupo_whatsapp, programacao com `Retirada de kits 6:00`, `Largada Light 7:50`, `Largada Hard 7:40`):
  - assunto === `Pagamento confirmado – 2º Pedal Solidário`
  - texto contém: `Olá, Maria`, o código, `Light`, `Manga longa, tamanho G`, `R$ 216,00`, `Domingo, 14 de março de 2027`, `Bosque Municipal`, `7:50 – Largada Light` e `7:40 – Largada Hard` (todas as largadas da programação), `https://site.test/regulamento.php`, o link do grupo, `(43) 99675-9480`, `Lions Clube de Cornélio Procópio`.
  - texto não contém `Retirada de kits` (só largadas).
  - sem camiseta (`camiseta_tamanho` null): texto não contém `Camiseta`.
  - modelo inexistente `xyz`: texto contém `xyz, tamanho M`.
  - grupo do WhatsApp vazio: nem texto nem HTML contêm `chat.whatsapp`.
  - nome `Ana <b>&"Zé"` → HTML contém `Ana &lt;b&gt;&amp;&quot;Zé&quot;` e não contém `<b>&`; texto contém `Ana <b>&"Zé"`.
  - `email_site_base(['HTTPS'=>'on','HTTP_HOST'=>'lionsclubecornelio.com','SCRIPT_NAME'=>'/admin/acao.php'])` === `https://lionsclubecornelio.com`
  - `email_site_base(['HTTP_HOST'=>'[::1]','SCRIPT_NAME'=>'/pedal-solidario/admin/acao.php'])` === `http://[::1]/pedal-solidario`

- [ ] **Step 2: Run it to verify it fails**
  Run: `C:/xampp/php/php tests/email_pagamento_test.php`
  Expected: fatal error (arquivo/função não existe).

- [ ] **Step 3: Implement both functions in `includes/email_pagamento.php`.** Primeiro nome = primeira palavra de `nome`. Categoria pelo slug em `$ev['categorias']` (cai no slug). Modelo pelo slug em `camiseta_modelos($ev['camiseta'])`. HTML simples em tabela de 600px, estilos inline, cores do site; cada valor com `e()`.

- [ ] **Step 4: Run it to verify it passes**
  Run: `C:/xampp/php/php tests/email_pagamento_test.php`
  Expected: `Tudo certo`

- [ ] **Step 5: Commit** — `feat(email): mensagem de pagamento confirmado`

### Task 2: Envio por SMTP e registro no banco

**Files:**
- Create: `includes/vendor/PHPMailer/{PHPMailer.php,SMTP.php,Exception.php,LICENSE}` (v7.2.0, de github.com/PHPMailer/PHPMailer `src/`)
- Create: `config/email.exemplo.php`, `tests/smtp_falso.php`, `tests/email_envio_test.php`
- Modify: `includes/email_pagamento.php`, `includes/db.php` (esquema_atualizar), `database/schema.sql`, `.gitignore`, `README.md` (seção Hostinger)

**Interfaces:**
- Consumes: `email_pago_mensagem()`, `email_site_base()` (Task 1).
- Produces:
  - `email_config(?string $arquivo = null): ?array` — lê `config/email.php` (ou o caminho dado); `null` se não existir ou faltar host/usuario. Padrões: porta 465, seguranca `ssl`, nome `Lions Clube de Cornélio Procópio`.
  - `email_enviar(?array $cfg, string $para, string $nome_para, array $msg): ?string` — `null` = enviado; senão o motivo (≤255 chars). `$cfg` null → `e-mail não configurado`. Nunca lança exceção.
  - `email_pago_enviar(PDO $pdo, int $id, string $site, ?array $cfg): ?string` — carrega a inscrição; se não estiver `pago`, retorna `inscrição não está paga` sem enviar; envia; grava `email_pago_em = NOW(), email_pago_erro = NULL` ou `email_pago_erro = motivo`.
  - Colunas `inscricoes.email_pago_em DATETIME NULL`, `inscricoes.email_pago_erro VARCHAR(255) NULL`.
  - `tests/smtp_falso.php <porta> <arquivo>`: servidor SMTP mínimo (EHLO com AUTH LOGIN PLAIN, MAIL, RCPT, DATA, QUIT), sem TLS; anexa cada mensagem recebida ao arquivo com uma linha separadora `=====`; atende até ser encerrado.

- [ ] **Step 1: Write the failing test** — `tests/email_envio_test.php`: sobe `smtp_falso.php` com `proc_open` numa porta livre (ex.: 25251), espera aceitar conexão, e confere:
  - `email_config(__DIR__.'/nao-existe.php')` === null
  - `email_enviar(null, ...)` === `'e-mail não configurado'`
  - envio com `['host'=>'127.0.0.1','porta'=>25251,'seguranca'=>'','usuario'=>'contato@exemplo.test','senha'=>'x','nome'=>'Lions Clube de Cornélio Procópio']` retorna null; o arquivo contém `To: Maria`, `maria@t.test`, `From: Lions Clube`, `contato@exemplo.test`, o assunto (codificado em UTF-8: decodificar com `iconv_mime_decode` a linha Subject e comparar), `multipart/alternative`.
  - `endereco invalido`: `email_enviar($cfg, 'nao-e-email', ...)` retorna string não vazia.
  - porta fechada (25259): retorna string não vazia, com `strlen <= 255`, em menos de 20 s.
  - encerra o servidor no fim (`proc_terminate`).

- [ ] **Step 2: Run it to verify it fails**
  Run: `C:/xampp/php/php tests/email_envio_test.php`
  Expected: fatal error (`email_config` não existe).

- [ ] **Step 3: Baixar o PHPMailer 7.2.0** (os 3 arquivos de `src/` e LICENSE) para `includes/vendor/PHPMailer/`; escrever `tests/smtp_falso.php`.

- [ ] **Step 4: Implement `email_config`, `email_enviar`, `email_pago_enviar`.** PHPMailer: `CharSet = 'UTF-8'`, `isSMTP`, `SMTPAuth = true` com usuario/senha, `SMTPSecure` = `ssl`→`PHPMailer::ENCRYPTION_SMTPS`, `tls`→`ENCRYPTION_STARTTLS`, `''`→ sem criptografia e `SMTPAutoTLS = false`; `Timeout = 15`; `setFrom(usuario, nome)`; `addReplyTo(usuario)`; `isHTML`, `Body = html`, `AltBody = texto`. Capturar `Exception` e `\Throwable`; motivo = `ErrorInfo` ou mensagem, cortado em 255.

- [ ] **Step 5: Run it to verify it passes**
  Run: `C:/xampp/php/php tests/email_envio_test.php`
  Expected: `Tudo certo`

- [ ] **Step 6: Banco e configuração** — `esquema_atualizar`: se faltar `email_pago_em`, `ALTER TABLE inscricoes ADD COLUMN email_pago_em DATETIME NULL AFTER pago_em, ADD COLUMN email_pago_erro VARCHAR(255) NULL AFTER email_pago_em`; mesmas colunas em `schema.sql`. `config/email.exemplo.php` com comentários do jeito do `banco.exemplo.php` (onde criar a caixa no hPanel, host/porta). `.gitignore`: `config/email.php`. README: passo "Criar config/email.php no Gerenciador de Arquivos".
  Verify: abrir `http://localhost:8123/` uma vez e `mysql -e "SHOW COLUMNS FROM inscricoes LIKE 'email_pago%'"` mostra as 2 colunas.

- [ ] **Step 7: Commit** — `feat(email): envio pela caixa do domínio (SMTP) e registro na inscrição`

### Task 3: Painel — confirmar envia, reenviar, avisos

**Files:**
- Modify: `admin/acao.php`, `admin/inscrito.php`, `admin/painel.php`, `assets/css/admin.css` (se precisar de estilo)
- Test: cenário CDP `cen-email.js` no scratchpad (+ entrada no `rodar-tudo.sh`)

**Interfaces:**
- Consumes: `email_config()`, `email_pago_enviar()`, `email_site_base()` (Tasks 1–2).
- Produces: ação `email_pago` em `acao.php`.

- [ ] **Step 1: Write the failing scenario** `cen-email.js`: guarda e restaura `config/email.php` (se existir); escreve um apontando para `127.0.0.1:25252` sem criptografia; sobe `tests/smtp_falso.php 25252 <arquivo>`; cria admin `teste-email` (acesso total) e uma inscrição `EML…` pendente com e-mail `email-cdp1@t.test`. Confere:
  - `ok_confirma_envia`: clicar "Confirmar pagamento" na página da inscrição → aviso contém `E-mail enviado para email-cdp1@t.test`; arquivo tem 1 mensagem; `email_pago_em` preenchido; página mostra `E-mail de confirmação: enviado em`.
  - `ok_reconfirmar_nao_reenvia`: voltar para pendente, confirmar de novo → arquivo continua com 1 mensagem.
  - `ok_reenviar`: botão `Reenviar e-mail de confirmação` → 2 mensagens.
  - `ok_falha_registra`: config apontando para porta fechada 25259, nova inscrição pendente, confirmar → status `pago`; aviso contém `O e-mail não foi enviado`; página mostra `não enviado`; lista (painel.php) mostra `e-mail não enviado` na linha dela.
  - `ok_sem_config`: sem `config/email.php`, reenviar → aviso contém `e-mail não configurado`.
  - `ok_reenvio_recusado_pendente`: POST `acao=email_pago` para inscrição pendente → aviso de erro, nada enviado.
  - finally: apaga inscrições `email-cdp%`, admin de teste, encerra o servidor, restaura a config.

- [ ] **Step 2: Run it to verify it fails**
  Run: `node cdp.js email`
  Expected: `ok_confirma_envia: false` (aviso sem "E-mail enviado").

- [ ] **Step 3: Implement**
  - `acao.php` caso `pago`: depois do UPDATE, se `email_pago_em` vazio (incluir no SELECT inicial), `email_pago_enviar($pdo, $id, email_site_base($_SERVER), email_config())`; aviso `Pagamento confirmado: {nome}. E-mail enviado para {email}.` ou `Pagamento confirmado: {nome}. O e-mail não foi enviado ({motivo}). Use "Reenviar e-mail de confirmação" na inscrição.` (este com `flash('erro', …)`? não: `flash('ok', …)` para a primeira parte não sumir — usar `ok` no sucesso e `erro` na falha).
  - Caso novo `email_pago`: só se `status = 'pago'`; senão `flash('erro', 'Só inscrições pagas recebem o e-mail de confirmação.')`. Sucesso: `E-mail de confirmação enviado para {email}.`; falha: `O e-mail não foi enviado ({motivo}).`
  - `inscrito.php` (pagas): na lista de dados, `E-mail de confirmação` = `enviado em dd/mm/aaaa hh:mm` ou `não enviado — {erro}` (ou `não enviado` sem erro); botão `Enviar e-mail de confirmação` (nunca enviado) / `Reenviar e-mail de confirmação` com confirmação `Enviar o e-mail de confirmação para {email}?`.
  - `painel.php`: incluir `email_pago_em` no SELECT; nas pagas sem envio, selo pequeno `e-mail não enviado` ao lado da situação.

- [ ] **Step 4: Run it to verify it passes**
  Run: `node cdp.js email`, depois `bash rodar-tudo.sh` (com `$?` conferido)
  Expected: todos os `ok_*` true; suíte inteira verde.

- [ ] **Step 5: Commit** — `feat(email): painel envia ao confirmar pagamento, mostra o envio e permite reenviar`
