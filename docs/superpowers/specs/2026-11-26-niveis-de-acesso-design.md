# Níveis de acesso no painel – desenho (curto)

## Intenção
Pessoas diferentes do Lions vão usar o painel com funções diferentes (inscrições, financeiro, dados do evento).
Ao criar um administrador, quem cria marca quais páginas ele pode acessar.

## Abordagens
- **A) Página liberada ou não (escolhida pela organização).** Simples de entender; quem não tem a página não a vê
  no menu nem abre pelo endereço.
- B) "Só ver" × "pode alterar" por página: mais controle, o dobro de opções e de testes. Pode vir depois, se
  alguma página precisar.

## Como funciona
- **Páginas com acesso controlado:** Inscritos (lista, detalhe, confirmar/cancelar pagamento), Exportar CSV,
  Cupons, Dados do Evento, Administradores. Financeiro e Dashboard entram na lista quando forem criados.
- **"Acesso total"**: uma opção à parte que libera todas as páginas, inclusive as que forem criadas depois.
  Os administradores que já existem ficam com acesso total (hoje todos acessam tudo).
- **Banco:** duas colunas novas em `admins`: `acesso_total` (padrão 1) e `paginas` (lista das páginas liberadas).
  Criadas sozinhas na primeira visita, como as outras atualizações do banco, e também no `schema.sql`.
- **Proteção:** cada página do painel declara qual permissão exige. Sem ela, a pessoa vai para a primeira página
  que pode acessar, com o aviso "Você não tem acesso a essa página". Sem nenhuma página liberada, vê só um aviso
  e o botão Sair. As permissões são lidas do banco a cada página aberta, então mudar o acesso de alguém vale na
  hora, sem precisar sair e entrar de novo.
- **Menu:** mostra só as páginas liberadas. Depois do login, a pessoa cai na primeira página liberada.
- **Tela Administradores** (só para quem tem essa página):
  - Criar administrador: usuário, senha e as caixas de seleção das páginas, mais "Acesso total".
  - A lista mostra as páginas de cada um e tem "Editar acesso".
  - Ninguém altera o próprio acesso (evita se trancar para fora), e sempre sobra pelo menos um administrador com
    acesso total.
- **Minha conta:** trocar a própria senha passa para uma página "Minha conta", aberta a todos os
  administradores. Hoje essa opção fica dentro de Administradores, que nem todos vão ter.

## Fora do escopo
- "Só ver" × "alterar" (abordagem B).
- Registro de quem fez o quê no painel.

## Testes esperados
- Regra de permissão em função pura, testada por script PHP.
- No navegador: criar administrador só com Cupons → menu só com Cupons; abrir `painel.php` e `dados.php` pelo
  endereço → volta para Cupons com o aviso; POST direto para `acao.php` → recusado; mudar o acesso dele → vale na
  próxima página; não consegue mexer no próprio acesso; não dá para tirar o último acesso total; ele troca a
  própria senha em Minha conta.
