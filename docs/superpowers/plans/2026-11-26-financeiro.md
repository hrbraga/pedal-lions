# Financeiro – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Página Financeiro com lançamentos manuais, categorias editáveis, valores automáticos das inscrições e balancete por período.

**Architecture:** Regras e contas em `includes/financeiro.php`, em **centavos (int)**, sobre arrays (testáveis sem banco), mais
funções finas que leem o banco. `admin/financeiro.php` é a tela (padrão de `admin/cupons.php`: POST com `acao`, CSRF,
flash + redirect). A chave de acesso `financeiro` entra em `ACESSO_PAGINAS`.

**Tech Stack:** PHP 8.2, PDO MySQL. Testes: `tests/financeiro_test.php` (`confere()`), cenário CDP `cen-financeiro.js`,
runner `rodar-tudo.sh`.

**Spec:** `docs/superpowers/specs/2026-11-26-financeiro-design.md`

## Global Constraints
- Tabelas: `fin_categorias(id, tipo ENUM('entrada','saida'), nome VARCHAR(60), ativa TINYINT(1) DEFAULT 1, criado_em, UNIQUE(tipo,nome))`;
  `fin_lancamentos(id, tipo ENUM('entrada','saida'), categoria_id INT UNSIGNED, data DATE, descricao VARCHAR(200), valor DECIMAL(10,2),
  criado_por VARCHAR(50), criado_em, alterado_por VARCHAR(50) NULL, alterado_em DATETIME NULL, INDEX(data))`.
- Categorias iniciais (só se a tabela estiver vazia): Entrada — Patrocínios, Doações, Outras entradas. Saída — Troféus e medalhas,
  Camisetas (fornecedor), Kits e brindes, Alimentação e hidratação, Estrutura e som, Divulgação, Seguro atleta, Tarifas (Asaas/banco),
  Reembolsos, Outras saídas.
- Rótulos automáticos: entradas `Inscrições`, `Camisetas`, `Inscrições canceladas (sem reembolso)`, `Taxa de administração`;
  saída `Repasse da taxa de administração`.
- Automáticos (por `DATE(pago_em)`): status `pago` → Inscrições = inscrição − desconto, Camisetas = camiseta; status `cancelado` com
  `pago_em` → Canceladas = inscrição − desconto + camiseta; Taxa = `valor_taxa` de toda linha com `pago_em` (pago ou cancelado), e o
  mesmo valor em Repasse. Status `pendente` nunca entra.
- Valor do lançamento: maior que zero, até 9.999.999,99. Descrição obrigatória, até 200. Data válida. Categoria ativa e do mesmo tipo.
- Menu: Inscritos, Cupons, Dados do Evento, **Financeiro**, Administradores, Exportar CSV.
- Nada vai para a `main` sem o ok do usuário.

## Review Focus
1. Somas com centavos (ex.: 0,10 + 0,20) → exatas; totais do balancete batem com a soma das linhas.
2. Filtro com data inicial → "Saldo anterior" = tudo antes; saldo acumulado parte dele; saldo final igual com ou sem filtro (até = hoje).
3. Inscrição paga → cancelada → continua em "canceladas"; paga → pendente → sai; excluir paga é bloqueado.
4. POST forjado (categoria de outro tipo ou inativa, valor negativo, id de lançamento inexistente) → recusado.
5. Usuário só com "Financeiro" vê só essa página; quem não tem não abre `financeiro.php`.

### Task 1: Regras e contas (`includes/financeiro.php`)
Files: create `includes/financeiro.php`, `tests/financeiro_test.php`; modify `includes/db.php` (tabelas), `database/schema.sql`.
- `fin_centavos(string $texto): ?int` — "1.234,56" / "1234,56" / "1234.56" → 123456; vazio, negativo, letras ou 3 casas → null.
- `fin_moeda(int $centavos): string` — "R$ 1.234,56" (negativo: "−R$ 10,00").
- `fin_validar_lancamento(array $e, array $categorias): array` → `['dados' => [tipo, categoria_id, data, descricao, valor_centavos], 'erros' => [campo => msg]]`.
- `fin_automaticos(array $inscricoes): array` — linhas `{data, tipo, rotulo, valor (centavos), qtd}` agrupadas por dia e rótulo, ordenadas por data.
- `fin_balancete(array $manuais, array $automaticos, string $de, string $ate): array` — `$manuais` = `{id, data, tipo, categoria, descricao, valor (centavos), criado_por}`;
  devolve `saldo_anterior`, `entradas` (rótulo => centavos), `saidas`, `total_entradas`, `total_saidas`, `saldo_final`, `linhas`
  (cronológicas, manuais e automáticas juntas, cada uma com `saldo` acumulado). `$de`/`$ate` vazios = sem limite.
- Banco: `fin_garantir(PDO)` (categorias iniciais), `fin_categorias(PDO)`, `fin_lancamentos(PDO)`, `fin_inscricoes_com_pagamento(PDO)`.
- Testes: os 5 itens de cálculo da Review Focus 1–3 com arrays; validação (tipo errado, categoria inativa/de outro tipo, valor 0/negativo, data inválida).

### Task 2: Página Financeiro e permissão
Files: create `admin/financeiro.php`; modify `includes/acessos.php`, `assets/css/admin.css`, `tests/acessos_test.php` (se precisar), scratchpad `cen-acessos.js` (menu esperado).
- Filtro De/Até; cartões Entradas, Saídas, Saldo; formulário Novo lançamento (com Editar reaproveitando o mesmo formulário, `?editar=ID`);
  balancete (entradas e saídas por rótulo, totais, saldo); lista com saldo acumulado, automáticos marcados, Editar/Excluir nos manuais;
  `?csv=1` exporta balancete + lançamentos do período (`;`, BOM, `celula()` contra fórmula); bloco Categorias (criar, renomear,
  ativar/desativar, excluir só sem lançamentos).
- Ações POST: `lancar`, `editar`, `excluir`, `cat_criar`, `cat_renomear`, `cat_ativar`, `cat_excluir`. Flash + redirect mantendo o filtro.
- Cenário `cen-financeiro.js`: lança entrada e saída, edita, exclui; cria/renomeia/desativa categoria; categoria com lançamento não exclui;
  com inscrições de teste (paga, paga→cancelada, pendente) o balancete mostra os automáticos certos e Taxa = Repasse; filtro com
  saldo anterior; CSV; usuário só com Financeiro vê só Financeiro + Minha conta.

### Task 3: Não excluir inscrição já paga
Files: `admin/acao.php`, `admin/inscrito.php`.
- `acao=excluir` com `pago_em` preenchido → flash `Inscrição já paga não pode ser excluída (o valor está no financeiro). Use Cancelar.`; sem botão Excluir na tela.
- Cenário: inscrição paga → POST excluir recusado (continua no banco); nunca paga → exclui.
