/* 2º Pedal Solidário – scripts do site */
(function () {
    'use strict';

    /* ---------- Menu mobile ---------- */
    var botao = document.querySelector('.menu-botao');
    var menu = document.getElementById('menu-principal');
    if (botao && menu) {
        botao.addEventListener('click', function () {
            var aberto = menu.classList.toggle('aberto');
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
    }

    /* ---------- Patrocinadores: faixa que anda sozinha ----------
       Usa a rolagem nativa do navegador (scrollLeft), que funciona bem em celulares.
       Pausa ao tocar/arrastar, ao passar o mouse (só em computador), ao focar com o teclado
       e quando a faixa sai da tela. Respeita "reduzir movimento" do aparelho. */
    (function () {
        var faixa = document.querySelector('.patrocinio__faixa');
        var lista = faixa && faixa.querySelector('.patrocinio__lista');
        if (!faixa || !lista || !lista.children.length) return;

        var reduzirMovimento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduzirMovimento) return; // fica como faixa parada, que dá para arrastar
        if (lista.children.length < 2) return; // um logo só: fica parado e centralizado

        var VELOCIDADE = 45;          // pixels por segundo
        var PAUSA_APOS_TOQUE = 3000;  // ms parado depois que a pessoa mexe
        var originais = Array.prototype.slice.call(lista.children);
        var largura = 0;              // largura de um conjunto completo de logos
        var pos = 0;                  // posição (decimal) da rolagem
        var ultimo = 0;               // último valor aplicado por nós (para distinguir do usuário)
        var pausaAte = 0;
        var manual = false, emFoco = false, sobMouse = false, tocando = false, visivel = true;
        var anterior = null;

        function pausarPor(ms) { pausaAte = Math.max(pausaAte, performance.now() + ms); }

        function montar() {
            Array.prototype.slice.call(lista.querySelectorAll('[data-clone]')).forEach(function (n) {
                n.parentNode.removeChild(n);
            });
            faixa.classList.add('rolando');
            largura = lista.getBoundingClientRect().width;
            if (!(largura > 0)) { faixa.classList.remove('rolando'); return; }

            // Cópias suficientes para nunca aparecer espaço vazio no fim da faixa
            var copias = Math.max(1, Math.ceil(faixa.clientWidth / largura));
            for (var c = 0; c < copias; c++) {
                originais.forEach(function (li) {
                    var clone = li.cloneNode(true);
                    clone.setAttribute('data-clone', '');
                    clone.setAttribute('aria-hidden', 'true');
                    Array.prototype.forEach.call(clone.querySelectorAll('a'), function (a) { a.setAttribute('tabindex', '-1'); });
                    Array.prototype.forEach.call(clone.querySelectorAll('img'), function (img) { img.setAttribute('alt', ''); });
                    lista.appendChild(clone);
                });
            }
            if (pos >= largura) pos = pos % largura;
            faixa.scrollLeft = pos;
            ultimo = faixa.scrollLeft;
        }

        function quadro(agora) {
            window.requestAnimationFrame(quadro);
            if (anterior === null) { anterior = agora; return; }
            var dt = Math.min(agora - anterior, 100);
            anterior = agora;
            if (!(largura > 0) || !visivel || document.hidden || manual || emFoco || sobMouse || tocando || agora < pausaAte) return;

            pos += VELOCIDADE * dt / 1000;
            if (pos >= largura) pos -= largura;     // volta ao início sem salto visível (o conteúdo se repete)
            faixa.scrollLeft = pos;
            ultimo = faixa.scrollLeft;
        }

        // Mexeu na faixa (arrastar, roda do mouse, setas do teclado): assume a posição e espera um pouco
        faixa.addEventListener('scroll', function () {
            if (Math.abs(faixa.scrollLeft - ultimo) > 2) {
                pos = faixa.scrollLeft;
                ultimo = faixa.scrollLeft;
                pausarPor(PAUSA_APOS_TOQUE);
            }
        }, { passive: true });

        faixa.addEventListener('touchstart', function () { tocando = true; }, { passive: true });
        faixa.addEventListener('touchend', function () { tocando = false; pausarPor(PAUSA_APOS_TOQUE); }, { passive: true });
        faixa.addEventListener('touchcancel', function () { tocando = false; pausarPor(PAUSA_APOS_TOQUE); }, { passive: true });

        // "Passar o mouse" só vale em computador: no celular o hover fica preso depois do toque
        if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            faixa.addEventListener('mouseenter', function () { sobMouse = true; });
            faixa.addEventListener('mouseleave', function () { sobMouse = false; });
        }

        // Foco só pausa quando é pelo teclado (tocar na faixa também a foca, mas não deve travar)
        faixa.addEventListener('focusin', function (ev) {
            try { emFoco = ev.target.matches(':focus-visible'); } catch (e) { emFoco = false; }
        });
        faixa.addEventListener('focusout', function () { emFoco = false; });

        // Economia de bateria: não anima quando a faixa está fora da tela
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entradas) {
                visivel = entradas[0].isIntersecting;
            }).observe(faixa);
        }

        // Botão opcional de pausa (se existir no HTML)
        var botaoPausa = document.querySelector('.patrocinio__pausa');
        if (botaoPausa) {
            botaoPausa.addEventListener('click', function () {
                manual = !manual;
                botaoPausa.setAttribute('aria-pressed', manual ? 'true' : 'false');
                botaoPausa.textContent = manual ? 'Retomar rolagem' : 'Pausar rolagem';
            });
        }

        // Girou o celular / mudou o tamanho da janela: refaz as cópias
        var temporizador;
        function aoRedimensionar() {
            window.clearTimeout(temporizador);
            temporizador = window.setTimeout(montar, 200);
        }
        window.addEventListener('resize', aoRedimensionar);
        window.addEventListener('orientationchange', aoRedimensionar);

        montar();
        window.requestAnimationFrame(quadro);
    })();

    /* ---------- Contagem regressiva ---------- */
    var contagem = document.querySelector('[data-contagem]');
    if (contagem) {
        var partes = contagem.getAttribute('data-contagem').split('-');
        var alvo = new Date(+partes[0], +partes[1] - 1, +partes[2]);
        var hoje = new Date();
        hoje.setHours(0, 0, 0, 0);
        var dias = Math.round((alvo - hoje) / 86400000);
        if (dias > 1) {
            contagem.textContent = 'Faltam ' + dias + ' dias para o pedal.';
        } else if (dias === 1) {
            contagem.textContent = 'É amanhã!';
        } else if (dias === 0) {
            contagem.textContent = 'É hoje! Boa pedalada.';
        }
    }

    /* ---------- Modais (<dialog>) ---------- */
    document.querySelectorAll('[data-modal]').forEach(function (gatilho) {
        gatilho.addEventListener('click', function () {
            var dlg = document.getElementById(gatilho.getAttribute('data-modal'));
            if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
        });
    });
    document.querySelectorAll('dialog.modal').forEach(function (dlg) {
        dlg.addEventListener('click', function (ev) {
            // Clique fora do conteúdo (no fundo escurecido) fecha o modal
            if (ev.target === dlg) dlg.close();
        });
        var fechar = dlg.querySelector('[data-fechar]');
        if (fechar) fechar.addEventListener('click', function () { dlg.close(); });
    });

    /* ---------- Formulário de inscrição ---------- */
    var form = document.getElementById('form-inscricao');
    if (!form) return;

    var cpfInput = document.getElementById('cpf');
    var telInputs = [document.getElementById('telefone'), document.getElementById('emerg_tel')];
    var nascimento = document.getElementById('nascimento');
    var avisoMenor = document.getElementById('aviso-menor');

    function soDigitos(v) { return v.replace(/\D/g, ''); }

    function mascaraCpf(v) {
        v = soDigitos(v).slice(0, 11);
        return v.replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    function mascaraTelefone(v) {
        v = soDigitos(v).slice(0, 11);
        if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        if (v.length > 6) return v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        if (v.length > 2) return v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
        return v;
    }

    function cpfValido(cpf) {
        cpf = soDigitos(cpf);
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        for (var t = 9; t < 11; t++) {
            var soma = 0;
            for (var i = 0; i < t; i++) soma += +cpf[i] * (t + 1 - i);
            var dig = ((soma * 10) % 11) % 10;
            if (dig !== +cpf[t]) return false;
        }
        return true;
    }

    cpfInput.addEventListener('input', function () {
        cpfInput.value = mascaraCpf(cpfInput.value);
    });

    telInputs.forEach(function (el) {
        if (el) el.addEventListener('input', function () { el.value = mascaraTelefone(el.value); });
    });

    function validarCpf() {
        var erro = cpfInput.parentNode.querySelector('.erro');
        var ok = cpfValido(cpfInput.value);
        cpfInput.classList.toggle('invalido', !ok && cpfInput.value !== '');
        if (erro) erro.textContent = (!ok && cpfInput.value !== '') ? 'CPF inválido. Confira os números.' : '';
        return ok;
    }
    cpfInput.addEventListener('blur', validarCpf);

    /* Aviso para menores de idade (idade no dia do evento) */
    nascimento.addEventListener('change', function () {
        if (!nascimento.value) { avisoMenor.hidden = true; return; }
        var ev = form.getAttribute('data-data-evento').split('-');
        var dataEvento = new Date(+ev[0], +ev[1] - 1, +ev[2]);
        var n = nascimento.value.split('-');
        var nasc = new Date(+n[0], +n[1] - 1, +n[2]);
        var idade = dataEvento.getFullYear() - nasc.getFullYear();
        var fezAniversario = (dataEvento.getMonth() > nasc.getMonth()) ||
            (dataEvento.getMonth() === nasc.getMonth() && dataEvento.getDate() >= nasc.getDate());
        if (!fezAniversario) idade--;
        avisoMenor.hidden = idade >= 18;
    });

    /* Resumo com categoria, camiseta e total */
    var resumoCat = document.getElementById('resumo-categoria');
    var resumoInsc = document.getElementById('resumo-inscricao');
    var resumoCam = document.getElementById('resumo-camiseta');
    var resumoVal = document.getElementById('resumo-valor');
    var queroCam = document.getElementById('quero-camiseta');
    var blocoTamanho = document.getElementById('camiseta-tamanho');
    var selTamanho = document.getElementById('tamanho');

    function formatarMoeda(n) {
        return 'R$ ' + n.toFixed(2).replace('.', ',');
    }

    var resumoDesc = document.getElementById('resumo-desconto');
    var resumoTaxa = document.getElementById('resumo-taxa');
    var taxaPct = parseFloat(form.getAttribute('data-taxa')) || 0;
    var campoCupom = document.getElementById('cupom');
    var botaoCupom = document.getElementById('aplicar-cupom');
    var msgCupom = document.getElementById('cupom-msg');
    var descontoAtual = 0;   // desconto do cupom já conferido (somente na inscrição)
    var cupomAplicado = '';  // código que gerou o desconto

    function atualizarResumo() {
        var radio = form.querySelector('input[name="categoria"]:checked');
        var valorInsc = radio ? parseFloat(radio.getAttribute('data-valor')) : 0;
        var valorCam = (queroCam && queroCam.checked) ? parseFloat(queroCam.getAttribute('data-valor')) : 0;
        var desconto = Math.min(descontoAtual, valorInsc);

        resumoCat.textContent = radio ? radio.getAttribute('data-nome') : 'Não escolhida';
        resumoInsc.textContent = formatarMoeda(valorInsc);
        resumoDesc.textContent = desconto > 0 ? '− ' + formatarMoeda(desconto) : formatarMoeda(0);
        resumoCam.textContent = valorCam > 0
            ? formatarMoeda(valorCam) + (selTamanho && selTamanho.value ? ' (' + selTamanho.value + ')' : '')
            : 'Não';
        // Taxa de administração: sobre o valor cheio da inscrição (sem desconto e sem camiseta), em centavos
        // Cupom que cobre 100% da inscrição também isenta a taxa
        var inscricaoGratis = valorInsc > 0 && (desconto + 0.005) >= valorInsc;
        var taxa = inscricaoGratis ? 0 : Math.round(Math.round(valorInsc * 100) * taxaPct / 100) / 100;
        resumoTaxa.textContent = formatarMoeda(taxa);
        resumoVal.textContent = formatarMoeda(valorInsc + taxa - desconto + valorCam);
    }

    /* Cupom: o servidor confere o código e devolve o desconto (e confere de novo ao enviar) */
    function limparCupom(mensagem) {
        descontoAtual = 0;
        cupomAplicado = '';
        msgCupom.textContent = mensagem || '';
        msgCupom.className = 'cupom__msg';
        atualizarResumo();
    }

    function aplicarCupom(silencioso) {
        if (!campoCupom || !botaoCupom) return;
        var codigo = campoCupom.value.trim();
        if (codigo === '') { limparCupom(''); return; }

        var radio = form.querySelector('input[name="categoria"]:checked');
        if (!radio) {
            limparCupom('Escolha a categoria para aplicar o cupom.');
            msgCupom.classList.add('cupom__msg--erro');
            return;
        }

        var dados = new FormData();
        dados.append('csrf', form.querySelector('input[name="csrf"]').value);
        dados.append('codigo', codigo);
        dados.append('categoria', radio.value);

        botaoCupom.disabled = true;
        fetch('cupom_validar.php', { method: 'POST', body: dados, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    descontoAtual = res.desconto;
                    cupomAplicado = codigo.toUpperCase();
                    msgCupom.textContent = res.mensagem;
                    msgCupom.className = 'cupom__msg cupom__msg--ok';
                } else {
                    descontoAtual = 0;
                    cupomAplicado = '';
                    msgCupom.textContent = res.mensagem || 'Não foi possível aplicar o cupom.';
                    msgCupom.className = 'cupom__msg cupom__msg--erro';
                }
                atualizarResumo();
            })
            .catch(function () {
                limparCupom('Não foi possível conferir o cupom agora. Ele será conferido ao enviar a inscrição.');
            })
            .then(function () { botaoCupom.disabled = false; });
    }

    if (botaoCupom) {
        botaoCupom.addEventListener('click', function () { aplicarCupom(false); });
        campoCupom.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); aplicarCupom(false); }
        });
        campoCupom.addEventListener('input', function () {
            // Alterou o código: o desconto anterior deixa de valer até aplicar de novo
            if (cupomAplicado && campoCupom.value.trim().toUpperCase() !== cupomAplicado) limparCupom('');
        });
    }

    form.querySelectorAll('input[name="categoria"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            atualizarResumo();
            if (campoCupom && campoCupom.value.trim() !== '') aplicarCupom(true);
        });
    });

    if (queroCam) {
        queroCam.addEventListener('change', function () {
            blocoTamanho.hidden = !queroCam.checked;
            selTamanho.required = queroCam.checked;
            if (!queroCam.checked) selTamanho.value = '';
            atualizarResumo();
            if (queroCam.checked) selTamanho.focus();
        });
        selTamanho.addEventListener('change', atualizarResumo);
    }
    if (queroCam) {
        // Estado inicial (importante quando a página recarrega com erros de validação)
        blocoTamanho.hidden = !queroCam.checked;
        selTamanho.required = queroCam.checked;
    }
    atualizarResumo();
    if (nascimento.value) nascimento.dispatchEvent(new Event('change'));

    if (campoCupom && campoCupom.value.trim() !== '' && form.querySelector('input[name="categoria"]:checked')) aplicarCupom(true);

    var caixaErros = document.getElementById('erros');
    if (caixaErros) caixaErros.focus();

    /* Validação antes de enviar */
    form.addEventListener('submit', function (evento) {
        var cpfOk = validarCpf();
        if (!form.checkValidity() || !cpfOk) {
            evento.preventDefault();
            form.reportValidity();
            if (!cpfOk) cpfInput.focus();
        }
        // A validação definitiva será feita no servidor (PHP) na etapa 2.
    });
})();
