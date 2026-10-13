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

    function atualizarResumo() {
        var radio = form.querySelector('input[name="categoria"]:checked');
        var valorInsc = radio ? parseFloat(radio.getAttribute('data-valor')) : 0;
        var valorCam = (queroCam && queroCam.checked) ? parseFloat(queroCam.getAttribute('data-valor')) : 0;

        resumoCat.textContent = radio ? radio.getAttribute('data-nome') : 'Não escolhida';
        resumoInsc.textContent = formatarMoeda(valorInsc);
        resumoCam.textContent = valorCam > 0
            ? formatarMoeda(valorCam) + (selTamanho && selTamanho.value ? ' (' + selTamanho.value + ')' : '')
            : 'Não';
        resumoVal.textContent = formatarMoeda(valorInsc + valorCam);
    }

    form.querySelectorAll('input[name="categoria"]').forEach(function (radio) {
        radio.addEventListener('change', atualizarResumo);
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
    atualizarResumo();

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
