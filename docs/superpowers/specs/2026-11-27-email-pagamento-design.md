# E-mail de pagamento confirmado — desenho

## Por quê

Quando o pagamento de uma inscrição é confirmado, o atleta precisa receber um e-mail avisando, enviado pela caixa
de e-mail do domínio na Hostinger (contato@lionsclubecornelio.com), e não por um serviço de terceiros.
Hoje a confirmação é manual (botão "Confirmar pagamento" no painel); depois virá automática pelo Asaas.
O envio tem de servir aos dois.

## Objetivos

- Ao confirmar o pagamento, enviar um e-mail "Pagamento confirmado" para o e-mail da inscrição.
- Registrar se o e-mail foi enviado (quando) ou por que falhou.
- Se o envio falhar, o pagamento continua confirmado; o painel avisa e permite reenviar.
- Botão para enviar/reenviar o e-mail na página da inscrição.
- Não mandar e-mail em duplicidade sem querer.

## Fora do escopo

- E-mail com instruções de pagamento (vem com o Asaas).
- E-mail para inscrições pendentes, canceladas ou para administradores.
- Fila ou envio em segundo plano: o envio acontece no clique (1 a 3 segundos).

## Como (forma A, escolhida)

**Entrar na caixa do domínio por SMTP autenticado**, com a biblioteca PHPMailer copiada para o projeto
(`includes/vendor/PHPMailer/`, sem Composer). Porque a forma B (`mail()` da hospedagem) cai em spam com mais
frequência e não diz se falhou. Escrever um cliente SMTP próprio foi descartado: o PHPMailer já resolve TLS,
acentos e anexos e é a biblioteca padrão do PHP para isso.

### Configuração

`config/email.php`, fora do git como o `config/banco.php`, com modelo em `config/email.exemplo.php`:

| chave | Hostinger |
|---|---|
| host | smtp.hostinger.com |
| porta | 465 |
| seguranca | ssl (porta 465) ou tls (porta 587); vazio = sem criptografia (só para teste local) |
| usuario | contato@lionsclubecornelio.com |
| senha | a senha da caixa (só no servidor, nunca no git nem no chat) |
| nome | Lions Clube de Cornélio Procópio |

Sem o arquivo, o e-mail não é enviado e o motivo registrado é "e-mail não configurado".

### Banco

Duas colunas novas em `inscricoes` (via `esquema_atualizar` e `schema.sql`):
- `email_pago_em DATETIME NULL` — quando o e-mail de confirmação saiu.
- `email_pago_erro VARCHAR(255) NULL` — motivo da última falha (vazio depois de um envio que deu certo).

### Fluxo

- "Confirmar pagamento" (admin/acao.php): marca pago como hoje; se `email_pago_em` estiver vazio, envia.
  - Deu certo: grava `email_pago_em`, limpa o erro. Aviso: "Pagamento confirmado: Fulano. E-mail enviado para x@y."
  - Falhou: grava o erro. Aviso: "Pagamento confirmado: Fulano. O e-mail não foi enviado (motivo). Use Reenviar e-mail na inscrição."
- Voltar para pendente e confirmar de novo não reenvia sozinho (o atleta já recebeu).
- Página da inscrição (paga): linha "E-mail de confirmação: enviado em dd/mm/aaaa hh:mm" ou "não enviado — motivo",
  e botão "Enviar e-mail de confirmação" / "Reenviar e-mail de confirmação" (pede confirmação no clique).
- Lista de inscritos: marca "e-mail não enviado" nas pagas sem envio, para ninguém ficar sem saber.
- O Asaas, quando vier, chama a mesma função de envio.

### Conteúdo

Assunto: "Pagamento confirmado – 2º Pedal Solidário". HTML simples com versão em texto puro:
- Olá, nome; pagamento da inscrição confirmado.
- Código da inscrição, categoria, camiseta (modelo e tamanho, se comprou), valor pago.
- Data, horário e local da largada do evento.
- Links para o regulamento e para o grupo do WhatsApp; contato do WhatsApp da organização.
- Assinatura: Lions Clube de Cornélio Procópio.

Textos e links saem dos Dados do Evento (o que o painel salvou), não fixos no código.
Os links usam o endereço do próprio site em que o painel está aberto.

## Testes

- Montagem da mensagem (assunto, texto, HTML, campos, escape de HTML no nome) sem banco nem rede.
- No navegador de teste, um servidor SMTP falso local recebe o e-mail: confirmar pagamento envia uma vez,
  reconfirmar não reenvia, reenviar envia de novo, servidor fora do ar registra o erro e o pagamento continua pago.
- Nenhum código só de teste no site: o teste local aponta o `config/email.php` para o servidor falso.

## Deixado para quem constrói

Layout do HTML do e-mail (simples, cores do site, legível no celular) e a redação final das frases.
