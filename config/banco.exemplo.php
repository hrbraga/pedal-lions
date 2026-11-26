<?php
/**
 * MODELO da conexão com o banco de dados.
 *
 * Copie este arquivo para config/banco.php e preencha. O banco.php NÃO vai para o git
 * (cada lugar tem o seu: o XAMPP local e o servidor de produção).
 *
 * XAMPP (local): usuário "root", senha vazia, debug true.
 * Hostinger: hPanel → Bancos de dados → Bancos de dados MySQL. Os nomes têm prefixo da conta,
 *            ex.: "u123456789_pedal" (banco) e "u123456789_pedal" (usuário). Host: "localhost".
 */
return [
    'host'    => 'localhost',
    'nome'    => 'u000000000_pedal',
    'usuario' => 'u000000000_pedal',
    'senha'   => 'troque-pela-senha-do-banco',

    // true = mostra detalhes do erro na tela (use só no XAMPP). No site publicado, deixe false.
    'debug'   => false,
];
