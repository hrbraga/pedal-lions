# Avatar "Eu vou!" — design

## Para que serve
Página onde o participante (ou quem pretende ir) coloca a própria foto dentro da moldura oficial do
2º Pedal Solidário e baixa ou compartilha a imagem para postar no Instagram. É divulgação boca a boca
do evento: quanto mais fácil no celular, mais gente posta.

## Objetivos
- Funcionar bem no celular, que é onde as pessoas vão usar (o computador vem em segundo lugar).
- Dois formatos: **Feed 1:1** (1080×1080) e **Stories 9:16** (1080×1920).
- A pessoa escolhe a foto uma vez e ajusta posição e zoom **separadamente em cada formato**, porque
  os recortes das molduras têm formatos diferentes.
- Botão **Compartilhar** (folha de compartilhamento do celular, que leva ao Instagram). Onde não houver
  suporte, o botão vira **Baixar**.

## Fora do escopo
- Não envia, não guarda e não registra a foto em lugar nenhum. Tudo acontece no navegador.
- Sem filtros, textos personalizados, stickers ou edição além de posição e zoom.
- Não exige inscrição: qualquer pessoa pode usar.

## Seria um fracasso se
- A imagem final saísse borrada, cortada errado ou com a moldura deslocada.
- No iPhone não fosse possível salvar nem compartilhar.
- A página travasse com fotos grandes de celular (12 MP ou mais).

## Abordagens consideradas
- **A. Canvas no navegador, JavaScript puro (escolhida).** Segue o padrão do `assets/js/main.js`,
  não depende de biblioteca, a foto não sai do aparelho e funciona em qualquer hospedagem.
- B. Cropper.js. O gesto de pinça vem pronto, mas a moldura teria que ser sobreposta à mão e o projeto
  ganharia uma dependência externa. Exagero para posição + zoom.
- C. Montagem no servidor (PHP GD). O GD está desligado no XAMPP, a foto de terceiros passaria pelo
  servidor (LGPD) e seria mais lento. Descartada.

## Como funciona (decidido)
- **Página nova `avatar.php`** com o layout do site (`header.php`/`footer.php`), o título
  "Eu vou!" e a chave de menu `avatar`.
- **Molduras:** `assets/img/avatar/moldura-1x1.png` e `assets/img/avatar/moldura-9x16.png`.
  As atuais são provisórias (quadriculado removido por script). As definitivas entram no lugar
  com o mesmo nome, sem mexer no código.
- **Fluxo:**
  1. Botão "Escolher foto": abre a galeria ou a câmera (`<input type="file" accept="image/*">`).
  2. Abas **Feed | Stories**. A prévia mostra a foto atrás da moldura no formato da aba ativa.
  3. Ajuste: arrastar (dedo ou mouse), pinça com dois dedos, controle deslizante de zoom e
     botão "Centralizar". Cada aba guarda o seu próprio ajuste.
  4. Na primeira vez em cada aba, a foto entra já preenchendo o recorte e centralizada.
  5. Botão **Compartilhar** (usa `navigator.share` com arquivo, quando o aparelho suporta) e botão
     **Baixar** (sempre disponível). Gera um PNG de 1080 px de largura no formato da aba ativa.
- **Nomes dos arquivos:** `pedal-solidario-eu-vou-feed.png` e `pedal-solidario-eu-vou-stories.png`.
- **Fotos grandes:** a foto é reduzida uma única vez ao carregar (lado maior em torno de 2160 px),
  para não estourar a memória do celular. A orientação EXIF do iPhone é respeitada (`createImageBitmap`
  com `imageOrientation: 'from-image'`, com alternativa para navegadores antigos).
- **Onde aparece:**
  - Item **"Eu vou!"** no menu principal.
  - Botão na home, ao lado de "Ver rotas".
  - Chamada na página de inscrição recebida (`inscricao_recebida.php`): "Inscrição feita? Avise a galera!".
- **Privacidade:** frase curta na página dizendo que a foto não é enviada a lugar nenhum.

## Deixado a cargo de quem constrói
- Visual dos controles, seguindo a identidade atual (azul `#0A1E4D`, dourado, Barlow).
- Limites de zoom (mínimo: preencher o recorte; máximo sugerido: 4×).
- Detalhes de acessibilidade (rótulos, uso pelo teclado: setas movem e +/− dão zoom).
- Arquivos novos sugeridos: `avatar.php` e `assets/js/avatar.js` (carregado só nessa página),
  mais um bloco de CSS em `assets/css/style.css`.
