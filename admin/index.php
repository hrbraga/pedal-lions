<?php
require __DIR__ . '/_bootstrap.php';
header('Location: ' . (admin_logado() ? admin_inicio() : 'login.php'));
exit;
