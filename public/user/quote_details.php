<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_auth('client');
$userId = (int) $user['id'];
$quoteId = (int) ($_GET['id'] ?? 0);

if ($quoteId <= 0) {
    set_flash('error', 'Orçamento inválido.');
    redirect('/user/dashboard.php');
}

$quoteStmt = db()->prepare(
    'SELECT id, title, description, status, target_type, created_at
     FROM quotes
     WHERE id = :id AND client_id = :client_id
     LIMIT 1'
);
$quoteStmt->execute([
    'id' => $quoteId,
    'client_id' => $userId,
]);
$quote = $quoteStmt->fetch();

if (!$quote) {
    set_flash('error', 'Orçamento não encontrado.');
    redirect('/user/dashboard.php');
}

$itemsStmt = db()->prepare(
    'SELECT item_name, quantity, unit
     FROM quote_items
     WHERE quote_id = :quote_id
     ORDER BY id ASC'
);
$itemsStmt->execute(['quote_id' => $quoteId]);
$items = $itemsStmt->fetchAll();

$responsesStmt = db()->prepare(
    'SELECT
        qr.total_amount,
        qr.delivery_deadline,
        qr.payment_terms,
        qr.message,
        qr.created_at,
        s.store_name,
        s.city
     FROM quote_responses qr
     INNER JOIN stores s ON s.user_id = qr.store_id
     WHERE qr.quote_id = :quote_id
     ORDER BY qr.created_at DESC'
);
$responsesStmt->execute(['quote_id' => $quoteId]);
$responses = $responsesStmt->fetchAll();

$targetsStmt = db()->prepare('SELECT COUNT(*) FROM quote_store_targets WHERE quote_id = :quote_id');
$targetsStmt->execute(['quote_id' => $quoteId]);
$targetCount = (int) $targetsStmt->fetchColumn();

render_header('Detalhes do orçamento', $user);
?>
<section class="card">
    <div class="section-head">
        <h2><?= e($quote['title']) ?></h2>
        <a class="btn btn-outline" href="/user/dashboard.php">Voltar</a>
    </div>
    <p><?= e((string) ($quote['description'] ?: 'Sem observações adicionais.')) ?></p>
    <div class="chip-row">
        <span class="chip">Status: <?= $quote['status'] === 'open' ? 'aberto' : 'fechado' ?></span>
        <span class="chip">Envio: <?= $quote['target_type'] === 'favorites' ? 'favoritas' : 'todas' ?></span>
        <span class="chip">Lojas alvo: <?= e((string) $targetCount) ?></span>
    </div>
</section>

<section class="card">
    <h3>Itens solicitados</h3>
    <ul class="list">
        <?php foreach ($items as $item): ?>
            <li class="list-item">
                <strong><?= e($item['item_name']) ?></strong>
                <span><?= e((string) $item['quantity']) . ' ' . e($item['unit']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="card">
    <h3>Respostas das lojas (<?= e((string) count($responses)) ?>)</h3>
    <?php if (!$responses): ?>
        <p>Nenhuma loja respondeu ainda. Você será notificado quando chegarem propostas.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($responses as $response): ?>
                <li class="list-item proposal-item">
                    <div>
                        <strong><?= e($response['store_name']) ?></strong>
                        <p><?= e((string) ($response['city'] ?: 'Cidade não informada')) ?></p>
                    </div>
                    <div class="proposal-content">
                        <p><strong>Total:</strong> R$ <?= e(number_format((float) $response['total_amount'], 2, ',', '.')) ?></p>
                        <p><strong>Prazo de entrega:</strong> <?= e($response['delivery_deadline']) ?></p>
                        <p><strong>Pagamento:</strong> <?= e($response['payment_terms']) ?></p>
                        <p><strong>Mensagem:</strong> <?= e((string) ($response['message'] ?: 'Sem mensagem adicional.')) ?></p>
                        <small><?= e((string) $response['created_at']) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
