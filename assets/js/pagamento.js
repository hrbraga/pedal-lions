/* 2º Pedal Solidário – modal de pagamento (Pix e cartão pelo Mercado Pago) */
(function () {
    'use strict';

    var modal = document.getElementById('pag-modal');
    if (!modal) {
        return;
    }
    var caixa = modal.querySelector('[role=dialog]');
    var codigo = modal.dataset.codigo;
    var expira = new Date(modal.dataset.expira);
    var total = parseFloat(modal.dataset.total);
    var chavePublica = modal.dataset.publicKey;
    var ultimoFoco = null;
    var consulta = null;
    var contagem = null;
    var brickCriado = false;
    var confirmado = false;

    /* ---------- Abrir e fechar ---------- */
    function focaveis() {
        return Array.prototype.filter.call(
            caixa.querySelectorAll('button, a[href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])'),
            function (el) { return !el.disabled && el.offsetParent !== null; }
        );
    }
    function abrir() {
        ultimoFoco = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('pag-aberto');
        var primeiro = caixa.querySelector('[role=tab][aria-selected=true]') || focaveis()[0];
        if (primeiro) {
            primeiro.focus();
        }
        atualizarContagem();
        contagem = setInterval(atualizarContagem, 30000);
        consultar();
        consulta = setInterval(consultar, 5000);
    }
    function fechar() {
        if (confirmado) {
            location.reload();
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('pag-aberto');
        clearInterval(consulta);
        clearInterval(contagem);
        if (ultimoFoco && ultimoFoco.focus) {
            ultimoFoco.focus();
        }
    }
    document.querySelectorAll('[data-pag-abrir]').forEach(function (b) { b.addEventListener('click', abrir); });
    modal.querySelectorAll('[data-pag-fechar]').forEach(function (b) { b.addEventListener('click', fechar); });
    modal.querySelectorAll('[data-pag-recarregar]').forEach(function (b) { b.addEventListener('click', function () { location.reload(); }); });
    document.addEventListener('keydown', function (e) {
        if (modal.hidden) {
            return;
        }
        if (e.key === 'Escape') {
            fechar();
        } else if (e.key === 'Tab') { // o foco não sai do modal
            var f = focaveis();
            if (!f.length) {
                return;
            }
            if (e.shiftKey && document.activeElement === f[0]) {
                e.preventDefault();
                f[f.length - 1].focus();
            } else if (!e.shiftKey && document.activeElement === f[f.length - 1]) {
                e.preventDefault();
                f[0].focus();
            }
        }
    });

    /* ---------- Abas ---------- */
    var abas = Array.prototype.slice.call(modal.querySelectorAll('[role=tab]'));
    function escolherAba(aba) {
        abas.forEach(function (a) {
            var ativa = a === aba;
            a.setAttribute('aria-selected', ativa ? 'true' : 'false');
            a.tabIndex = ativa ? 0 : -1;
            document.getElementById(a.getAttribute('aria-controls')).hidden = !ativa;
        });
        if (aba.id === 'pag-aba-cartao') {
            iniciarCartao();
        }
    }
    abas.forEach(function (a, i) {
        a.addEventListener('click', function () { escolherAba(a); });
        a.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                var prox = abas[(i + (e.key === 'ArrowRight' ? 1 : abas.length - 1)) % abas.length];
                prox.focus();
                escolherAba(prox);
            }
        });
    });

    /* ---------- Pix: contagem, copiar, tentar de novo ---------- */
    function atualizarContagem() {
        var alvo = modal.querySelector('[data-pag-contagem]');
        var resta = Math.max(0, expira.getTime() - Date.now());
        if (resta <= 0) {
            location.reload(); // expirou: a página mostra o aviso de prazo encerrado
            return;
        }
        var min = Math.floor(resta / 60000);
        if (alvo) {
            alvo.textContent = 'faltam ' + Math.floor(min / 60) + 'h ' + (min % 60) + 'min';
        }
    }
    var copiar = modal.querySelector('[data-pag-copiar]');
    if (copiar) {
        copiar.addEventListener('click', function () {
            var campo = modal.querySelector('.pag-copia input');
            var feito = function () {
                copiar.textContent = 'Copiado!';
                setTimeout(function () { copiar.textContent = 'Copiar'; }, 2500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(campo.value).then(feito, function () { campo.select(); document.execCommand('copy'); feito(); });
            } else {
                campo.select();
                document.execCommand('copy');
                feito();
            }
        });
    }
    var tentar = modal.querySelector('[data-pag-tentar]');
    if (tentar) {
        tentar.addEventListener('click', function () {
            tentar.disabled = true;
            tentar.textContent = 'Gerando…';
            var corpo = new URLSearchParams();
            corpo.set('c', codigo);
            fetch('pagamento_pix.php', { method: 'POST', body: corpo })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok && d.pix) {
                        modal.querySelector('.pag-qr').src = 'data:image/png;base64,' + d.pix.qr_base64;
                        modal.querySelector('.pag-copia input').value = d.pix.copia_cola;
                        modal.querySelector('[data-pag-pix]').hidden = false;
                        modal.querySelector('[data-pag-sem-pix]').hidden = true;
                    } else {
                        tentar.textContent = 'Tentar de novo';
                        tentar.disabled = false;
                    }
                })
                .catch(function () { tentar.textContent = 'Tentar de novo'; tentar.disabled = false; });
        });
    }

    /* ---------- Confirmação: pergunta ao site a cada 5 s ---------- */
    function mostrarConfirmado() {
        confirmado = true;
        clearInterval(consulta);
        clearInterval(contagem);
        modal.querySelector('[data-pag-etapa=pagar]').hidden = true;
        modal.querySelector('[data-pag-etapa=ok]').hidden = false;
        var titulo = modal.querySelector('.pag-ok h2');
        titulo.tabIndex = -1;
        titulo.focus();
    }
    function consultar() {
        fetch('pagamento_status.php?c=' + encodeURIComponent(codigo), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.status === 'pago') {
                    mostrarConfirmado();
                } else if (d.status === 'expirado' || d.status === 'cancelado') {
                    location.reload();
                }
            })
            .catch(function () { /* sem rede: tenta de novo na próxima */ });
    }

    /* ---------- Cartão: formulário do Mercado Pago (carregado só quando a aba abre) ---------- */
    function mensagemCartao(texto, erro) {
        var p = modal.querySelector('[data-pag-cartao-msg]');
        p.textContent = texto;
        p.hidden = !texto;
        p.classList.toggle('pag-msg--erro', !!erro);
    }
    function carregarSdk(pronto) {
        if (window.MercadoPago) {
            pronto();
            return;
        }
        var s = document.createElement('script');
        s.src = 'https://sdk.mercadopago.com/js/v2';
        s.onload = pronto;
        s.onerror = function () { mensagemCartao('Não foi possível carregar o pagamento com cartão. Confira sua conexão ou pague com Pix.', true); };
        document.head.appendChild(s);
    }
    function iniciarCartao() {
        if (brickCriado) {
            return;
        }
        brickCriado = true;
        carregarSdk(function () {
            try {
                var mp = new window.MercadoPago(chavePublica, { locale: 'pt-BR' });
                mp.bricks().create('cardPayment', 'pag-cartao-brick', {
                    initialization: { amount: total },
                    customization: {
                        paymentMethods: { maxInstallments: 2, minInstallments: 1 },
                        visual: { style: { theme: 'default', customVariables: { baseColor: '#0A1E4D' } } }
                    },
                    callbacks: {
                        onReady: function () {},
                        onSubmit: function (dados) {
                            mensagemCartao('', false);
                            dados.codigo = codigo;
                            return fetch('pagamento_cartao.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(dados)
                            })
                                .then(function (r) { return r.json(); })
                                .then(function (d) {
                                    if (d.status === 'approved') {
                                        mostrarConfirmado();
                                    } else {
                                        mensagemCartao(d.mensagem || 'Pagamento recusado. Confira os dados ou tente outro cartão.', d.status !== 'in_process');
                                    }
                                })
                                .catch(function () {
                                    mensagemCartao('Não conseguimos confirmar o pagamento agora. Aguarde alguns minutos antes de tentar de novo: se ele foi aprovado, esta tela e o seu e-mail confirmam sozinhos.', true);
                                });
                        },
                        onError: function (erro) {
                            if (window.console) {
                                console.warn('Mercado Pago:', erro);
                            }
                        }
                    }
                });
            } catch (e) {
                mensagemCartao('Não foi possível carregar o pagamento com cartão. Pague com Pix ou tente mais tarde.', true);
            }
        });
    }

    if (modal.dataset.abrir === '1') {
        abrir();
    }
})();
