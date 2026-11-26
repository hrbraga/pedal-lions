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

    form.addEventListener('submit', function () {
        renumerar();
        alterado = false;
    });

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
