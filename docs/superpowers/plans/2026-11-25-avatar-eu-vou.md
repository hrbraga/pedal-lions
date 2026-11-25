# Avatar "Eu vou!" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Página `avatar.php` onde a pessoa coloca a própria foto atrás da moldura oficial, ajusta posição/zoom por formato (Feed 1:1 e Stories 9:16) e compartilha ou baixa o PNG.

**Architecture:** Tudo no navegador. A matemática (zoom, limites, recorte transparente, redução da foto) fica num módulo puro `assets/js/avatar-geometria.js`, testado com `node --test`. A interface (`assets/js/avatar.js`) desenha num `<canvas>` de saída 1080 px (foto + moldura por cima) e usa o módulo. PHP só monta a página.

**Tech Stack:** PHP 8.2 (XAMPP), JavaScript puro (ES2017, sem bundler, sem dependências), Canvas 2D, Web Share API, `node:test` (Node 22) para os testes do módulo puro.

**Spec:** `docs/superpowers/specs/2026-11-25-avatar-eu-vou-design.md`

## Global Constraints

- Nenhuma biblioteca nova, nenhum envio da foto ao servidor (sem `fetch`/`XMLHttpRequest`/form com a foto).
- Saída: Feed **1080×1080**, Stories **1080×1920**, PNG.
- Molduras: `assets/img/avatar/moldura-1x1.png` e `assets/img/avatar/moldura-9x16.png` (únicos caminhos usados; trocáveis sem mexer no código).
- Nomes: `pedal-solidario-eu-vou-feed.png` e `pedal-solidario-eu-vou-stories.png`.
- Foto reduzida ao carregar: lado maior ≤ **2160 px**.
- Zoom: mínimo = foto cobrindo o canvas inteiro (zoom 1); máximo **4**.
- Textos da página em português; identidade atual (variáveis `--azul`, `--ouro`, fonte Barlow); chave de menu `avatar`, rótulo **"Eu vou!"**.
- Frase de privacidade visível: "Sua foto não sai do seu aparelho: a imagem é montada aqui mesmo, no navegador."
- Trabalhar na branch `feat/avatar-eu-vou`.
- JS no padrão do `main.js`: IIFE, `var`/`function`, sem módulos ES (o arquivo de geometria exporta para `window.AvatarGeometria` e, no Node, para `module.exports`).

## Review Focus

1. **Foto do iPhone com orientação EXIF** → deve aparecer em pé, como na galeria. (Task 3: `createImageBitmap(arquivo, {imageOrientation:'from-image'})`; verificação manual com foto em retrato do iPhone.)
2. **Foto enorme (48 MP)** → não pode travar nem estourar o limite de canvas do iOS (16,7 Mpx); é reduzida para ≤ 2160 px. (Task 1: teste `reduzirDimensoes`; Task 3 usa.)
3. **Arquivo que o navegador não decodifica (HEIC no Chrome desktop, PDF renomeado)** → mensagem "Não conseguimos abrir essa imagem. Tente uma foto em JPG ou PNG." e a página continua utilizável. (Task 3, passo de verificação manual com um `.txt` renomeado para `.jpg`.)
4. **Compartilhar sem suporte a arquivo (Firefox, desktop) ou cancelado pelo usuário** → botão Compartilhar escondido quando `navigator.canShare({files})` for falso; cancelar (`AbortError`) não mostra erro. (Task 4.)
5. **Botões usados antes de escolher a foto / troca de aba antes da foto** → Compartilhar/Baixar desabilitados até haver foto; trocar aba sem foto só troca a moldura exibida. (Task 3/4.)

---

## File Structure

- Create `assets/js/avatar-geometria.js` — funções puras (sem DOM).
- Create `tests/avatar-geometria.test.js` — testes `node:test`.
- Create `avatar.php` — página.
- Create `assets/js/avatar.js` — interface (carregar foto, desenhar, gestos, exportar).
- Modify `includes/header.php` — item de menu.
- Modify `includes/footer.php` — aceitar `$scripts_extra` (lista de caminhos) após `main.js`.
- Modify `assets/css/style.css` — bloco `/* ===== Avatar ===== */` no fim.
- Modify `index.php:17-22` — botão "Eu vou!" no `.hero__acoes`.
- Modify `inscricao_recebida.php:80` — chamada antes de "Voltar ao início" (só no ramo de inscrição encontrada).
- Modify `README.md` — seção "Avatar Eu vou!".
- Add `assets/img/avatar/moldura-1x1.png`, `assets/img/avatar/moldura-9x16.png` (já gerados). Os originais `assets/img/Moldura Maior.png` / `Moldura Menor.png` **não** são commitados (são a arte com quadriculado falso).

---

### Task 1: Módulo de geometria (puro, testado)

**Files:**
- Create: `assets/js/avatar-geometria.js`
- Test: `tests/avatar-geometria.test.js`

**Interfaces:**
- Produces (em `window.AvatarGeometria` / `module.exports`):
  - `ZOOM_MAX = 4`, `LADO_MAX_FOTO = 2160`
  - `FORMATOS = { feed: {largura:1080, altura:1080, moldura:'assets/img/avatar/moldura-1x1.png', arquivo:'pedal-solidario-eu-vou-feed.png'}, stories: {largura:1080, altura:1920, moldura:'assets/img/avatar/moldura-9x16.png', arquivo:'pedal-solidario-eu-vou-stories.png'} }`
  - `escalaBase(foto:{w,h}, saida:{w,h}) -> number` — escala que faz a foto cobrir a saída: `max(saida.w/foto.w, saida.h/foto.h)`.
  - Estado: `{ zoom:number, x:number, y:number }` — `x,y` = canto superior esquerdo da foto em px da saída; escala real = `escalaBase * zoom`.
  - `limitar(estado, foto, saida) -> estado` — zoom em `[1, ZOOM_MAX]`; `x ∈ [saida.w - foto.w*s, 0]`, idem `y` (a foto sempre cobre a saída).
  - `estadoInicial(foto, saida, buraco:{x,y,w,h}|null) -> estado` — zoom 1, centro da foto no centro do `buraco` (ou da saída se `null`), depois `limitar`.
  - `mover(estado, dx, dy, foto, saida) -> estado`.
  - `zoomEm(estado, novoZoom, ponto:{x,y}, foto, saida) -> estado` — o ponto da saída sob `ponto` continua no mesmo lugar da foto; depois `limitar`.
  - `areaTransparente(rgba:Uint8ClampedArray, largura, altura, limiar=10) -> {x,y,w,h}|null` — retângulo envolvente dos pixels com alfa < `limiar`; `null` se nenhum.
  - `reduzirDimensoes(w, h, maxLado=LADO_MAX_FOTO) -> {w,h}` — mantém proporção, arredonda, nunca amplia.

- [ ] **Step 1: Criar a branch**

Run: `git checkout -b feat/avatar-eu-vou`
Expected: `Switched to a new branch 'feat/avatar-eu-vou'`

- [ ] **Step 2: Escrever os testes que falham** em `tests/avatar-geometria.test.js` (`require('node:test')`, `require('node:assert/strict')`, `require('../assets/js/avatar-geometria.js')`):

```js
test('escalaBase cobre a saída', () => {
  assert.equal(G.escalaBase({w:2000,h:1000},{w:1080,h:1080}), 1.08);   // limitado pela altura
  assert.equal(G.escalaBase({w:1000,h:2000},{w:1080,h:1920}), 1.08);   // limitado pela largura
});
test('limitar mantém a foto cobrindo a saída e o zoom em [1,4]', () => {
  const f={w:1000,h:1000}, s={w:1080,h:1080};          // escalaBase 1.08 → 1080 px no zoom 1
  assert.deepEqual(G.limitar({zoom:0.5,x:50,y:-999},f,s), {zoom:1,x:0,y:0});
  assert.deepEqual(G.limitar({zoom:9,x:-99999,y:10},f,s), {zoom:4,x:1080-4320,y:0});
});
test('estadoInicial centraliza no buraco', () => {
  const f={w:2000,h:1000}, s={w:1080,h:1080};          // foto vira 2160×1080
  assert.deepEqual(G.estadoInicial(f,s,{x:540,y:0,w:540,h:1080}), {zoom:1,x:810-1080,y:0});
  assert.deepEqual(G.estadoInicial(f,s,null), {zoom:1,x:-540,y:0});
});
test('mover soma o deslocamento e respeita limites', () => {
  const f={w:2000,h:1000}, s={w:1080,h:1080};
  assert.deepEqual(G.mover({zoom:1,x:-540,y:0},100,50,f,s), {zoom:1,x:-440,y:0});
  assert.deepEqual(G.mover({zoom:1,x:-540,y:0},5000,0,f,s), {zoom:1,x:0,y:0});
});
test('zoomEm mantém o ponto sob o dedo', () => {
  const f={w:1000,h:1000}, s={w:1080,h:1080};
  const r=G.zoomEm({zoom:1,x:0,y:0},2,{x:540,y:540},f,s);
  assert.deepEqual(r, {zoom:2,x:-540,y:-540});
});
test('areaTransparente acha o retângulo do recorte', () => {
  const w=4,h=3, px=new Uint8ClampedArray(w*h*4).fill(255);
  px[(1*w+1)*4+3]=0; px[(2*w+2)*4+3]=5;              // (1,1) e (2,2) transparentes
  assert.deepEqual(G.areaTransparente(px,w,h), {x:1,y:1,w:2,h:2});
  assert.equal(G.areaTransparente(new Uint8ClampedArray(16).fill(255),2,2), null);
});
test('reduzirDimensoes limita o lado maior a 2160 sem ampliar', () => {
  assert.deepEqual(G.reduzirDimensoes(8064,6048), {w:2160,h:1620});
  assert.deepEqual(G.reduzirDimensoes(3000,4000), {w:1620,h:2160});
  assert.deepEqual(G.reduzirDimensoes(800,600), {w:800,h:600});
});
test('FORMATOS tem os tamanhos e nomes do spec', () => {
  assert.deepEqual([G.FORMATOS.feed.largura,G.FORMATOS.feed.altura],[1080,1080]);
  assert.deepEqual([G.FORMATOS.stories.largura,G.FORMATOS.stories.altura],[1080,1920]);
  assert.equal(G.FORMATOS.stories.arquivo,'pedal-solidario-eu-vou-stories.png');
});
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `node --test tests/`
Expected: FAIL com `Cannot find module '../assets/js/avatar-geometria.js'`

- [ ] **Step 4: Implementar `assets/js/avatar-geometria.js`** com as funções do bloco Interfaces. Envolver em `(function (raiz) { ... })(typeof window !== 'undefined' ? window : globalThis)`, exportando `raiz.AvatarGeometria = api` e, se `typeof module !== 'undefined'`, `module.exports = api`. Comparar números com igualdade exata nos testes ⇒ arredondar `x`/`y` com `Math.round` em `limitar`.

- [ ] **Step 5: Rodar e ver passar**

Run: `node --test tests/`
Expected: `# pass 8`, `# fail 0`

- [ ] **Step 6: Commit**

```bash
git add assets/js/avatar-geometria.js tests/avatar-geometria.test.js
git commit -m "feat(avatar): add pure geometry module with tests"
```

---

### Task 2: Página `avatar.php`, menu, molduras e CSS (sem comportamento)

**Files:**
- Create: `avatar.php`
- Modify: `includes/header.php` (array `$menu`, após `'faq'`), `includes/footer.php:43`, `assets/css/style.css` (fim)
- Add: `assets/img/avatar/moldura-1x1.png`, `assets/img/avatar/moldura-9x16.png`

**Interfaces:**
- Produces (marcação que a Task 3 usa — ids exatos):
  - `#avatar` (raiz, `data-formato="feed"`), `#avatar-arquivo` (`<input type="file" accept="image/*">`, visualmente escondido, acionado por `<label class="btn btn--ouro" for="avatar-arquivo">Escolher foto</label>`)
  - Abas: `<div role="tablist">` com `<button role="tab" data-formato="feed" aria-selected="true">Feed</button>` e `data-formato="stories"` (“Stories”)
  - `#avatar-canvas` (`<canvas width="1080" height="1080" tabindex="0" aria-label="Prévia: arraste para posicionar, use + e − para zoom">`), dentro de `.avatar__palco`
  - `#avatar-zoom` (`<input type="range" min="1" max="4" step="0.01" value="1" disabled>` com label "Zoom"), `#avatar-centralizar` (botão "Centralizar", disabled)
  - `#avatar-compartilhar` (`btn btn--ouro`, `hidden`, disabled) e `#avatar-baixar` (`btn btn--azul`, disabled)
  - `#avatar-aviso` (`<p role="status" aria-live="polite">`)
  - Parágrafo de privacidade (texto exato do Global Constraints)
- `footer.php`: após `main.js`, `foreach (($scripts_extra ?? []) as $s)` imprime `<script src="<?= e(asset($s)) ?>"></script>`. `avatar.php` define `$scripts_extra = ['assets/js/avatar-geometria.js', 'assets/js/avatar.js'];`, `$pagina = 'avatar'`, `$titulo = 'Eu vou!'`, `$descricao` curta.

- [ ] **Step 1: Copiar as molduras para o commit** (já existem em `assets/img/avatar/`). Verificar que têm alfa:

Run: `xxd -s 25 -l 1 -p assets/img/avatar/moldura-1x1.png; xxd -s 25 -l 1 -p assets/img/avatar/moldura-9x16.png`
Expected: `06` e `06`

- [ ] **Step 2: Criar `avatar.php`** no padrão de `faq.php` (`.pagina-topo` com h1 "Eu vou!" e subtítulo "Monte sua foto com a moldura do 2º Pedal Solidário e poste no Instagram."; depois `<section class="secao">` com a marcação do bloco Interfaces), **mais** o item de menu e o laço de `$scripts_extra` no footer.

- [ ] **Step 3: CSS** — bloco `/* ===== Avatar ===== */`: `.avatar__palco` com largura máx. 420 px, centralizado; `#avatar-canvas` com `width:100%; height:auto; touch-action:none; cursor:grab; border-radius` e fundo xadrez claro só enquanto não houver foto; abas com o mesmo estilo de botão pequeno (ativa em `--ouro`); botões em linha que quebram no celular. Sem cores novas fora das variáveis existentes.

- [ ] **Step 4: Verificar a página**

Run: `php -l avatar.php && php -l includes/footer.php && php -l includes/header.php`
Expected: `No syntax errors detected` (×3)

Run (servidor embutido, em segundo plano): `php -S localhost:8123` na raiz; depois `curl -s localhost:8123/avatar.php | grep -c 'id="avatar-canvas"\|avatar-geometria.js\|avatar.js\|aria-current="page">Eu vou!'`
Expected: `4`

Run: `curl -s localhost:8123/faq.php | grep -c 'avatar.js'`
Expected: `0` (scripts só na página do avatar)

- [ ] **Step 5: Commit**

```bash
git add avatar.php includes/header.php includes/footer.php assets/css/style.css assets/img/avatar/
git commit -m "feat(avatar): add Eu vou! page skeleton, menu entry and frames"
```

---

### Task 3: Interface — carregar foto, prévia, abas e ajuste

**Files:**
- Create: `assets/js/avatar.js`

**Interfaces:**
- Consumes: `window.AvatarGeometria` (Task 1), ids da Task 2.
- Produces (para a Task 4, dentro da mesma IIFE): `function desenhar(ctx, formato)` que pinta foto (estado do formato) + moldura num contexto de tamanho `FORMATOS[formato]`; variáveis `foto` (`ImageBitmap|HTMLCanvasElement|null`) e `formatoAtual` (`'feed'|'stories'`); `function atualizarBotoes()`.

- [ ] **Step 1: Molduras** — ao iniciar, carregar as duas `Image` de `FORMATOS[*].moldura`; ao carregar cada uma, desenhar num canvas auxiliar, `getImageData`, e guardar `buraco[formato] = areaTransparente(...)`.

- [ ] **Step 2: Carregar a foto** — no `change` de `#avatar-arquivo`: `createImageBitmap(arquivo, {imageOrientation:'from-image'})`; se lançar, tentar `<img>` + `URL.createObjectURL` (navegadores antigos aplicam EXIF sozinhos); se também falhar, `#avatar-aviso` = "Não conseguimos abrir essa imagem. Tente uma foto em JPG ou PNG." e nada mais muda. Reduzir para `reduzirDimensoes` num canvas e guardar em `foto`. Zerar os estados das duas abas (`estados = {feed:null, stories:null}`); o estado de cada aba é criado com `estadoInicial(fotoDims, saida, buraco[formato])` na primeira vez que ela é exibida.

- [ ] **Step 3: Desenhar** — `#avatar-canvas` recebe `width/height` do formato atual; `desenhar` pinta fundo `#0A1E4D`, a foto em `x,y` com escala `escalaBase*zoom`, e a moldura por cima a `0,0` em tamanho cheio. Sem foto: só a moldura. Redesenho via `requestAnimationFrame` (um por quadro).

- [ ] **Step 4: Abas** — clique em `[role=tab]`: atualiza `aria-selected`, `data-formato` da raiz, `formatoAtual`, o valor do `#avatar-zoom` para o zoom da aba, e redesenha.

- [ ] **Step 5: Gestos** — Pointer Events no canvas com `setPointerCapture`; converter deslocamento de px da tela para px da saída (`saida.w / canvas.getBoundingClientRect().width`). 1 ponteiro: `mover`. 2 ponteiros: `zoomEm` com razão das distâncias × zoom inicial do gesto, ponto = meio dos dois dedos. Roda do mouse: `zoomEm` com fator `Math.exp(-deltaY*0.0015)` no ponto do cursor (`preventDefault`). `#avatar-zoom` `input`: `zoomEm` no centro da saída. Teclado no canvas: setas movem 20 px da saída; `+`/`=` e `-` multiplicam o zoom por 1,1 / ÷1,1. `#avatar-centralizar`: `estadoInicial`. Tudo atualiza `#avatar-zoom.value`.

- [ ] **Step 6: Habilitar controles** — `atualizarBotoes()`: zoom, centralizar e baixar habilitados só com `foto`.

- [ ] **Step 7: Verificação no navegador** (`php -S localhost:8123`, abrir `/avatar.php`, de preferência com o celular na mesma rede ou com o modo dispositivo do DevTools):
  - Sem foto: aparece a moldura Feed; trocar para Stories mostra a moldura alta; zoom/baixar desabilitados.
  - Foto em paisagem e em retrato: entram preenchendo o recorte, centralizadas; arrastar não deixa aparecer fundo azul; zoom vai de 1 a 4.
  - Ajustar o Feed, trocar para Stories, voltar: o ajuste do Feed foi mantido.
  - Arquivo `.txt` renomeado para `.jpg`: aparece a mensagem de erro; a página continua funcionando.
  - `node --test tests/` continua `# fail 0`.

- [ ] **Step 8: Commit**

```bash
git add assets/js/avatar.js
git commit -m "feat(avatar): photo loading, live preview, tabs and drag/pinch/zoom"
```

---

### Task 4: Exportar — Compartilhar e Baixar

**Files:**
- Modify: `assets/js/avatar.js`

**Interfaces:**
- Consumes: `desenhar`, `foto`, `formatoAtual`, `atualizarBotoes` (Task 3); `FORMATOS[*].arquivo` (Task 1).

- [ ] **Step 1: Gerar o arquivo** — `function gerarArquivo() -> Promise<File>`: canvas novo no tamanho do formato atual, `desenhar`, `toBlob(..., 'image/png')`, `new File([blob], FORMATOS[formatoAtual].arquivo, {type:'image/png'})`.

- [ ] **Step 2: Baixar** — `<a download>` com `URL.createObjectURL(file)`, clique programático, `revokeObjectURL` após 1 s.

- [ ] **Step 3: Compartilhar** — no início, `podeCompartilhar = !!(navigator.canShare && navigator.canShare({files:[new File([''], 'x.png', {type:'image/png'})]}))`; se falso, `#avatar-compartilhar` fica `hidden`. Ao clicar: `navigator.share({files:[file], title:'2º Pedal Solidário', text:'Eu vou! 🚴 2º Pedal Solidário – 14/03/2027'})`. `AbortError` → silêncio. Outro erro → cai no Baixar e `#avatar-aviso` = "Não deu para compartilhar daqui. Baixamos a imagem para você postar." `atualizarBotoes` passa a cuidar também deste botão.

- [ ] **Step 4: Verificação**
  - Desktop (Chrome/Firefox): Compartilhar escondido; Baixar gera `pedal-solidario-eu-vou-feed.png` 1080×1080 e, na aba Stories, `pedal-solidario-eu-vou-stories.png` 1080×1920 (conferir dimensões no visualizador de imagens). A imagem baixada bate com a prévia.
  - Celular Android/iPhone: Compartilhar abre a folha do sistema com Instagram; cancelar não mostra erro.

- [ ] **Step 5: Commit**

```bash
git add assets/js/avatar.js
git commit -m "feat(avatar): share via Web Share API with download fallback"
```

---

### Task 5: Pontos de entrada e README

**Files:**
- Modify: `index.php:17-22`, `inscricao_recebida.php:80`, `README.md`

- [ ] **Step 1: Home** — no `.hero__acoes`, depois de "Ver rotas": `<a class="btn btn--contorno" href="avatar.php">Eu vou!</a>`.

- [ ] **Step 2: Inscrição recebida** — só no ramo de inscrição encontrada, antes de "Voltar ao início": um bloco `<div class="aviso">` com `<strong>Inscrição feita? Avise a galera!</strong>` e `<a class="btn btn--ouro btn--pequeno" href="avatar.php">Montar minha foto “Eu vou!”</a>`.

- [ ] **Step 3: README** — seção "Avatar Eu vou!": página `avatar.php`; molduras em `assets/img/avatar/` (PNG **com transparência**, 1080×1080 e 1080×1920, mesmos nomes para trocar); a foto não sai do aparelho; testes com `node --test tests/`.

- [ ] **Step 4: Verificar**

Run: `php -l index.php && php -l inscricao_recebida.php`
Expected: `No syntax errors detected` (×2)

Run: `curl -s localhost:8123/index.php | grep -c 'href="avatar.php"'`
Expected: `2` (menu + botão da home)

- [ ] **Step 5: Commit**

```bash
git add index.php inscricao_recebida.php README.md
git commit -m "feat(avatar): link Eu vou! from home and confirmation page; document"
```
