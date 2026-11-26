# Logos dos patrocinadores pelo painel – desenho (curto)

## Intenção
Quem não mexe em código (pessoas do Lions) envia, troca, edita e exclui patrocinadores pelo painel, sem pedir ao
desenvolvedor para colocar arquivos em `assets/img/patrocinadores/`.

## Abordagens
- **A) Lista dentro de Dados do Evento (escolhida).** A seção Patrocinadores vira uma lista como Categorias, salva
  pelo mesmo botão e guardada no Histórico (dá para restaurar). Quem tem Dados do Evento cuida dos patrocinadores.
- B) Página própria com ações imediatas e permissão separada; sem histórico. Descartada pela organização.

## Como funciona
- **Dados:** `patrocinadores` passa a ser uma lista (a ordem da lista é a ordem do carrossel). Cada item:
  `logo` (caminho do arquivo), `nome` (obrigatório – vira o texto alternativo) e `url` (opcional, https://).
- **Logos de hoje:** enquanto nada for salvo com a lista nova, o site continua lendo a pasta
  `assets/img/patrocinadores/` com `patrocinadores_info`, como hoje. O painel mostra esses 4 logos já como itens
  da lista; no primeiro Salvar eles passam para a lista, sem reenviar nada.
- **Envio:** cada item tem um campo de arquivo ("Enviar logo" / "Trocar logo") com pré-visualização ao escolher.
  - Aceita PNG, JPG e WEBP de até 2 MB, conferidos pelo conteúdo do arquivo (não pela extensão). SVG não
    (pode levar código).
  - O arquivo é gravado em `uploads/patrocinadores/` com nome sorteado pelo site (ex.: `3f9a…c2.png`);
    o nome original nunca vira nome de arquivo.
  - A pasta tem `.htaccess` que só serve imagens e não lista arquivos; fica fora do git, para uma atualização do
    site não apagar os logos.
  - Se o Salvar der erro em outro campo, o logo já enviado é guardado e continua no formulário (o navegador
    esqueceria o arquivo escolhido).
- **Excluir / trocar:** tira da lista; o arquivo antigo fica guardado enquanto alguma versão do Histórico usar.
  Depois de cada Salvar, arquivos que nenhuma das 50 versões usa (e com mais de um dia) são apagados.
- **Restaurar versão antiga:** volta também com os logos daquela versão. Logo cujo arquivo sumiu não aparece no
  site (o resto continua).
- **Site:** o carrossel da página inicial lê a lista; sem mudança visual.
- **Sem redução automática de imagem** (o PHP do XAMPP não tem a extensão de imagens); a dica de ~400 px de
  largura aparece ao lado do campo.

## Fora do escopo
- Redimensionar/cortar o logo no site.
- Página própria e permissão separada para patrocinadores (abordagem B).

## Testes esperados
- Script PHP: validação do arquivo (PNG/JPG/WEBP válidos aceitos; SVG, texto renomeado para .png, maior que
  2 MB recusados) e do caminho do logo (só da pasta de envios ou da pasta antiga, sem `..`).
- No navegador: enviar um logo novo com nome e link → aparece no carrossel; reordenar; trocar o logo; excluir;
  erro em outro campo não perde o logo enviado; restaurar versão antiga traz os logos dela; arquivo `.php`
  renomeado para `.png` é recusado.
