/**
 * Avatar "Eu vou!": cálculos puros (sem DOM), usados por avatar.js e pelos testes.
 *
 * Estado de ajuste de um formato: { zoom, x, y }
 *   - x, y: canto superior esquerdo da foto, em pixels da imagem final (saída);
 *   - escala real da foto = escalaBase(foto, saida) * zoom.
 */
(function (raiz) {
    'use strict';

    var ZOOM_MAX = 4;
    var LADO_MAX_FOTO = 2160;

    var FORMATOS = {
        feed: {
            largura: 1080,
            altura: 1080,
            moldura: 'assets/img/avatar/moldura-1x1.png',
            arquivo: 'pedal-solidario-eu-vou-feed.png'
        },
        stories: {
            largura: 1080,
            altura: 1920,
            moldura: 'assets/img/avatar/moldura-9x16.png',
            arquivo: 'pedal-solidario-eu-vou-stories.png'
        }
    };

    /** Escala que faz a foto cobrir a saída inteira (zoom 1). */
    function escalaBase(foto, saida) {
        return Math.max(saida.w / foto.w, saida.h / foto.h);
    }

    function entre(v, min, max) {
        return Math.min(max, Math.max(min, v));
    }

    /** Zoom em [1, ZOOM_MAX] e posição tal que a foto sempre cubra a saída. */
    function limitar(estado, foto, saida) {
        var zoom = entre(estado.zoom, 1, ZOOM_MAX);
        var s = escalaBase(foto, saida) * zoom;
        return {
            zoom: zoom,
            x: Math.round(entre(estado.x, saida.w - foto.w * s, 0)),
            y: Math.round(entre(estado.y, saida.h - foto.h * s, 0))
        };
    }

    /** Zoom 1 com o centro da foto no centro do recorte (ou da saída). */
    function estadoInicial(foto, saida, buraco) {
        var s = escalaBase(foto, saida);
        var cx = buraco ? buraco.x + buraco.w / 2 : saida.w / 2;
        var cy = buraco ? buraco.y + buraco.h / 2 : saida.h / 2;
        return limitar({ zoom: 1, x: cx - (foto.w * s) / 2, y: cy - (foto.h * s) / 2 }, foto, saida);
    }

    function mover(estado, dx, dy, foto, saida) {
        return limitar({ zoom: estado.zoom, x: estado.x + dx, y: estado.y + dy }, foto, saida);
    }

    /** Muda o zoom mantendo fixo o ponto da foto que está sob `ponto` (coordenadas da saída). */
    function zoomEm(estado, novoZoom, ponto, foto, saida) {
        var base = escalaBase(foto, saida);
        var zoom = entre(novoZoom, 1, ZOOM_MAX);
        var sAntes = base * estado.zoom;
        var sDepois = base * zoom;
        var fx = (ponto.x - estado.x) / sAntes;
        var fy = (ponto.y - estado.y) / sAntes;
        return limitar({ zoom: zoom, x: ponto.x - fx * sDepois, y: ponto.y - fy * sDepois }, foto, saida);
    }

    /** Retângulo envolvente dos pixels com alfa < limiar (dados RGBA de getImageData). */
    function areaTransparente(rgba, largura, altura, limiar) {
        if (limiar === undefined) limiar = 10;
        var x0 = largura, y0 = altura, x1 = -1, y1 = -1;
        for (var y = 0; y < altura; y++) {
            for (var x = 0; x < largura; x++) {
                if (rgba[(y * largura + x) * 4 + 3] < limiar) {
                    if (x < x0) x0 = x;
                    if (x > x1) x1 = x;
                    if (y < y0) y0 = y;
                    if (y > y1) y1 = y;
                }
            }
        }
        return x1 < 0 ? null : { x: x0, y: y0, w: x1 - x0 + 1, h: y1 - y0 + 1 };
    }

    /** Reduz (nunca amplia) mantendo a proporção, com o lado maior <= maxLado. */
    function reduzirDimensoes(w, h, maxLado) {
        if (maxLado === undefined) maxLado = LADO_MAX_FOTO;
        var f = Math.min(1, maxLado / Math.max(w, h));
        return { w: Math.round(w * f), h: Math.round(h * f) };
    }

    var api = {
        ZOOM_MAX: ZOOM_MAX,
        LADO_MAX_FOTO: LADO_MAX_FOTO,
        FORMATOS: FORMATOS,
        escalaBase: escalaBase,
        limitar: limitar,
        estadoInicial: estadoInicial,
        mover: mover,
        zoomEm: zoomEm,
        areaTransparente: areaTransparente,
        reduzirDimensoes: reduzirDimensoes
    };

    raiz.AvatarGeometria = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
