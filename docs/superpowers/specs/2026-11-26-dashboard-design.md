# Dashboard do painel – desenho (curto, aprovado em 2026-11-26)

## Intenção
Uma página para a organização ver o evento de relance: inscrições, perfil dos inscritos, camisetas e dinheiro.

## Abordagens
- **Gráficos desenhados pelo próprio site, HTML/CSS (escolhida):** sem dependência externa, leve, funciona no celular e
  na impressão, com o número exato ao passar o mouse/tocar e "ver em tabela".
- Biblioteca de gráficos externa (Chart.js): descartada (depende de outro servidor, mais pesada, sem ganho aqui).

## Como funciona
- Página `admin/dashboard.php`, **primeira do menu**, permissão própria `dashboard`; quem tem acesso total cai nela ao entrar.
- Contam **pagas e pendentes**, separadas por cor (azul = pagas, laranja = pendentes); canceladas fora.
- **Resumo do evento:** destaques (inscrições pagas/pendentes, vagas restantes de N, camisetas); por categoria (barras
  empilhadas pagas/pendentes, na ordem das categorias); por gênero; por idade no dia do evento (até 17, 18–29, 30–39,
  40–49, 50–59, 60+); camisetas por modelo × tamanho; cidades (10 maiores + "Outras"); equipes (10 maiores) e quantos
  "Sem equipe"; inscrições por dia (colunas).
- **Financeiro** (só para quem também tem a permissão Financeiro): destaques Entradas/Saídas/Saldo; Entradas x Saídas;
  entradas por origem; saídas por categoria — os mesmos números do balancete (mesmas funções, evento inteiro).
- Cada gráfico tem "ver em tabela"; sem filtro de período.

## Regras de gráfico (guia dataviz)
Barras finas (≤ 24px), ponta arredondada, 2px entre segmentos; legenda quando houver 2 séries; valores em texto (não na cor
da série); dica com o número ao passar o mouse ou focar; paleta categórica fixa (azul #2a78d6, laranja #eb6834) validada.

## Testes
- PHP: agregações (idade e faixas, contagens por categoria/gênero/idade/modelo×tamanho, cidades com "Outras",
  equipes e "Sem equipe", por dia, canceladas fora).
- Navegador: números batem com inscrições de teste; financeiro só com a permissão; usuário só com Dashboard.
