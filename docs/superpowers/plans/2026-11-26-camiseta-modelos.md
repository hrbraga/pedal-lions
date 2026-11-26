# Camiseta com modelos – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Manga curta e manga longa, com preços próprios, escolhidas na inscrição e contadas no painel.

**Architecture:** `camiseta_modelos()` em `includes/funcoes.php` normaliza os modelos (com o formato antigo como padrão).
`evento_validar` valida a lista; `inscricao.php` grava `camiseta_modelo` e o preço do modelo; o painel agrupa por modelo × tamanho.

**Tech Stack:** PHP 8.2, PDO MySQL, JS sem framework. Testes: `tests/*_test.php`, cenário CDP `cen-camiseta.js`.

**Spec:** `docs/superpowers/specs/2026-11-26-camiseta-modelos-design.md`

## Global Constraints
- Modelo: `slug` (`[a-z0-9-]`, até 20, estável depois de criado), `nome` (obrigatório, até 40), `valor` (dinheiro ≥ 0).
- Pelo menos um modelo. Texto de erro: `Informe pelo menos um modelo de camiseta.`
- Padrão: `[['slug' => 'curta', 'nome' => 'Manga curta', 'valor' => 130.0], ['slug' => 'longa', 'nome' => 'Manga longa', 'valor' => <a confirmar>]]`.
- Coluna `inscricoes.camiseta_modelo VARCHAR(20) NULL`.
- Erros na inscrição: `Escolha o modelo da camiseta.` · `Escolha o tamanho da camiseta.` (já existe).
- Nada vai para a `main` sem o ok do usuário.

## Review Focus
1. Preço forjado no formulário (POST com outro valor) → o servidor sempre usa o preço do modelo salvo.
2. Modelo inexistente ou vazio com "quero camiseta" marcado → recusado com mensagem.
3. Dados salvos antes da mudança (só `valor`) → um modelo "Camiseta" com esse valor; nada quebra.
4. Inscrições antigas sem modelo → painel e CSV mostram "—" no modelo, contagem continua.

### Task 1: Modelos nos dados (funções, validação, formulário do painel)
Files: `includes/funcoes.php`, `includes/evento_validar.php`, `config/evento.php`, `admin/dados.php`, `tests/evento_validar_test.php`.
- `camiseta_modelos(array $cam): array` — lista de `{slug, nome, valor(float)}`; sem `modelos` → `[{slug 'camiseta', nome 'Camiseta', valor}]`.
- `camiseta_modelo(string $slug): ?array` — modelo do evento atual pelo slug.
- `evento_para_formulario()['camiseta']['modelos']` e `evento_validar` gravando `camiseta.modelos` (sem `valor`).
- Testes: ida e volta; nome vazio; valor inválido; lista vazia; slug novo gerado do nome; formato antigo.
- Dados do Evento → Camiseta: lista "Modelos" (nome + valor) no lugar do campo Valor.

### Task 2: Inscrição grava o modelo
Files: `includes/db.php`, `database/schema.sql`, `inscricao.php`, `assets/js/main.js`, `inscricao_recebida.php` (se mostrar a camiseta).
- Rádio `camiseta_modelo` com nome e preço (`data-valor`); JS soma o modelo escolhido; resumo "Manga longa – G".
- Servidor: modelo válido obrigatório quando "quero camiseta"; `valor_camiseta` = preço do modelo; grava `camiseta_modelo`.
- Cenário: inscrição manga longa G → banco com modelo `longa`, tamanho `G`, valor do modelo, total certo; preço forjado ignorado.

### Task 3: Painel e exportação
Files: `admin/painel.php`, `admin/inscrito.php`, `admin/exportar.php`, `config/evento.php` (FAQ).
- Contagem por modelo × tamanho (linhas = modelos, colunas = tamanhos, pagas/pendentes); detalhe e CSV com o modelo.
- Cenário: depois da inscrição de teste, o painel mostra "Manga longa" com 1 pendente no G; CSV traz o modelo.
