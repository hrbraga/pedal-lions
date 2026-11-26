// Testes do módulo puro do avatar "Eu vou!". Rode com: node --test (na raiz do projeto)
const test = require('node:test');
const assert = require('node:assert/strict');
const G = require('../assets/js/avatar-geometria.js');

test('escalaBase cobre a saída', () => {
    assert.equal(G.escalaBase({ w: 2000, h: 1000 }, { w: 1080, h: 1080 }), 1.08); // limitado pela altura
    assert.equal(G.escalaBase({ w: 1000, h: 2000 }, { w: 1080, h: 1920 }), 1.08); // limitado pela largura
});

test('limitar mantém a foto cobrindo a saída e o zoom em [1,4]', () => {
    const f = { w: 1000, h: 1000 }, s = { w: 1080, h: 1080 }; // escalaBase 1.08 → 1080 px no zoom 1
    assert.deepEqual(G.limitar({ zoom: 0.5, x: 50, y: -999 }, f, s), { zoom: 1, x: 0, y: 0 });
    assert.deepEqual(G.limitar({ zoom: 9, x: -99999, y: 10 }, f, s), { zoom: 4, x: 1080 - 4320, y: 0 });
});

test('estadoInicial centraliza no buraco', () => {
    const f = { w: 2000, h: 1000 }, s = { w: 1080, h: 1080 }; // foto vira 2160×1080
    assert.deepEqual(G.estadoInicial(f, s, { x: 540, y: 0, w: 540, h: 1080 }), { zoom: 1, x: 810 - 1080, y: 0 });
    assert.deepEqual(G.estadoInicial(f, s, null), { zoom: 1, x: -540, y: 0 });
});

test('mover soma o deslocamento e respeita limites', () => {
    const f = { w: 2000, h: 1000 }, s = { w: 1080, h: 1080 };
    assert.deepEqual(G.mover({ zoom: 1, x: -540, y: 0 }, 100, 50, f, s), { zoom: 1, x: -440, y: 0 });
    assert.deepEqual(G.mover({ zoom: 1, x: -540, y: 0 }, 5000, 0, f, s), { zoom: 1, x: 0, y: 0 });
});

test('zoomEm mantém o ponto sob o dedo', () => {
    const f = { w: 1000, h: 1000 }, s = { w: 1080, h: 1080 };
    const r = G.zoomEm({ zoom: 1, x: 0, y: 0 }, 2, { x: 540, y: 540 }, f, s);
    assert.deepEqual(r, { zoom: 2, x: -540, y: -540 });
});

test('areaTransparente acha o retângulo do recorte', () => {
    const w = 4, h = 3, px = new Uint8ClampedArray(w * h * 4).fill(255);
    px[(1 * w + 1) * 4 + 3] = 0;
    px[(2 * w + 2) * 4 + 3] = 5; // (1,1) e (2,2) transparentes
    assert.deepEqual(G.areaTransparente(px, w, h), { x: 1, y: 1, w: 2, h: 2 });
    assert.equal(G.areaTransparente(new Uint8ClampedArray(16).fill(255), 2, 2), null);
});

test('reduzirDimensoes limita o lado maior a 2160 sem ampliar', () => {
    assert.deepEqual(G.reduzirDimensoes(8064, 6048), { w: 2160, h: 1620 });
    assert.deepEqual(G.reduzirDimensoes(3000, 4000), { w: 1620, h: 2160 });
    assert.deepEqual(G.reduzirDimensoes(800, 600), { w: 800, h: 600 });
});

test('FORMATOS tem os tamanhos e nomes do spec', () => {
    assert.deepEqual([G.FORMATOS.feed.largura, G.FORMATOS.feed.altura], [1080, 1080]);
    assert.deepEqual([G.FORMATOS.stories.largura, G.FORMATOS.stories.altura], [1080, 1920]);
    assert.equal(G.FORMATOS.feed.arquivo, 'pedal-solidario-eu-vou-feed.png');
    assert.equal(G.FORMATOS.stories.arquivo, 'pedal-solidario-eu-vou-stories.png');
    assert.equal(G.FORMATOS.feed.moldura, 'assets/img/avatar/moldura-1x1.png');
    assert.equal(G.FORMATOS.stories.moldura, 'assets/img/avatar/moldura-9x16.png');
});

test('mover acumula deslocamentos menores que meio pixel (arraste lento)', () => {
    const f = { w: 2000, h: 1000 }, s = { w: 1080, h: 1080 };
    let e = { zoom: 1, x: -540, y: 0 };
    for (let i = 0; i < 10; i++) e = G.mover(e, 0.3, 0, f, s);
    assert.ok(Math.abs(e.x - -537) < 1e-9, `x = ${e.x}`);
});
