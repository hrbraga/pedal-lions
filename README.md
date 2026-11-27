# 2º Pedal Solidário – Lions Clube de Cornélio Procópio

## Como rodar no XAMPP
1. Copie a pasta `pedal-solidario` para `C:\xampp\htdocs\`.
2. Abra o XAMPP Control Panel e inicie o **Apache** (e o **MySQL**, a partir da Etapa 2).
3. Acesse http://localhost/pedal-solidario/

## Banco de dados (Etapa 2)
1. Inicie Apache e MySQL no XAMPP.
2. Abra http://localhost/phpmyadmin → crie o banco `pedal_solidario` (agrupamento `utf8mb4_unicode_ci`) → selecione-o → aba **Importar** → escolha `database/schema.sql` → **Importar**.
3. Copie `config/banco.exemplo.php` para `config/banco.php` (no XAMPP: usuário `root`, senha vazia, `debug` true). O `banco.php` não vai para o git.
4. Faça uma inscrição de teste e veja a tabela `inscricoes` no phpMyAdmin.

## Painel administrativo (Etapa 4)
1. Acesse http://localhost/pedal-solidario/admin/
2. No primeiro acesso, cadastre o usuário e a senha (mínimo de 10 caracteres). Essa tela se desativa sozinha depois.
3. Em **Administradores** você cria e remove usuários, altera a sua senha e redefine a de outra pessoa.
4. No painel: resumo, busca e filtros, detalhe de cada inscrito, confirmação manual de pagamento, camisetas por tamanho e exportação em CSV (Excel).

## Cupons de desconto
- Painel → **Cupons**: cria cupons (nome, código, % ou valor fixo, quantidade), edita, ativa/desativa e vê quem usou cada um.
- O desconto vale só para a inscrição, nunca para a camiseta.
- O banco se atualiza sozinho na primeira visita (tabelas/colunas dos cupons); o `schema.sql` já inclui tudo para instalações novas.

## Avatar "Eu vou!"
- Página `avatar.php` (menu **Eu vou!**, botão na home e chamada após a inscrição): a pessoa escolhe uma foto,
  ajusta atrás da moldura e compartilha ou baixa em **Feed 1:1** (1080×1080) ou **Stories 9:16** (1080×1920).
- Tudo acontece no navegador: a foto não é enviada ao servidor.
- Molduras: `assets/img/avatar/moldura-1x1.png` e `assets/img/avatar/moldura-9x16.png`. Devem ser PNG **com
  transparência real** no recorte, em 1080×1080 e 1080×1920. Para trocar a arte, substitua os arquivos mantendo os nomes.
- Testes dos cálculos de ajuste: `node --test` na raiz do projeto.

## Dados do Evento (painel)
- Painel → **Dados do Evento**: edita data, local, contatos, inscrições (prazo, vagas, taxa), camiseta, categorias,
  premiações, rotas, programação, perguntas frequentes e nome/link dos patrocinadores. O texto do regulamento continua em `regulamento.php`.
- Cada "Salvar" grava uma versão (tabela `evento_versoes`, criada sozinha); o **Histórico** guarda as 50 mais recentes e permite restaurar.
- `config/evento.php` passa a ser só o valor padrão: depois do primeiro "Salvar" no painel, valem os dados salvos.
  Se o banco estiver fora do ar, o site usa o arquivo.
- Mapas: cole o link da rota no Strava ou o código de incorporação; o site guarda só os dados da rota e monta o mapa.
- Categorias com inscritos não podem ser excluídas (podem ser renomeadas e ter o valor alterado).
- Testes: `php tests/evento_dados_test.php` e `php tests/evento_validar_test.php`.

## Mapas das rotas (Strava)
Pelo painel (Dados do Evento → Rotas) basta colar o link da rota. O método abaixo, direto no arquivo, continua valendo enquanto nada for salvo no painel.
No Strava: página da rota → menu "..." → **Embed** → copiar o código. Cole em `config/evento.php`, no campo `mapa_embed` da rota,
entre as linhas `<<<'HTML'` e `HTML,`.

## Publicar na Hostinger (Git pelo hPanel)
1. **PHP:** hPanel → Avançado → Configuração do PHP → versão **8.2** ou mais nova.
2. **Banco:** hPanel → Bancos de dados → Bancos de dados MySQL → crie banco e usuário (anote os nomes com prefixo e a senha).
   Depois, phpMyAdmin → escolha o banco → **Importar** → `database/schema.sql`.
3. **Código:** hPanel → Avançado → Git → repositório `git@github.com:hrbraga/pedal-lions.git`, branch `main`,
   pasta em branco (= `public_html`, que precisa estar vazia). Se o repositório for privado, copie a chave SSH que o hPanel
   mostra e cadastre no GitHub em Settings → Deploy keys (só leitura). Para atualizar: botão **Implantar** (ou ative a implantação automática).
4. **Conexão:** Gerenciador de Arquivos → `public_html/config/` → crie `banco.php` copiando `banco.exemplo.php` e preencha
   com os dados do passo 2 (`debug` false). Como ele não está no git, as atualizações não o apagam.
   **E-mail:** crie também `email.php` copiando `email.exemplo.php` e coloque a senha da caixa (hPanel → E-mails).
   Sem ele, o site não envia o aviso de pagamento confirmado (o painel mostra "e-mail não configurado").
   **Mercado Pago:** crie `mercadopago.php` copiando `mercadopago.exemplo.php` (veja "Pagamento (Mercado Pago)" abaixo).
5. **HTTPS:** hPanel → Segurança → SSL → instale o certificado grátis e ative "Forçar HTTPS".
6. **Primeiro administrador:** abra `https://SEU-DOMINIO/admin/` → cadastre usuário e senha (a tela se desativa depois).
7. **Inscrições:** começam fechadas ("As inscrições ainda não estão abertas."). Abra no painel em Dados do Evento → Inscrições.
8. **Conferir a proteção:** `https://SEU-DOMINIO/.git/config`, `/database/schema.sql`, `/config/banco.php`, `/config/email.php` e `/config/mercadopago.php` devem dar
   "não encontrado" (404). O `.htaccess` da raiz cuida disso; o de `uploads/` só deixa abrir imagens.
9. **Logos enviados pelo painel** ficam em `uploads/patrocinadores/` (fora do git): a pasta precisa existir com permissão de
   escrita (o site a cria sozinho) e deve ser mantida ao mudar de hospedagem. Depois de enviar um logo, abra-o no navegador para conferir.
## Pagamento (Mercado Pago)
O atleta paga com Pix ou cartão de crédito (até 2x) num modal logo depois de se inscrever; a inscrição vira paga sozinha,
o e-mail de confirmação sai e o valor entra no Financeiro. Quem não paga em **6 horas** (`PAGAMENTO_PRAZO_HORAS` em
`includes/pagamento.php`) fica "Não paga (expirada)": a vaga, o CPF e o cupom são liberados.
1. **Credenciais:** mercadopago.com.br/developers → Suas integrações → sua aplicação → Credenciais. Comece pelas **de teste**
   (começam com `TEST-`). Em `config/mercadopago.php` (fora do git) preencha `access_token` e `public_key`.
2. **Aviso de pagamento (webhook):** na mesma aplicação → Webhooks → Configurar notificações → URL de produção
   `https://lionsclubecornelio.com/pagamento_aviso.php`, evento **Pagamentos** → salvar. Copie a **assinatura secreta** para
   `webhook_secret`. Sem ela os avisos são recusados: o site só confirma quando o atleta (ou o painel, no botão
   "Conferir no Mercado Pago" da inscrição) abre a página de pagamento. Confira o webhook antes de abrir as inscrições.
3. **Testar:** faça uma inscrição no site com as credenciais de teste e pague com o Pix de teste ou um cartão de teste
   (lista em mercadopago.com.br/developers → Cartões de teste; nome do titular `APRO` aprova, `OTHE` recusa).
4. **Produção:** troque `access_token` e `public_key` pelas credenciais de produção (e confira o webhook de produção).
5. **Juros do parcelamento:** quem paga os juros das 2x é uma opção da conta do Mercado Pago (Seu negócio → Custos), não do site.
- Sem `config/mercadopago.php` o site funciona como antes: inscrição pendente sem prazo e confirmação manual no painel.
- No painel: "Não paga (expirada)", a forma de pagamento e alertas como "paga sem vaga — devolver o dinheiro",
  "pagamento em duplicidade — devolver o dinheiro" e "estornado no Mercado Pago" (estorno é feito no painel do Mercado Pago).
- Testes: `php tests/mercadopago_test.php` e `php tests/pagamento_test.php` (usam um Mercado Pago falso, sem internet).

## Onde editar
- `config/evento.php`: datas, valores, rotas, programação, FAQ, contatos.
- Inscrições: fecham sozinhas após `inscricoes_prazo_iso` (vale até o fim do dia) ou ao atingir `inscricoes_vagas`
  (contam as pagas e as pendentes dentro do prazo; cancelar ou expirar libera a vaga). `inscricoes_abertas => false` fecha antes.
  Teste da regra: `php tests/inscricoes_test.php`.
- `assets/img/logo.png`: logo do Lions (topo, home e rodapé). Favicon: `favicon.ico` na raiz e arquivos `favicon-*.png` / `apple-touch-icon.png` em `assets/img/`.
- `assets/img/camiseta.jpg`: arte da camiseta (abre ampliada em modal).
- `assets/img/tabela-tamanhos.png`: tabela de tamanhos (abre em modal).
- Camiseta (valor, prazo, tamanhos): bloco `camiseta` em `config/evento.php`.
- `assets/img/banner.jpg`: imagem de fundo do topo da home (opcional).
- `database/schema.sql`: banco de dados (Etapa 2).

## Etapas
1. Layout e páginas (feito)
2. Gravação das inscrições no MySQL (feito)
3. Pagamento pelo Mercado Pago (Pix e cartão)
4. Painel administrativo (feito)
5. E-mail de confirmação, LGPD e testes
