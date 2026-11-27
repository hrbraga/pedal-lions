# Pagamento das inscrições pelo Mercado Pago — desenho

## Por quê

Hoje a inscrição termina na página "Inscrição recebida" com o aviso "as instruções de pagamento serão divulgadas em
breve", e o pagamento só é confirmado à mão no painel ("Confirmar pagamento"). O Lions quer receber pelo site, com
**Pix e cartão de crédito**, pelo **Mercado Pago** (substitui o Asaas que estava planejado), com a inscrição
confirmada sozinha quando o pagamento é aprovado — e com isso o e-mail de confirmação e o Financeiro funcionando
sem ninguém clicar em nada.

Também resolve um problema de vagas: hoje uma inscrição pendente segura a vaga para sempre. Com pagamento online,
quem desiste no meio não pode travar o limite (ex.: 300).

## Objetivos

- Logo após concluir a inscrição, um **modal** de pagamento abre por cima da página "Inscrição recebida"
  (esboço aprovado: `docs/superpowers/specs/2026-11-27-pagamento-modal-esboco.html`).
- **Pix** (aba aberta primeiro): QR-Code + código copia e cola + prazo com contagem regressiva.
- **Cartão de crédito**: formulário do próprio Mercado Pago embutido no modal, **até 2 parcelas**.
- Pagamento aprovado → inscrição vira **paga** sozinha, `pago_em` = momento da aprovação, o e-mail "Pagamento
  confirmado" sai (o que já existe, uma vez só) e o valor entra no Financeiro (já automático pelas inscrições pagas).
- O modal percebe a aprovação sozinho (o Pix é pago no app do banco) e mostra "Pagamento confirmado!".
- **E-mail de instruções de pagamento** ao concluir a inscrição: QR-Code, copia e cola, prazo e botão para abrir a
  página de pagamento (ali também dá para pagar com cartão).
- **Prazo de 6 horas** desde a inscrição. Passou sem pagar: a inscrição vira **"Não paga (expirada)"**, libera a vaga
  e continua no relatório do painel. Quem quiser voltar faz **uma nova inscrição** (novo QR-Code).
- O limite de vagas continua exato: pagas + pendentes dentro do prazo ocupam vaga; expiradas não.
- O botão manual "Confirmar pagamento" continua para pagamentos por fora (dinheiro, transferência).

## Fora do escopo

- Boleto, Checkout Pro (redirecionar para a página do Mercado Pago), outras formas de pagamento.
- Devolver dinheiro pelo site: estorno é feito no painel do Mercado Pago pela organização.
- Conciliar no Financeiro a tarifa real do Mercado Pago ou os juros do parcelamento: o Financeiro continua lançando
  os valores da inscrição (inscrição, camiseta, taxa e o repasse da taxa no mesmo valor), como hoje.
- Configurar quem paga os juros das 2 parcelas: é uma opção da conta do Mercado Pago, não do site.
- Guardar dados de cartão (nunca passam pelo nosso servidor).
- Lembrete por e-mail antes de expirar.

## Não pode acontecer

- Passar do limite de vagas.
- Confirmar uma inscrição com valor diferente do total dela, ou por um aviso falso (alguém chamando a URL do aviso).
- Credenciais do Mercado Pago no git ou no chat.
- E-mail de confirmação em duplicidade.
- Cobrar depois do prazo sem ninguém saber.

## Como (forma A, escolhida)

| forma | o que é | por que (não) |
|---|---|---|
| **A — pagamento dentro do site (escolhida)** | Pix criado pela API do Mercado Pago e mostrado no nosso modal; cartão pelo formulário embutido do Mercado Pago (Card Payment Brick, JavaScript deles) | controle exato do prazo, QR-Code também no e-mail, o atleta não sai do site, visual do evento |
| B — Checkout Pro | botão "Pagar" leva à página do Mercado Pago e volta | mais simples, mas o prazo do Pix é deles, o QR-Code não vai no e-mail e o atleta sai do site no meio |

Sem SDK nem Composer: chamadas HTTPS diretas à API REST do Mercado Pago com cURL (como o PHPMailer, nada a instalar
na Hostinger). No navegador, o script oficial `https://sdk.mercadopago.com/js/v2` só para o formulário de cartão.

## Decisões da organização

1. Mercado Pago; **Pix e cartão de crédito**; **cartão em até 2x**.
2. **Prazo de 6 horas** (vale para a vaga e para o QR-Code do Pix, que expira junto no Mercado Pago).
3. Expirou: status **"Não paga (expirada)"**, libera a vaga, aparece no relatório do painel; para voltar, nova
   inscrição com novo QR-Code.
4. Pagamento em **modal**, com o esboço aprovado (três momentos: Pix, Cartão, Confirmado). Pix é a aba inicial.
5. **Um CPF só pode ter uma inscrição ativa** (paga ou pendente dentro do prazo). Expiradas e canceladas não
   bloqueiam o CPF.
6. **Pagamento aprovado depois do prazo** (cartão que ficou "em análise" no Mercado Pago):
   - se ainda há vaga e o CPF não tem outra inscrição ativa → a inscrição volta como **paga** normalmente;
   - senão → fica paga, mas em destaque no painel: **"paga sem vaga — devolver o dinheiro"**, para a organização
     decidir.
7. Expiradas **não aparecem** na lista pública de inscritos (como as canceladas; a lista já mostra só pagas).

## Comportamento

### Inscrição (inscricao.php)

- Ao gravar uma inscrição com total > 0: `status = 'pendente'`, `expira_em = agora + 6 h`.
- Ainda dentro do envio do formulário, cria o Pix no Mercado Pago (valor = `valor_total` do banco, vencimento =
  `expira_em`, referência = código da inscrição) e guarda o copia e cola, a imagem do QR-Code e o id do pagamento.
  Se o Mercado Pago falhar, a inscrição fica gravada do mesmo jeito; o modal mostra "Não foi possível gerar o Pix
  agora" com um botão "Tentar de novo" (e o cartão continua disponível).
- Envia o e-mail de instruções de pagamento (falha no envio não impede nada; fica registrada como no e-mail de
  confirmação).
- Redireciona para `inscricao_recebida.php?c=CODIGO&pagar=1`, que abre o modal.
- Total 0 (cupom de 100%) continua entrando como pago, sem Mercado Pago.
- **CPF**: a conferência passa a ser "existe inscrição ativa com este CPF?" e vai para dentro da trava de vagas
  (hoje está antes dela), para dois envios simultâneos do mesmo CPF não passarem os dois. A coluna `cpf` deixa de
  ser UNIQUE no banco (vira índice comum). Mensagem se houver pendente ativa: "Já existe uma inscrição com este CPF
  aguardando pagamento até HH:MM. Use o link enviado para o seu e-mail." Se houver paga: a mensagem de hoje.

### Expiração

- Uma inscrição `pendente` com `expira_em` no passado está expirada. Sem tarefa agendada: antes de contar vagas,
  de listar no painel/dashboard e de processar um pagamento, o site executa
  `UPDATE inscricoes SET status = 'expirado' WHERE status = 'pendente' AND expira_em < NOW()`.
- `expira_em` é calculado pelo próprio MySQL (`NOW() + INTERVAL 6 HOUR`) e comparado com `NOW()`, para o relógio
  do PHP e o do banco nunca discordarem; o vencimento enviado ao Mercado Pago é esse mesmo valor, com fuso.
- Inscrições criadas antes desta funcionalidade (sem `expira_em`) e as criadas pelo painel não expiram.
- `expirado` vira um novo valor do status, com rótulo "Não paga (expirada)".
- Onde hoje se conta "tudo que não é cancelado" (vagas, usos de cupom, painel por categoria/tamanho) passa a contar
  só `pendente` e `pago`. Ou seja: **expirada também devolve o uso do cupom**.
- No painel, uma expirada pode ser **confirmada manualmente** (pagou por fora) somente se houver vaga, conferido
  dentro da trava de vagas; ou excluída. "Voltar para pendente" não se aplica a expiradas.

### Modal de pagamento (inscricao_recebida.php)

- Abre sozinho quando a inscrição está pendente e a URL tem `pagar=1`; se fechado, a página mostra o prazo e um
  botão **"Pagar agora"** que reabre.
- Cabeçalho azul com logo do Lions, "Pagamento da inscrição", código e nome; faixa com "Total a pagar".
- Aba **Pix**: QR-Code, "Pague até DD/MM às HHhMM · faltam Xh Ymin" (contagem viva), copia e cola com botão
  "Copiar", três passos, "Aguardando o pagamento…".
- Aba **Cartão de crédito**: Card Payment Brick com no máximo 2 parcelas e o valor do banco; botão "Pagar R$ X".
  Recusado → mensagem no próprio modal, pode tentar outro cartão ou voltar ao Pix.
- A página consulta o site a cada ~5 s (`pagamento_status.php?c=CODIGO`, responde só o status) enquanto o modal está
  aberto. Aprovado → tela "Pagamento confirmado!" (visto verde, código, "enviamos a confirmação para m***@…",
  botão "Faça seu avatar!", "Fechar").
- Expirada → a página diz "O prazo de pagamento terminou e a vaga foi liberada." com botão para uma nova inscrição
  (se as inscrições estiverem abertas).

### Pagamento com cartão (pagamento_cartao.php)

- Recebe do formulário do Mercado Pago o token do cartão, parcelas, bandeira e dados do pagador; cria o pagamento
  na API com o valor **lido do banco** (nunca o que veio do navegador), referência = código, chave de idempotência.
- Recusa se a inscrição não está pendente ou já passou de `expira_em`.
- Aprovado na hora → mesma rotina de "aprovar" abaixo. Em análise → o modal mostra "Pagamento em análise pelo
  Mercado Pago; avisaremos por e-mail" e o aviso do Mercado Pago resolve depois.

### Aviso do Mercado Pago (pagamento_aviso.php — webhook)

- URL pública configurada no painel do Mercado Pago (evento "Pagamentos"):
  `https://lionsclubecornelio.com/pagamento_aviso.php`.
- Confere a assinatura do aviso (`x-signature`, com a chave secreta do webhook) e, de qualquer forma, **nunca
  confia no conteúdo do aviso**: busca o pagamento na API pelo id e decide pelo que a API responde.
- Rotina "aprovar" (compartilhada com cartão e consulta): status `approved`, referência = código de uma inscrição,
  valor = `valor_total` dela. Feita com a linha travada (sem processar duas vezes):
  - pendente → paga, `pago_em = NOW()`, envia o e-mail de confirmação se ainda não saiu;
  - expirada → decisão 6 (dentro da trava de vagas);
  - mesmo pagamento já registrado → nada (aviso repetido);
  - **outro** pagamento aprovado numa inscrição que já está paga (pagou Pix e cartão; ou o painel confirmou à mão e
    depois o Pix caiu) ou que foi cancelada → não muda o status e marca em destaque no painel
    **"pagamento em duplicidade — devolver o dinheiro"**, com o id do pagamento. *(Decisão de quem desenhou, na
    mesma linha da decisão 6.)*
- Valor diferente, referência desconhecida: registra no log e não confirma.
- Estornado/contestado (`refunded`, `charged_back`) numa inscrição paga: marca em destaque no painel
  ("estornado no Mercado Pago"); não muda o status sozinho.
- Responde 200 rápido para o Mercado Pago não ficar repetindo.

### E-mail de instruções de pagamento

- Mesmo visual do e-mail de confirmação (logo, "Olá, Atleta <nome>!"), assunto "Pague sua inscrição até HH:MM –
  {evento}".
- Total, prazo, QR-Code (imagem anexada no corpo) e copia e cola; botão "Clique aqui para pagar (Pix ou cartão)"
  → `inscricao_recebida.php?c=CODIGO&pagar=1`; aviso de que depois do prazo a vaga é liberada.
- Sem Pix gerado (falha no Mercado Pago): vai só com o botão.

### Painel

- Filtro e selo "Não paga (expirada)"; resumo com a contagem de expiradas; exportação com o rótulo.
- Na inscrição: forma de pagamento (Pix / cartão Nx), id do pagamento no Mercado Pago, prazo.
- Selos de destaque: "paga sem vaga — devolver o dinheiro", "estornado no Mercado Pago".
- Dashboard: "pendentes" passa a ser só as dentro do prazo; expiradas não entram nos gráficos.
- Financeiro: sem mudança (expirada não tem `pago_em`).

### Configuração

`config/mercadopago.php`, fora do git (como `banco.php` e `email.php`), modelo em `config/mercadopago.exemplo.php`:
`access_token`, `public_key`, `webhook_secret`. Credenciais de teste ou de produção decidem o modo. Sem o arquivo,
o site funciona como hoje: inscrição pendente, aviso "instruções de pagamento em breve", sem modal e **sem
expiração** (`expira_em` só é preenchido quando o pagamento online está ligado).

## Testes

- Servidor falso da API do Mercado Pago em `tests/` (como o `smtp_falso.php`), com o endereço da API configurável,
  para testar sem internet: criar Pix, cartão aprovado/recusado/em análise, consulta de pagamento.
- Aviso simulado: assinatura válida/inválida, valor errado, referência desconhecida, aviso repetido, aprovado depois
  do prazo com e sem vaga.
- Expiração: vaga liberada, cupom devolvido, CPF liberado, limite exato com pendentes/expiradas.
- Cenários no navegador (CDP) para o modal: abrir sozinho, trocar abas, copiar, contagem, virar "confirmado".
- Teste de verdade, com as credenciais de teste do Mercado Pago, no site da Hostinger (o aviso só chega em URL
  pública).

## Fica com quem constrói

- Estrutura de tabelas: colunas na `inscricoes` ou uma tabela `pagamentos` (uma linha por tentativa) — escolha de
  quem constrói; precisa guardar id do pagamento, forma, parcelas, copia e cola, imagem do QR-Code e marcas de
  destaque.
- Onde fica a constante do prazo (6 h) — no código, num só lugar.
- Detalhes visuais finos do modal, dentro do esboço aprovado.
- Textos exatos das mensagens de erro, no tom das já existentes.
