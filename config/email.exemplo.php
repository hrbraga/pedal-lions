<?php
/**
 * MODELO da configuração do e-mail (aviso de pagamento confirmado para o atleta).
 *
 * Copie este arquivo para config/email.php e preencha. O email.php NÃO vai para o git
 * (a senha da caixa de e-mail fica só no servidor).
 *
 * Hostinger: hPanel → E-mails → crie a caixa (ex.: contato@lionsclubecornelio.com) e anote a senha.
 *            Servidor de saída (SMTP): smtp.hostinger.com, porta 465, segurança SSL.
 * Sem este arquivo o site funciona normalmente, mas não envia e-mail (o painel avisa "e-mail não configurado").
 */
return [
    'host'      => 'smtp.hostinger.com',
    'porta'     => 465,
    'seguranca' => 'ssl',                          // 'ssl' (porta 465) ou 'tls' (porta 587)
    'usuario'   => 'contato@lionsclubecornelio.com', // a caixa que envia (também recebe as respostas)
    'senha'     => 'troque-pela-senha-da-caixa',
    'nome'      => 'Lions Clube de Cornélio Procópio', // nome que aparece como remetente
];
