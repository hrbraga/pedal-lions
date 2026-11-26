# Lista pública de inscritos – desenho (curto, aprovado em 2026-11-26)

## Intenção
Participantes e público veem quem já está inscrito, separados por categoria e sexo, só quando a organização liberar.

## Como funciona
- Página `inscritos.php`: busca por nome no topo (filtra enquanto digita, sem diferenciar acentos/maiúsculas, esconde grupos
  sem resultado); grupos na ordem das Categorias de Dados do Evento, cada uma em Masc e Fem ("Light Masc", "Light Fem", ...),
  com a quantidade; nomes em ordem alfabética com **nome, cidade e equipe**. Grupo vazio: "Nenhum inscrito ainda".
- Só inscrições **pagas** (decisão do usuário). Nunca CPF, telefone, e-mail, nascimento ou saúde.
- Liberação: Dados do Evento → Inscrições → "Mostrar a lista de inscritos no site" (padrão: desmarcado).
  Marcado: item "Inscritos" no menu do site e botão "Ver inscritos" na página inicial. Desmarcado: sem menu e botão; a página
  responde "A lista de inscritos ainda não foi liberada." (código 404).
- Decisões minhas: ordem dos grupos = ordem das categorias no painel (o usuário reordena lá para Light, Hard, E-bike, Rachão);
  a página tem `noindex` (a lista de nomes não aparece no Google); categoria removida do evento não aparece na lista.

## Fora do escopo
- Filtro por categoria, exportar a lista, mostrar pendentes.

## Testes
- PHP: agrupamento (ordem, Masc/Fem, só pagas, ordem alfabética com acentos, categoria desconhecida ignorada).
- Navegador: desligado → sem menu/botão e página bloqueada; ligar no painel → menu, botão e grupos certos; busca filtra.
