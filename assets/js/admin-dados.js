/**
 * Painel → Dados do Evento: listas editáveis (adicionar, remover, subir, descer)
 * e aviso ao sair da página com alterações não salvas.
 */
(function () {
    'use strict';

    var form = document.querySelector('form[data-dados]');
    if (!form) return;

    var alterado = false;
    var contador = 0;

    function marcar() { alterado = true; }
    form.addEventListener('input', marcar);
    form.addEventListener('change', marcar);

    window.addEventListener('beforeunload', function (ev) {
        if (!alterado) return;
        ev.preventDefault();
        ev.returnValue = '';
    });

    /* Os itens de cada lista são enviados na ordem da tela: lista[0], lista[1], ... */
    function renumerar() {
        Array.prototype.forEach.call(form.querySelectorAll('[data-lista]'), function (lista) {
            var prefixo = lista.getAttribute('data-prefixo');
            Array.prototype.forEach.call(lista.children, function (item, i) {
                Array.prototype.forEach.call(item.querySelectorAll('[name]'), function (campo) {
                    if (campo.name.indexOf(prefixo + '[') !== 0) return;
                    var resto = campo.name.slice(prefixo.length).replace(/^\[[^\]]*\]/, '[' + i + ']');
                    campo.name = prefixo + resto;
                });
            });
        });
    }

    var botaoSalvar = form.querySelector('[data-salvar]');

    form.addEventListener('submit', function (ev) {
        if (ev.defaultPrevented) return; // fotos ainda sendo preparadas
        // Clique duplo: o segundo envio veria a versão que o primeiro acabou de gravar e acusaria um conflito falso
        if (form.getAttribute('data-enviando')) {
            ev.preventDefault();
            return;
        }
        form.setAttribute('data-enviando', '1');
        if (botaoSalvar) {
            botaoSalvar.disabled = true;
            botaoSalvar.textContent = 'Salvando…';
        }
        renumerar();
        alterado = false;
    });

    // Voltando pelo botão "voltar" do navegador, a página pode vir da memória com o botão travado
    window.addEventListener('pageshow', function () {
        form.removeAttribute('data-enviando');
        if (botaoSalvar) {
            botaoSalvar.disabled = false;
            botaoSalvar.textContent = 'Salvar alterações';
        }
    });

    /* Logo do patrocinador: mostra o arquivo escolhido antes de salvar e avisa se passar de 2 MB */
    var LOGO_MAX = 2 * 1024 * 1024;
    form.addEventListener('change', function (ev) {
        var campo = ev.target;
        if (!campo.matches || !campo.matches('[data-logo-arquivo]')) return;
        var item = campo.closest('[data-item]');
        var previa = item && item.querySelector('[data-logo-previa]');
        var aviso = item && item.querySelector('[data-logo-aviso]');
        var arquivo = campo.files && campo.files[0];
        if (!arquivo || !previa) return;
        if (previa.src && previa.src.indexOf('blob:') === 0) URL.revokeObjectURL(previa.src);
        previa.src = URL.createObjectURL(arquivo);
        previa.hidden = false;
        if (aviso) {
            var grande = arquivo.size > LOGO_MAX;
            aviso.textContent = grande ? 'O logo passa de 2 MB.' : '';
            aviso.hidden = !grande;
        }
    });

    /* Foto do carrossel "Onde o valor arrecadado será usado?": reduz no próprio navegador antes de enviar
       (lado maior até 1600 px, JPEG), para aceitar a foto original do celular e deixar a página inicial leve.
       Navegador sem suporte: envia a original (o servidor aceita até 8 MB). */
    var FOTO_LADO = 1600, FOTO_QUALIDADE = 0.82, FOTO_MAX = 8 * 1024 * 1024;
    var preparando = 0;

    function reduzirFoto(arquivo) {
        return new Promise(function (resolve) {
            if (!window.DataTransfer || !window.File || !HTMLCanvasElement.prototype.toBlob) { resolve(null); return; }
            var url = URL.createObjectURL(arquivo);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                var lado = Math.max(img.naturalWidth, img.naturalHeight);
                if (!lado) { resolve(null); return; }
                var escala = Math.min(1, FOTO_LADO / lado);
                var tela = document.createElement('canvas');
                tela.width = Math.round(img.naturalWidth * escala);
                tela.height = Math.round(img.naturalHeight * escala);
                var ctx = tela.getContext('2d');
                ctx.fillStyle = '#fff'; // PNG transparente vira fundo branco no JPEG
                ctx.fillRect(0, 0, tela.width, tela.height);
                ctx.drawImage(img, 0, 0, tela.width, tela.height);
                tela.toBlob(function (blob) {
                    if (!blob) { resolve(null); return; }
                    var nome = (arquivo.name || 'foto').replace(/\.[^.]*$/, '') + '.jpg';
                    resolve(new File([blob], nome, { type: 'image/jpeg', lastModified: Date.now() }));
                }, 'image/jpeg', FOTO_QUALIDADE);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(null); };
            img.src = url;
        });
    }

    function atualizarBotaoSalvar() {
        if (!botaoSalvar || form.getAttribute('data-enviando')) return;
        botaoSalvar.disabled = preparando > 0;
        botaoSalvar.textContent = preparando > 0 ? 'Preparando fotos…' : 'Salvar alterações';
    }

    form.addEventListener('change', function (ev) {
        var campo = ev.target;
        if (!campo.matches || !campo.matches('[data-foto-arquivo]')) return;
        var item = campo.closest('[data-item]');
        var previa = item && item.querySelector('[data-logo-previa]');
        var aviso = item && item.querySelector('[data-logo-aviso]');
        var arquivo = campo.files && campo.files[0];
        if (!arquivo) return;
        if (previa) {
            if (previa.src && previa.src.indexOf('blob:') === 0) URL.revokeObjectURL(previa.src);
            previa.src = URL.createObjectURL(arquivo);
            previa.hidden = false;
        }
        preparando++;
        atualizarBotaoSalvar();
        reduzirFoto(arquivo).then(function (menor) {
            if (menor && menor.size < arquivo.size && campo.files && campo.files[0] === arquivo) {
                try {
                    var dt = new DataTransfer();
                    dt.items.add(menor);
                    campo.files = dt.files;
                } catch (e) { /* fica a original */ }
            }
            var final = campo.files && campo.files[0];
            if (aviso) {
                var grande = !!final && final.size > FOTO_MAX;
                aviso.textContent = grande ? 'A foto passa de 8 MB. Escolha uma foto menor.' : '';
                aviso.hidden = !grande;
            }
        }).then(function () {
            preparando--;
            atualizarBotaoSalvar();
        });
    });

    form.addEventListener('submit', function (ev) {
        if (preparando > 0) ev.preventDefault(); // ainda reduzindo: o botão volta a valer em instantes
    }, true);

    form.addEventListener('click', function (ev) {
        var botao = ev.target.closest('[data-adicionar], [data-acao]');
        if (!botao) return;

        if (botao.hasAttribute('data-adicionar')) {
            var nome = botao.getAttribute('data-adicionar');
            var lista = form.querySelector('[data-lista="' + nome + '"]');
            var modelo = form.querySelector('template[data-modelo="' + nome + '"]');
            lista.insertAdjacentHTML('beforeend', modelo.innerHTML.replace(/__i__/g, 'novo' + (++contador)));
            var primeiro = lista.lastElementChild.querySelector('input:not([type=hidden]), textarea');
            if (primeiro) primeiro.focus();
            marcar();
            return;
        }

        var item = botao.closest('[data-item]');
        var acao = botao.getAttribute('data-acao');
        if (acao === 'remover') {
            item.remove();
        } else if (acao === 'subir' && item.previousElementSibling) {
            item.parentNode.insertBefore(item, item.previousElementSibling);
            botao.focus();
        } else if (acao === 'descer' && item.nextElementSibling) {
            item.parentNode.insertBefore(item.nextElementSibling, item);
            botao.focus();
        }
        marcar();
    });
})();
