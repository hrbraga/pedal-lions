# 2º Pedal Solidário – Lions Clube de Cornélio Procópio

## Como rodar no XAMPP
1. Copie a pasta `pedal-solidario` para `C:\xampp\htdocs\`.
2. Abra o XAMPP Control Panel e inicie o **Apache** (e o **MySQL**, a partir da Etapa 2).
3. Acesse http://localhost/pedal-solidario/

## Banco de dados (Etapa 2)
1. Inicie Apache e MySQL no XAMPP.
2. Abra http://localhost/phpmyadmin → aba **Importar** → escolha `database/schema.sql` → **Importar**.
3. Confira `config/banco.php` (no XAMPP: usuário `root`, senha vazia).
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

## Mapas das rotas (Strava)
No Strava: página da rota → menu "..." → **Embed** → copiar o código. Cole em `config/evento.php`, no campo `mapa_embed` da rota,
entre as linhas `<<<'HTML'` e `HTML,`.

## Onde editar
- `config/evento.php`: datas, valores, rotas, programação, FAQ, contatos.
- `assets/img/logo.png`: logo do Lions (topo, home e rodapé). Favicon: `favicon.ico` na raiz e arquivos `favicon-*.png` / `apple-touch-icon.png` em `assets/img/`.
- `assets/img/camiseta.jpg`: arte da camiseta (abre ampliada em modal).
- `assets/img/tabela-tamanhos.png`: tabela de tamanhos (abre em modal).
- Camiseta (valor, prazo, tamanhos): bloco `camiseta` em `config/evento.php`.
- `assets/img/banner.jpg`: imagem de fundo do topo da home (opcional).
- `database/schema.sql`: banco de dados (Etapa 2).

## Etapas
1. Layout e páginas (feito)
2. Gravação das inscrições no MySQL (feito)
3. Pagamento (gateway pendente)
4. Painel administrativo (feito)
5. E-mail de confirmação, LGPD e testes
