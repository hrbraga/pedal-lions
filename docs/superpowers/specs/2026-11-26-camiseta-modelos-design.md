# Camiseta com modelos (manga curta / manga longa) – desenho (curto)

## Intenção
A camiseta passa a ter manga curta e manga longa, com preços diferentes, escolhidas na inscrição.
Precisa estar no ar antes da abertura das inscrições (01/12/2026 12:00).

## Abordagens
- **Lista de modelos com preço próprio (escolhida).** Dados do Evento → Camiseta ganha uma lista de modelos (nome + valor),
  começando com "Manga curta" e "Manga longa". Permite outro modelo no futuro sem código.
- Acréscimo de manga longa sobre o preço da curta: descartado (menos claro para o atleta e para o painel).

## Como funciona
- **Dados:** `camiseta.modelos` = lista de `{slug, nome, valor}`; tamanhos, prazo e arte continuam únicos.
  Dados antigos sem `modelos` viram um modelo "Camiseta" com o `valor` antigo.
- **Inscrição:** marcando "Quero a camiseta", escolhe o modelo (com preço) e o tamanho; o resumo e o total usam o preço
  do modelo. Sem taxa e sem cupom, como hoje. O servidor confere modelo e tamanho e grava o preço do modelo.
- **Banco:** coluna nova `inscricoes.camiseta_modelo` (slug; NULL = sem camiseta ou inscrição antiga), criada sozinha.
- **Painel:** contagem de camisetas por modelo × tamanho; detalhe do inscrito e CSV mostram o modelo.
- **Dados do Evento → Camiseta:** lista de modelos (nome e valor, subir/descer/remover), prazo e tamanhos.
- **FAQ padrão:** cita os preços dos dois modelos.

## Premissas (ajustáveis no painel)
- Mesmos tamanhos para os dois modelos; uma única arte.
- Preço inicial: manga curta R$ 130,00 (valor atual); manga longa a confirmar com a organização antes de publicar.

## Fora do escopo
- Tamanhos ou artes diferentes por modelo.
- Impedir a remoção de um modelo que já tenha compras (mostra o identificador salvo).

## Testes esperados
- PHP: validação dos modelos (nome, valor, pelo menos um), compatibilidade com o formato antigo, ida e volta do formulário.
- Navegador: inscrição com manga longa G grava modelo, tamanho e preço certos e o total bate; painel mostra a contagem
  por modelo × tamanho; Dados do Evento salva um preço novo e a inscrição passa a usá-lo.
