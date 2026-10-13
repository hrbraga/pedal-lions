# 2º Pedal Solidário – Lions Clube de Cornélio Procópio

## Como rodar no XAMPP
1. Copie a pasta `pedal-solidario` para `C:\xampp\htdocs\`.
2. Abra o XAMPP Control Panel e inicie o **Apache** (e o **MySQL**, a partir da Etapa 2).
3. Acesse http://localhost/pedal-solidario/

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
2. Gravação das inscrições no MySQL
3. Pagamento (gateway pendente)
4. Painel administrativo
5. E-mail de confirmação, LGPD e testes
