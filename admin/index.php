<?php
require __DIR__ . '/_bootstrap.php';
header('Location: ' . (admin_logado() ? 'painel.php' : 'login.php'));
exit;
