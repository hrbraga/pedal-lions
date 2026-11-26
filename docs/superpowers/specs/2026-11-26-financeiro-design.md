# Financeiro do painel – desenho

## Intenção
A organização (Lions) precisa saber quanto entrou e quanto saiu no evento, sem planilha paralela: lançar à mão o que
não passa pelo site (patrocínios, doações, todas as despesas) e ver um balancete com tudo, inclusive o que entrou pelas
inscrições. Pedido do usuário: "lançamento de caixa, bem simples", categorias de entrada/saída cadastradas no painel,
"relatório tipo balancete com todos os lançamentos efetuados". Depois, o Dashboard usa estes números (entradas por
origem: patrocínios, inscrições, camisetas; saídas por categoria; balanço).

## Objetivos
- Lançar, editar e excluir entradas e saídas manuais (data, categoria, descrição, valor).
- Cadastrar, renomear e desativar categorias de entrada e de saída no próprio painel.
- Inscrições e camisetas pagas entram sozinhas no balancete (abordagem A, escolhida pelo usuário).
- Balancete por período: totais por categoria, total de entradas, de saídas e saldo, mais a lista de lançamentos.
- Permissão própria ("Financeiro") em Administradores, para liberar só o financeiro ao tesoureiro.

## Não-objetivos
- Contas bancárias separadas, conciliação com extrato, anexos de comprovantes, contas a pagar/receber, parcelas.
- Gerar recibo ou nota fiscal.
- Mexer no valor das inscrições pelo financeiro (o que vem do site só é corrigido mudando a inscrição).

## Anti-objetivos (o que faria isso fracassar mesmo funcionando)
- Contar duas vezes o dinheiro das inscrições (automático + lançado à mão).
- Número do balancete diferente do painel de Inscritos.
- Tela complicada para quem não é da área (tem que ser "bem simples").

## Abordagens
- **A) Inscrições e camisetas automáticas (escolhida).** O balancete soma as inscrições pagas pela data do pagamento;
  à mão, só o resto. Não há digitação dupla e o número bate com a tela de Inscritos; tarifas do Asaas/banco entram
  como saída manual.
- B) Tudo à mão: bate com o extrato, mas é trabalho repetido e pode divergir da lista de inscritos. Descartada.

## Como funciona
### Página "Financeiro" (admin/financeiro.php)
- Chave de acesso `financeiro`, no menu entre "Dados do Evento" e "Administradores". Quem tem acesso total já vê.
- Topo: filtro de período (De / Até; padrão: tudo) e os três números grandes: **Entradas**, **Saídas**, **Saldo**.
- **Novo lançamento:** Entrada ou Saída, Data (padrão: hoje), Categoria (só as ativas do tipo escolhido), Descrição
  (até 200), Valor (R$, maior que zero). Botão Salvar.
- **Balancete** do período:
  - Entradas por categoria, incluindo as automáticas "Inscrições", "Taxa de administração" e "Camisetas", e o total.
  - Saídas por categoria e o total.
  - Saldo (entradas − saídas).
- **Lançamentos** do período, do mais antigo ao mais novo, com saldo acumulado:
  - manuais: data, tipo, categoria, descrição, valor, quem lançou; botões Editar e Excluir (com confirmação);
  - automáticos: uma linha por dia de pagamento para cada origem (ex.: "Inscrições pagas em 05/12/2026 – 12
    inscrições"), sem botões, marcadas como "automático".
- **Exportar CSV** do balancete e dos lançamentos do período (abre no Excel, como o CSV de inscritos).
- **Categorias** (bloco na mesma página): duas listas (Entrada / Saída) com nome e "ativa"; criar, renomear,
  desativar. Categoria com lançamentos não pode ser excluída, só desativada; sem lançamentos pode ser excluída.

### Valores automáticos (vindos das inscrições)
- Data: `pago_em` (o dia em que o pagamento foi confirmado).
- Inscrições pagas (situação **paga**): "Inscrições" = `valor_inscricao − valor_desconto`; "Camisetas" = `valor_camiseta`.
- **Pagas e depois canceladas** (situação cancelada com `pago_em` preenchido) — decisão do usuário: o dinheiro
  continua, numa entrada própria "Inscrições canceladas (sem reembolso)" = `valor_inscricao − valor_desconto + valor_camiseta`.
  Se houver reembolso, lança-se uma saída manual (categoria "Reembolsos").
- **Taxa de administração** — decisão do usuário: ela paga o gateway/terceiro. Entra como "Taxa de administração"
  (`valor_taxa` de toda inscrição com `pago_em`) e sai, no mesmo dia e valor, como "Repasse da taxa de administração".
  Efeito no saldo: zero.
- Inscrição que volta a pendente (`pago_em` apagado) sai do balancete sozinha.
- **Excluir uma inscrição com `pago_em`** passa a ser bloqueado na tela do inscrito (pode cancelar); assim o dinheiro
  não some dos livros. Inscrição nunca paga continua podendo ser excluída.

### Banco
- `fin_categorias`: id, tipo (entrada|saida), nome (até 60), ativa (1/0), criado_em; nome único por tipo.
- `fin_lancamentos`: id, tipo (entrada|saida), categoria_id, data (DATE), descricao (até 200), valor DECIMAL(10,2),
  criado_por, criado_em, alterado_por, alterado_em.
- Criadas sozinhas na primeira visita (como as outras atualizações) e também no `schema.sql`.
- Categorias iniciais (editáveis): Entrada — Patrocínios, Doações, Outras entradas. Saída — Troféus e medalhas,
  Camisetas (fornecedor), Kits e brindes, Alimentação e hidratação, Estrutura e som, Divulgação, Seguro atleta,
  Tarifas (Asaas/banco), Reembolsos, Outras saídas.

### Para o Dashboard (depois)
- Uma função de resumo por período (entradas por categoria/origem, saídas por categoria, totais e saldo) usada pelo
  balancete; o Dashboard reaproveita a mesma, sem refazer contas.

## Restrições
- PHP 8.2, MySQL, sem bibliotecas novas; mesmo visual do painel; funciona no celular.
- Valores em centavos exatos (DECIMAL no banco; nada de somar float para exibir totais sem arredondar a 2 casas).
- Todas as ações com CSRF e `admin_exigir('financeiro')`; quem lançou/alterou fica registrado.

## Decisões do usuário (2026-11-26)
- Abordagem A (inscrições e camisetas automáticas).
- Paga e depois cancelada: o dinheiro continua, em linha própria.
- Taxa de administração paga o gateway/terceiro: entrada e repasse automáticos, saldo zero.

## Decisões minhas (para o usuário conferir)
- Lançamentos automáticos agrupados por dia (não um por inscrito), para a lista não ficar enorme.
- Período padrão: tudo. Com data inicial no filtro, a lista começa com "Saldo anterior" (tudo antes do período) e o
  saldo acumulado parte dele; os totais do período (Entradas, Saídas) são só do período, e "Saldo" mostra o final.
- Sem "saldo inicial" automático (se precisar, lança como entrada "Outras entradas").
- Excluir lançamento manual é permanente (com confirmação); não há lixeira nem histórico além de quem/quando.
- A data automática é a da confirmação no painel; quem confirma tarde pode lançar a diferença de datas à mão se
  precisar. Voltar para pendente e confirmar de novo muda a data (aceito: raro).

## Deixado para quem constrói
- Layout exato dos blocos, textos de ajuda, paginação da lista se crescer muito.

## Testes esperados
- PHP: validação do lançamento (tipo, data, categoria ativa do mesmo tipo, valor > 0, descrição), soma do balancete com
  lançamentos manuais + automáticos, inscrição pendente/cancelada fora do automático, agrupamento por dia, período.
- Navegador: lançar entrada e saída, editar, excluir; criar/renomear/desativar categoria; categoria com lançamentos não
  se exclui; balancete bate com os números de Inscritos; CSV; usuário só com "Financeiro" vê só essa página.
