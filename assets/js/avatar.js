/**
 * Avatar "Eu vou!": monta a foto da pessoa atrás da moldura, no navegador.
 * A foto nunca é enviada ao servidor. Cálculos em avatar-geometria.js.
 */
(function () {
    'use strict';

    var G = window.AvatarGeometria;
    var raiz = document.getElementById('avatar');
    if (!G || !raiz) return;

    var canvas = document.getElementById('avatar-canvas');
    var ctx = canvas.getContext('2d');
    var inputArquivo = document.getElementById('avatar-arquivo');
    var zoomRange = document.getElementById('avatar-zoom');
    var btnCentralizar = document.getElementById('avatar-centralizar');
    var btnBaixar = document.getElementById('avatar-baixar');
    var btnCompartilhar = document.getElementById('avatar-compartilhar');
    var aviso = document.getElementById('avatar-aviso');
    var abas = raiz.querySelectorAll('[role="tab"]');

    var MSG_ERRO_FOTO = 'Não conseguimos abrir essa imagem. Tente uma foto em JPG ou PNG.';
    var MSG_ERRO_MOLDURA = 'Não foi possível carregar a moldura. Recarregue a página.';
    var MSG_TOQUE_DE_NOVO = 'Imagem pronta! Toque em Compartilhar de novo.';
    var MSG_SEM_COMPARTILHAR ='Não deu para compartilhar daqui. Baixamos a imagem para você postar.';
    var TEXTO_COMPARTILHAR = 'Eu vou! 🚴 2º Pedal Solidário – 14/03/2027';

    var podeCompartilhar = false;
    try {
        podeCompartilhar = !!(navigator.canShare &&
            navigator.canShare({ files: [new File([''], 'x.png', { type: 'image/png' })] }));
    } catch (err) {
        podeCompartilhar = false;
    }
    btnCompartilhar.hidden = !podeCompartilhar;

    var formatoAtual = 'feed';
    var foto = null;              // canvas com a foto já reduzida
    var estados = { feed: null, stories: null };
    var molduras = {};            // formato -> HTMLImageElement carregada
    var buracos = {};             // formato -> {x,y,w,h} do recorte transparente
    var semRecorte = {};          // formato -> true se o estado foi criado antes da moldura carregar
    var canvasExport = document.createElement('canvas'); // reaproveitado (limite de memória do iOS)

    function saidaDe(formato) {
        var f = G.FORMATOS[formato];
        return { w: f.largura, h: f.altura };
    }

    function dimsFoto() {
        return { w: foto.width, h: foto.height };
    }

    /** Estado do formato, criado na primeira vez que é usado com a foto atual. */
    function estadoDe(formato) {
        if (!estados[formato]) {
            estados[formato] = G.estadoInicial(dimsFoto(), saidaDe(formato), buracos[formato] || null);
            semRecorte[formato] = !molduras[formato];
        }
        return estados[formato];
    }

    /** Pinta fundo + foto (se houver) + moldura num contexto do tamanho do formato. */
    function desenhar(c, formato) {
        var s = saidaDe(formato);
        c.clearRect(0, 0, s.w, s.h);
        if (foto) {
            var e = estadoDe(formato);
            var escala = G.escalaBase(dimsFoto(), s) * e.zoom;
            c.fillStyle = '#0A1E4D';
            c.fillRect(0, 0, s.w, s.h);
            c.imageSmoothingEnabled = true;
            c.imageSmoothingQuality = 'high';
            c.drawImage(foto, e.x, e.y, foto.width * escala, foto.height * escala);
        }
        if (molduras[formato]) c.drawImage(molduras[formato], 0, 0, s.w, s.h);
    }

    var pedido = 0;
    function redesenhar() {
        prepararArquivoEmSeguida();
        if (pedido) return;
        pedido = requestAnimationFrame(function () {
            pedido = 0;
            desenhar(ctx, formatoAtual);
        });
    }

    /*
     * Arquivo final pré-gerado: o Safari do iPhone só aceita navigator.share() logo após o toque,
     * e gerar o PNG é assíncrono. Então o PNG é refeito ~0,4 s depois de cada ajuste e o clique
     * em Compartilhar já encontra o arquivo pronto.
     */
    var arquivoPronto = null;
    var versaoArquivo = 0;
    var timerArquivo = 0;
    function prepararArquivoEmSeguida() {
        arquivoPronto = null;
        versaoArquivo++;
        clearTimeout(timerArquivo);
        if (!podeExportar()) return;
        var versao = versaoArquivo;
        timerArquivo = setTimeout(function () {
            gerarArquivo().then(function (arquivo) {
                if (versao === versaoArquivo) arquivoPronto = arquivo;
            }, function () { /* gera de novo no clique */ });
        }, 400);
    }

    /** Só exporta com foto e com a moldura do formato já carregada (nunca sai imagem sem moldura). */
    function podeExportar() {
        return !!foto && !!molduras[formatoAtual];
    }

    function atualizarBotoes() {
        var semFoto = !foto;
        zoomRange.disabled = semFoto;
        btnCentralizar.disabled = semFoto;
        btnBaixar.disabled = !podeExportar();
        btnCompartilhar.disabled = !podeExportar();
        zoomRange.value = semFoto ? 1 : estadoDe(formatoAtual).zoom;
    }

    function definirEstado(novo) {
        estados[formatoAtual] = novo;
        zoomRange.value = novo.zoom;
        redesenhar();
    }

    /* ---------- Molduras ---------- */
    Object.keys(G.FORMATOS).forEach(function (formato) {
        var img = new Image();
        img.onload = function () {
            var s = saidaDe(formato);
            var aux = document.createElement('canvas');
            aux.width = s.w;
            aux.height = s.h;
            var actx = aux.getContext('2d');
            actx.drawImage(img, 0, 0, s.w, s.h);
            try {
                buracos[formato] = G.areaTransparente(actx.getImageData(0, 0, s.w, s.h).data, s.w, s.h);
            } catch (err) {
                buracos[formato] = null; // sem acesso aos pixels: centraliza na imagem toda
            }
            aux.width = aux.height = 0; // libera a memória do canvas auxiliar
            molduras[formato] = img;
            // a foto chegou antes da moldura: refaz o enquadramento inicial, agora no recorte
            if (semRecorte[formato]) {
                estados[formato] = null;
                semRecorte[formato] = false;
            }
            if (formato === formatoAtual) {
                atualizarBotoes();
                redesenhar();
            }
        };
        img.onerror = function () {
            aviso.textContent = MSG_ERRO_MOLDURA;
        };
        img.src = G.FORMATOS[formato].moldura;
    });

    /* ---------- Carregar a foto ---------- */
    function abrirComoImg(arquivo) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(arquivo);
            var img = new Image();
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('imagem inválida')); };
            img.src = url;
        });
    }

    function abrirImagem(arquivo) {
        if (typeof createImageBitmap === 'function') {
            return createImageBitmap(arquivo, { imageOrientation: 'from-image' })
                .catch(function () { return abrirComoImg(arquivo); });
        }
        return abrirComoImg(arquivo);
    }

    function reduzir(origem) {
        var w = origem.naturalWidth || origem.width;
        var h = origem.naturalHeight || origem.height;
        if (!w || !h) throw new Error('imagem vazia');
        var d = G.reduzirDimensoes(w, h);
        var c = document.createElement('canvas');
        c.width = d.w;
        c.height = d.h;
        var cc = c.getContext('2d');
        cc.imageSmoothingEnabled = true;
        cc.imageSmoothingQuality = 'high';
        cc.drawImage(origem, 0, 0, d.w, d.h);
        if (typeof origem.close === 'function') origem.close();
        return c;
    }

    inputArquivo.addEventListener('change', function () {
        var arquivo = inputArquivo.files && inputArquivo.files[0];
        inputArquivo.value = ''; // permite escolher o mesmo arquivo de novo
        if (!arquivo) return;
        aviso.textContent = '';
        abrirImagem(arquivo)
            .then(function (origem) {
                var nova = reduzir(origem);
                if (foto) foto.width = foto.height = 0; // libera a foto anterior
                foto = nova;
                estados = { feed: null, stories: null };
                semRecorte = {};
                atualizarBotoes();
                redesenhar();
                canvas.focus({ preventScroll: true });
            })
            .catch(function () {
                aviso.textContent = MSG_ERRO_FOTO;
            });
    });

    /* ---------- Abas ---------- */
    function trocarFormato(formato) {
        formatoAtual = formato;
        raiz.setAttribute('data-formato', formato);
        Array.prototype.forEach.call(abas, function (aba) {
            aba.setAttribute('aria-selected', aba.getAttribute('data-formato') === formato ? 'true' : 'false');
        });
        var s = saidaDe(formato);
        canvas.width = s.w;
        canvas.height = s.h;
        atualizarBotoes();
        redesenhar();
    }

    Array.prototype.forEach.call(abas, function (aba) {
        aba.addEventListener('click', function () { trocarFormato(aba.getAttribute('data-formato')); });
    });

    /* ---------- Ajuste: arrastar, pinça, roda, teclado, controle ---------- */
    function fatorTela() {
        return canvas.width / canvas.getBoundingClientRect().width;
    }

    function pontoNaSaida(clientX, clientY) {
        var r = canvas.getBoundingClientRect();
        var f = canvas.width / r.width;
        return { x: (clientX - r.left) * f, y: (clientY - r.top) * f };
    }

    var ponteiros = {};   // pointerId -> {x, y} (coordenadas da tela)
    var pinca = null;     // {dist, zoom} no início do gesto de dois dedos

    function listaPonteiros() {
        return Object.keys(ponteiros).map(function (k) { return ponteiros[k]; });
    }

    function iniciarPinca() {
        var p = listaPonteiros();
        pinca = p.length === 2
            ? { dist: Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y) || 1, zoom: estadoDe(formatoAtual).zoom }
            : null;
    }

    canvas.addEventListener('pointerdown', function (ev) {
        if (!foto) return;
        canvas.setPointerCapture(ev.pointerId);
        ponteiros[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
        iniciarPinca();
    });

    canvas.addEventListener('pointermove', function (ev) {
        if (!foto || !ponteiros[ev.pointerId]) return;
        var antes = listaPonteiros();
        var meioAntes = antes.length === 2
            ? { x: (antes[0].x + antes[1].x) / 2, y: (antes[0].y + antes[1].y) / 2 }
            : null;
        var anterior = ponteiros[ev.pointerId];
        ponteiros[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
        var f = fatorTela();
        var s = saidaDe(formatoAtual);
        var e = estadoDe(formatoAtual);

        if (pinca && meioAntes) {
            var p = listaPonteiros();
            var dist = Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
            var meio = { x: (p[0].x + p[1].x) / 2, y: (p[0].y + p[1].y) / 2 };
            e = G.zoomEm(e, pinca.zoom * dist / pinca.dist, pontoNaSaida(meio.x, meio.y), dimsFoto(), s);
            e = G.mover(e, (meio.x - meioAntes.x) * f, (meio.y - meioAntes.y) * f, dimsFoto(), s);
        } else {
            e = G.mover(e, (ev.clientX - anterior.x) * f, (ev.clientY - anterior.y) * f, dimsFoto(), s);
        }
        definirEstado(e);
    });

    function soltar(ev) {
        delete ponteiros[ev.pointerId];
        iniciarPinca();
    }
    canvas.addEventListener('pointerup', soltar);
    canvas.addEventListener('pointercancel', soltar);

    canvas.addEventListener('wheel', function (ev) {
        if (!foto) return;
        ev.preventDefault();
        var e = estadoDe(formatoAtual);
        var novo = e.zoom * Math.exp(-ev.deltaY * 0.0015);
        definirEstado(G.zoomEm(e, novo, pontoNaSaida(ev.clientX, ev.clientY), dimsFoto(), saidaDe(formatoAtual)));
    }, { passive: false });

    function zoomNoCentro(novo) {
        var s = saidaDe(formatoAtual);
        definirEstado(G.zoomEm(estadoDe(formatoAtual), novo, { x: s.w / 2, y: s.h / 2 }, dimsFoto(), s));
    }

    zoomRange.addEventListener('input', function () {
        if (foto) zoomNoCentro(parseFloat(zoomRange.value));
    });

    canvas.addEventListener('keydown', function (ev) {
        if (!foto) return;
        var passo = 20;
        var desloc = { ArrowLeft: [-passo, 0], ArrowRight: [passo, 0], ArrowUp: [0, -passo], ArrowDown: [0, passo] }[ev.key];
        var e = estadoDe(formatoAtual);
        if (desloc) {
            definirEstado(G.mover(e, desloc[0], desloc[1], dimsFoto(), saidaDe(formatoAtual)));
        } else if (ev.key === '+' || ev.key === '=') {
            zoomNoCentro(e.zoom * 1.1);
        } else if (ev.key === '-' || ev.key === '_') {
            zoomNoCentro(e.zoom / 1.1);
        } else {
            return;
        }
        ev.preventDefault();
    });

    btnCentralizar.addEventListener('click', function () {
        if (!foto) return;
        definirEstado(G.estadoInicial(dimsFoto(), saidaDe(formatoAtual), buracos[formatoAtual] || null));
    });

    /* ---------- Exportar: compartilhar e baixar ---------- */
    function gerarArquivo() {
        var formato = formatoAtual;
        var s = saidaDe(formato);
        var c = canvasExport;
        c.width = s.w;
        c.height = s.h;
        desenhar(c.getContext('2d'), formato);
        // toBlob copia o conteúdo na hora da chamada, então reaproveitar o canvas é seguro
        return new Promise(function (resolve, reject) {
            c.toBlob(function (blob) {
                if (!blob) { reject(new Error('falha ao gerar a imagem')); return; }
                resolve(new File([blob], G.FORMATOS[formato].arquivo, { type: 'image/png' }));
            }, 'image/png');
        });
    }

    function baixar(arquivo) {
        var url = URL.createObjectURL(arquivo);
        var a = document.createElement('a');
        a.href = url;
        a.download = arquivo.name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        // o iOS pede confirmação do download; revogar cedo demais faz o download falhar
        setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    }

    /**
     * Entrega o arquivo final: na hora, se já estiver pré-gerado (mantém o "toque" válido no iPhone),
     * senão gera agora e guarda para o próximo toque. `usar(arquivo, tardio)`.
     */
    function obterArquivo(usar) {
        if (arquivoPronto) {
            usar(arquivoPronto, false);
            return;
        }
        var versao = versaoArquivo;
        gerarArquivo().then(function (arquivo) {
            if (versao === versaoArquivo) arquivoPronto = arquivo;
            usar(arquivo, true);
        });
    }

    function compartilhar(arquivo, tardio) {
        navigator.share({ files: [arquivo], title: '2º Pedal Solidário', text: TEXTO_COMPARTILHAR })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return; // a pessoa cancelou
                if (tardio && err && err.name === 'NotAllowedError') {
                    // o toque "expirou" enquanto a imagem era gerada; agora ela já está pronta
                    aviso.textContent = MSG_TOQUE_DE_NOVO;
                    return;
                }
                baixar(arquivo);
                aviso.textContent = MSG_SEM_COMPARTILHAR;
            });
    }

    btnBaixar.addEventListener('click', function () {
        if (!podeExportar()) return;
        aviso.textContent = '';
        obterArquivo(baixar);
    });

    btnCompartilhar.addEventListener('click', function () {
        if (!podeExportar()) return;
        aviso.textContent = '';
        obterArquivo(compartilhar);
    });

    atualizarBotoes();
    redesenhar();
})();
