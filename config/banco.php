<?php
/**
 * CONEXÃO COM O BANCO DE DADOS
 *
 * XAMPP (local): usuário "root" e senha vazia (padrão).
 * HostGator: crie o banco e o usuário no cPanel (MySQL Databases) e preencha aqui.
 *            No cPanel os nomes costumam ter prefixo, ex.: "conta_pedal" e "conta_usuario".
 */
return [
    'host'    => 'localhost',
    'nome'    => 'pedal_solidario',
    'usuario' => 'root',
    'senha'   => '',

    // true = mostra detalhes do erro na tela (use só no XAMPP). No site publicado, deixe false.
    'debug'   => true,
];
