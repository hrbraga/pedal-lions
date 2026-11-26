<?php
$pagina = 'regulamento';
$titulo = 'Regulamento';
$descricao = 'Regulamento do 2º Pedal Solidário do Lions Clube de Cornélio Procópio.';
require __DIR__ . '/includes/header.php';
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Regulamento</h1>
        <p>Leia com atenção antes de se inscrever. Ao se inscrever, você declara que concorda com todas as regras abaixo.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <p class="aviso"><strong>Minuta para revisão.</strong> Os textos dos itens 9 a 11 (termo de responsabilidade, uso de imagem e proteção de dados) ainda passarão por revisão jurídica.</p>

        <div class="texto">
            <h2>1. O evento</h2>
            <p>O <?= e($ev['nome']) ?> é um evento de ciclismo de caráter solidário, promovido pelo <?= e($ev['organizador']) ?>, que acontecerá em <?= e($ev['data_curta']) ?>, em <?= e($ev['cidade']) ?>, <?= e($ev['uf']) ?>. Local de largada: <?= e($ev['local_largada']) ?>.</p>

            <h2>2. Categorias</h2>
            <ul>
                <?php foreach ($ev['categorias'] as $cat): ?>
                    <li><strong><?= e($cat['nome']) ?></strong>: <?= e(moeda($cat['valor'])) ?>. <?= e($cat['resumo']) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>O evento não é uma prova de velocidade em via fechada. Os percursos são inteiramente em estrada de terra.</p>

            <h2>3. Inscrição e pagamento</h2>
            <ul>
                <li>A inscrição é feita exclusivamente pelo site oficial do evento.</li>
                <li>A inscrição só é efetivada após a confirmação do pagamento. Enquanto o pagamento não for confirmado, a inscrição fica pendente e não garante vaga nem kit.</li>
                <li>Sobre o valor da inscrição incide taxa de administração de <?= e(taxa_rotulo()) ?>, somada ao pagamento. Eventual cupom de desconto abate apenas o valor da inscrição, sem alterar a taxa, exceto quando o cupom cobre 100% da inscrição: nesse caso a taxa também é isenta. A camiseta, quando adquirida, não tem taxa nem desconto.</li>
                <li>O participante é responsável por informar dados corretos. Os dados informados serão usados para a organização do evento e a classificação.</li>
                <?php
                $prazo_insc = (string) ($ev['inscricoes_prazo_iso'] ?? '');
                $vagas_insc = (int) ($ev['inscricoes_vagas'] ?? 0);
                ?>
                <?php if ($prazo_insc !== '' && $vagas_insc > 0): ?>
                    <li>As inscrições vão até <?= e(data_br($prazo_insc)) ?> ou até o preenchimento das <?= $vagas_insc ?> vagas, o que ocorrer primeiro.</li>
                <?php elseif ($prazo_insc !== ''): ?>
                    <li>As inscrições vão até <?= e(data_br($prazo_insc)) ?>.</li>
                <?php elseif ($vagas_insc > 0): ?>
                    <li>As inscrições vão até o preenchimento das <?= $vagas_insc ?> vagas.</li>
                <?php endif; ?>
            </ul>

            <h2>4. Cancelamento e transferência</h2>
            <ul>
                <li>Não há reembolso do valor pago.</li>
                <li>Em caso de tempo severo, o evento poderá ser transferido para outra data, a ser definida e divulgada na semana do evento. As inscrições continuam válidas para a nova data.</li>
                <li>A inscrição pode ser transferida para outra pessoa até 12/03/2027, mediante solicitação à organização.</li>
            </ul>

            <h2>5. Idade mínima e menores de idade</h2>
            <p>Não há idade mínima. Menores de 18 anos só poderão participar com autorização expressa, por escrito, assinada pelo pai, pela mãe ou pelo responsável legal, entregue na retirada do kit.</p>

            <h2>6. Equipamentos e segurança</h2>
            <ul>
                <li>O uso de capacete é obrigatório durante todo o percurso.</li>
                <li>A bicicleta deve estar em boas condições, com freios e pneus funcionando.</li>
                <li>É dever do participante respeitar a sinalização, as orientações da organização e a legislação de trânsito.</li>
                <li>Recomenda-se levar água, documento e o contato de emergência.</li>
                <li>Todos os participantes com inscrição confirmada contam com seguro atleta durante o evento, incluído no kit.</li>
                <li>Bicicletas elétricas participam somente na categoria E-bike Hard. Não há regras de potência para as e-bikes.</li>
            </ul>

            <h2>7. Retirada do kit</h2>
            <p>A retirada é feita mediante documento com foto e comprovante de inscrição. Local, datas e horários serão divulgados na página de Programação.</p>
            <p>O kit é composto por placa de identificação, pulseira, abraçadeiras (fita Hellermann) para fixar a placa, gel energético, brindes e seguro atleta. A camiseta oficial não faz parte do kit: ela é opcional e adquirida à parte, no momento da inscrição.</p>

            <h2>8. Classificação e premiação</h2>
            <ul>
                <?php foreach ($ev['premiacoes'] as $p): ?>
                    <li>
                        <strong><?= e($p['titulo']) ?>:</strong>
                        <?= e($p['regra']) ?><?= !empty($p['premio']) ? '. ' . e($p['premio']) : '' ?>.
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>A cronometragem será feita manualmente por duas equipes da organização. Em caso de empate, vence quem tiver o melhor tempo na subida do Bosque. Para a premiação por equipes, todos os integrantes devem informar o mesmo nome de equipe na inscrição.</p>

            <h2>9. Termo de responsabilidade</h2>
            <p>Ao se inscrever, o participante declara que:</p>
            <ul>
                <li>está em boas condições de saúde e apto à prática de ciclismo na distância e na categoria escolhidas, e que foi orientado a fazer avaliação médica prévia;</li>
                <li>conhece os riscos da atividade, como quedas, terreno irregular, estrada de terra, poeira ou lama e mudanças de clima, e participa por livre e espontânea vontade;</li>
                <li>é responsável pelo próprio equipamento, inclusive pelo uso do capacete e pelas condições da bicicleta;</li>
                <li>vai respeitar a legislação de trânsito, a sinalização e as orientações da organização, e sabe que pode ser retirado do percurso em caso de comportamento que coloque em risco a si ou aos outros;</li>
                <li>autoriza a organização a providenciar atendimento de primeiros socorros e, se necessário, remoção para atendimento médico.</li>
            </ul>
            <p>Nos limites da lei, a organização, os patrocinadores e os apoiadores não se responsabilizam por acidentes, danos ou problemas de saúde decorrentes do descumprimento deste regulamento, das leis de trânsito ou de condição de saúde não informada, nem por perda, furto ou dano de equipamentos e objetos pessoais.</p>
            <p>No caso de participante menor de 18 anos, este termo é assumido pelo pai, pela mãe ou pelo responsável legal que assina a autorização.</p>

            <h2>10. Uso de imagem</h2>
            <p>O participante autoriza, de forma gratuita e sem limite de prazo ou de território, o uso de sua imagem, nome e voz captados em fotos, vídeos e transmissões do evento. O uso se destina à divulgação do evento e de suas próximas edições, das ações do <?= e($ev['organizador']) ?> e dos patrocinadores e apoiadores, em site, redes sociais, imprensa e material impresso.</p>
            <p>O participante não terá direito a remuneração por esse uso. Ele pode pedir a retirada de uma publicação específica dos canais da organização pelos contatos oficiais do evento.</p>

            <h2>11. Proteção de dados (LGPD)</h2>
            <p>O <?= e($ev['organizador']) ?> é o responsável pelos dados pessoais coletados na inscrição e os trata conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018).</p>
            <ul>
                <li><strong>Dados coletados:</strong> nome, CPF, data de nascimento, sexo, e-mail, telefone, cidade e estado, categoria, equipe, tamanho da camiseta, contato de emergência e, se informadas, observações de saúde.</li>
                <li><strong>Para que são usados:</strong> realizar e confirmar a inscrição, processar o pagamento, identificar o participante na retirada do kit, organizar as largadas, divulgar a classificação, comunicar informações do evento e prestar atendimento em caso de emergência.</li>
                <li><strong>Dados de saúde:</strong> são dados sensíveis, usados apenas para o atendimento em caso de emergência, com acesso restrito à organização.</li>
                <li><strong>Resultados:</strong> nome, categoria, equipe e colocação podem ser publicados na classificação e na premiação.</li>
                <li><strong>Compartilhamento:</strong> os dados não são vendidos. São compartilhados apenas com a empresa que processa os pagamentos, na medida necessária, e com autoridades, quando exigido por lei.</li>
                <li><strong>Guarda:</strong> os dados ficam em ambiente protegido, com acesso restrito à organização, e são mantidos por até 12 meses após o evento, salvo o que precisar ser guardado por mais tempo para cumprir obrigações legais, como os registros de pagamento.</li>
                <li><strong>Seus direitos:</strong> o participante pode pedir acesso, correção, exclusão ou informações sobre o uso dos seus dados<?= !empty($ev['contato']['email']) ? ' pelo e-mail ' . e($ev['contato']['email']) : ' pelos contatos oficiais do evento' ?>. A exclusão antes do evento implica o cancelamento da inscrição, sem reembolso.</li>
                <li><strong>Cookies:</strong> o site usa apenas os cookies necessários ao seu funcionamento. A página de rotas exibe mapas do Strava, que seguem a política de privacidade do próprio Strava.</li>
                <li><strong>Foto do “Eu vou!”:</strong> a foto usada para montar a imagem “Eu vou!” não é enviada ao site; ela é processada apenas no aparelho do participante.</li>
            </ul>

            <h2>12. Casos omissos</h2>
            <p>Situações não previstas neste regulamento serão decididas pela comissão organizadora do evento.</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
