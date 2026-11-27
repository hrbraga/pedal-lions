<?php
/**
 * MODELO da configuração do Mercado Pago (pagamento das inscrições com Pix e cartão).
 *
 * Copie este arquivo para config/mercadopago.php e preencha. O mercadopago.php NÃO vai para o git
 * (as credenciais ficam só no servidor).
 *
 * Onde achar: mercadopago.com.br/developers → Suas integrações → (criar/abrir a aplicação) →
 *   - Credenciais de teste ou Credenciais de produção: "Public Key" e "Access Token".
 *     As de teste (começam com TEST-) cobram com cartões e Pix de teste, sem dinheiro de verdade.
 *   - Webhooks → Configurar notificações: URL https://lionsclubecornelio.com/pagamento_aviso.php,
 *     evento "Pagamentos"; depois de salvar, copie a "Assinatura secreta" para webhook_secret.
 * Sem este arquivo o site funciona como antes: a inscrição fica pendente e não há pagamento online.
 */
return [
    'access_token'   => 'TEST-0000000000000000-000000-00000000000000000000000000000000-000000000',
    'public_key'     => 'TEST-00000000-0000-0000-0000-000000000000',
    'webhook_secret' => 'cole-aqui-a-assinatura-secreta-do-webhook',
];
