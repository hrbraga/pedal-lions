# Dados do Evento no painel – desenho

## Intenção

Hoje toda informação do evento (data, local, valores, prazos, vagas, contatos, categorias, premiações, rotas,
programação, perguntas frequentes) vive em `config/evento.php`. Mudar qualquer coisa exige editar código e
publicar o arquivo. A organização quer fazer essas mudanças pelo painel administrativo, numa página nova chamada
**"Dados do Evento"**.

Quem vai usar: o responsável técnico e **pessoas do Lions que não mexem com código**. Por isso a página precisa
ser à prova de erro: validar antes de salvar, nunca derrubar o site e permitir desfazer.

## Objetivos

- Editar pelo painel tudo o que hoje está em `config/evento.php`: os campos simples, as listas e os textos longos.
- Salvar sem tocar em arquivo nenhum; o site passa a ler os dados do banco.
- Guardar o histórico: cada gravação vira uma versão, com data e quem salvou, e qualquer versão pode ser restaurada.
- Não deixar que uma edição quebre o site ou as inscrições já feitas.

## Fora do escopo

- O texto do **regulamento** continua escrito em `regulamento.php` (decisão da organização).
- Envio de imagens (logo, banner, arte e tabela da camiseta, logos de patrocinadores): continua por arquivo na pasta
  `assets/img/`. Uma tela de upload pode vir depois.
- Dados de conexão com o banco (`config/banco.php`) nunca vão para o painel.
- A moldura e o texto de compartilhamento do avatar "Eu vou!" (a data está desenhada na arte).
- Permissões diferentes por administrador: todo administrador pode editar.

## Anti-objetivos (seria um fracasso mesmo funcionando)

- Um erro de digitação no painel derrubar o site ou a página de inscrição.
- Alguém conseguir colocar script no site público pelo painel.
- Excluir ou renomear o identificador de uma categoria que já tem inscritos, deixando inscrições "órfãs".
- Duas pessoas editando ao mesmo tempo e uma apagar, sem saber, o que a outra salvou.

## Abordagens consideradas

- **A) Um registro com todos os dados no banco, numa página com seções (escolhida).** O site lê o registro mais
  recente; `config/evento.php` vira só o valor de partida. Cada gravação é uma nova versão, o que dá histórico e
  "restaurar" quase de graça. Uma página só, salva tudo de uma vez.
- **B) Uma tabela e uma tela por lista, no padrão da tela de Cupons.** Edição item a item, mais familiar no painel,
  mas cerca de cinco vezes mais código e sem um "voltar versão" simples.
- **C) O painel reescrever `config/evento.php`.** Descartada: na hospedagem o arquivo pode não ter permissão de
  escrita, e uma gravação com erro derruba o site inteiro.

A organização escolheu **A**.

## Como funciona

### Armazenamento

- Nova tabela `evento_versoes`: `id`, `dados` (JSON em LONGTEXT), `salvo_em` (DATETIME), `salvo_por` (usuário do
  painel), `restaurada_de` (id da versão de origem, quando é uma restauração; senão NULL).
- A versão em vigor é a de maior `id`. Restaurar uma versão antiga **cria uma nova versão** com o conteúdo dela;
  nada é apagado, exceto pela limpeza abaixo.
- Mantêm-se as **50 versões mais recentes**; as mais antigas são apagadas ao salvar.
- A tabela é criada sozinha na primeira visita, no mesmo padrão de `esquema_atualizar()` em `includes/db.php`, e
  entra também em `database/schema.sql`.

### Leitura pelo site

- `evento()` continua sendo o único ponto de leitura. Passa a fazer: valores de `config/evento.php` → por cima, os
  dados da versão em vigor (campos simples substituem um a um; listas substituem a lista inteira).
- Se o banco não responder, ou se ainda não houver versão salva, o site usa `config/evento.php` como hoje. O site
  nunca cai por causa desta funcionalidade.
- Uma consulta por requisição (o resultado fica guardado em memória durante a requisição).
- `data_curta` e `data_extenso` deixam de ser digitadas: são calculadas da data do evento
  (ex.: `14/03/2027` e `Domingo, 14 de março de 2027`).

### A página "Dados do Evento"

Nova entrada **"Dados do Evento"** no menu do painel. Uma página só, com um índice de seções no topo e um botão
**Salvar** fixo no rodapé da tela.

```
Dados do Evento                                     Última alteração: 26/11/2026 14:32 por hugo
[Geral] [Inscrições] [Contatos] [Camiseta] [Categorias] [Premiações] [Rotas] [Programação] [Perguntas] [Patrocinadores] [Histórico]

Geral
  Nome do evento [2º Pedal Solidário        ]   Organizador [Lions Clube de Cornélio Procópio]
  Data do evento [14/03/2027]   Cidade [Cornélio Procópio]  UF [PR]
  Local de largada [Bosque Municipal de Cornélio Procópio]

Categorias
  ┌ Rachão ─────────────────────────────── [↑][↓]  (12 inscritos – não pode ser excluída)
  │ Nome [Rachão]  Valor [70,00]  Resumo [Categoria competitiva, com premiação…]
  └───────────────────────────────────────────────
  [+ Adicionar categoria]
                                                                    [ Salvar alterações ]
```

Seções e campos:

| Seção | Campos |
|---|---|
| Geral | nome do evento, organizador, data do evento, cidade, UF, local de largada |
| Inscrições | aberta (sim/não), último dia para se inscrever, número de vagas (0 = sem limite), taxa de administração (%) |
| Contatos | e-mail, WhatsApp, Instagram (link) |
| Camiseta | valor, último dia para comprar, tamanhos (lista de textos curtos, com ordem) |
| Categorias | lista: nome, valor, resumo |
| Premiações | lista: título, regra, prêmio |
| Rotas | lista: nome, distância, altimetria, descrição, mapa do Strava |
| Programação | lista: hora, título, descrição |
| Perguntas frequentes | lista: pergunta, resposta (texto longo) |
| Patrocinadores | para cada logo encontrado em `assets/img/patrocinadores/`: nome e link |
| Histórico | últimas versões: data, quem salvou, botão "Restaurar esta versão" (com confirmação) |

Listas: cada item tem botões subir, descer e remover; no fim da lista, "Adicionar". Tudo só vale ao clicar em
Salvar. Ao sair da página com alterações não salvas, o navegador pergunta se quer mesmo sair.

### Regras de validação (no servidor, sempre)

Se qualquer regra falhar, nada é salvo, a página volta com o que a pessoa digitou e os erros aparecem no topo e
junto de cada campo.

- Textos obrigatórios não podem ficar vazios; todos têm tamanho máximo.
- Datas válidas. O último dia de inscrição e o último dia da camiseta não podem ser depois da data do evento.
- Valores em reais: número ≥ 0, com até 2 casas, aceitando vírgula. Taxa: 0 a 100.
- Vagas: inteiro ≥ 0. Se for menor que o número de inscrições que já ocupam vaga, salva mas avisa que as
  inscrições ficaram fechadas.
- E-mail válido; WhatsApp com DDD (10 ou 11 dígitos), guardado já formatado; Instagram vazio ou link `https://`.
- Programação: hora no formato HH:MM.
- Pelo menos uma categoria e um tamanho de camiseta.
- **Categorias:** cada uma tem um identificador interno (o `slug` atual) que nunca muda depois de criado; o
  identificador de uma nova é gerado do nome. Uma categoria com inscrições (de qualquer situação) **não pode ser
  removida**; o botão remover nem aparece nela. Renomear e mudar o valor são permitidos (inscrições já feitas
  guardam o valor que pagaram).
- **Mapa do Strava:** a pessoa cola o **link da rota** ou o **código de incorporação** que o Strava fornece. O
  servidor extrai só os dados da rota (identificador e, se vierem, as opções do próprio Strava) e o site monta o
  mapa com o seu próprio código. Qualquer outro HTML colado é descartado. Nenhum campo do painel aceita HTML: todo
  texto é exibido como texto.

### Edição ao mesmo tempo

O formulário leva o número da versão que estava em vigor quando foi aberto. Se, ao salvar, já existir uma versão
mais nova, nada é gravado e aparece: "Outra pessoa salvou alterações enquanto você editava. Abra a página de novo
para ver a versão atual." (o que a pessoa digitou continua na tela para ela copiar).

### Mudanças em páginas existentes

- `regulamento.php`: o texto continua o mesmo, mas a frase do prazo e das vagas passa a usar os valores salvos
  (hoje está escrito "12/03/2027" e "300" direto no texto), para não ficar desatualizada quando mudarem no painel.
  O prazo de transferência (12/03/2027) continua fixo no texto.
- Pergunta frequente "Quanto custa a inscrição?": hoje monta o percentual da taxa automaticamente; vira texto comum,
  editado no painel como as outras.
- `rotas.php` passa a montar o mapa a partir dos dados da rota, em vez de imprimir o código colado.
- `config/evento.php` ganha no topo o aviso de que, depois do primeiro "Salvar" no painel, os valores dele só
  valem como padrão.

## Restrições

- PHP + MySQL do projeto, sem bibliotecas novas; JavaScript simples na página do painel (o painel já exige login).
- Funciona no XAMPP e na HostGator.
- Mesma proteção das outras telas: login obrigatório, token CSRF em todo POST.

## Testes esperados

- Regras de validação e montagem do mapa do Strava em funções puras, testadas por script PHP como
  `tests/inscricoes_test.php`.
- Leitura com e sem banco: o site segue no ar com o banco desligado.
- Fluxo completo no navegador: editar cada seção, salvar, ver no site; erro de validação; categoria com inscritos
  sem botão remover; duas abas salvando ao mesmo tempo; restaurar uma versão.

## Decisões deixadas para quem constrói

- Layout exato e estilos (seguir `assets/css/admin.css`).
- Nome dos arquivos e funções.
- Como o JavaScript monta as linhas das listas.
