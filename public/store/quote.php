<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_auth('store');
$storeUserId = (int) $user['id'];
$quoteId = (int) ($_GET['id'] ?? 0);

if ($quoteId <= 0) {
    set_flash('error', 'Orçamento inválido.');
    redirect('/store/dashboard.php');
}

$quoteStmt = db()->prepare(
    'SELECT
        q.id,
        q.title,
        q.description,
        q.status,
        q.created_at,
        u.id AS client_id,
        u.name AS client_name,
        u.email AS client_email
     FROM quote_store_targets qst
     INNER JOIN quotes q ON q.id = qst.quote_id
     INNER JOIN users u ON u.id = q.client_id
     WHERE q.id = :quote_id AND qst.store_id = :store_id
     LIMIT 1'
);
$quoteStmt->execute([
    'quote_id' => $quoteId,
    'store_id' => $storeUserId,
]);
$quote = $quoteStmt->fetch();

if (!$quote) {
    set_flash('error', 'Este orçamento não foi encontrado para sua loja.');
    redirect('/store/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token inválido.');
        redirect('/store/quote.php?id=' . $quoteId);
    }

    $totalAmount = (float) ($_POST['total_amount'] ?? 0);
    $deliveryDeadline = trim((string) ($_POST['delivery_deadline'] ?? ''));
    $paymentTerms = trim((string) ($_POST['payment_terms'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($totalAmount <= 0 || $deliveryDeadline === '' || $paymentTerms === '') {
        set_flash('error', 'Preencha total, prazo de entrega e condições de pagamento.');
        redirect('/store/quote.php?id=' . $quoteId);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $upsertResponse = $pdo->prepare(
            'INSERT INTO quote_responses
                (quote_id, store_id, total_amount, delivery_deadline, payment_terms, message, created_at, updated_at)
             VALUES
                (:quote_id, :store_id, :total_amount, :delivery_deadline, :payment_terms, :message, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                total_amount = VALUES(total_amount),
                delivery_deadline = VALUES(delivery_deadline),
                payment_terms = VALUES(payment_terms),
                message = VALUES(message),
                updated_at = NOW()'
        );
        $upsertResponse->execute([
            'quote_id' => $quoteId,
            'store_id' => $storeUserId,
            'total_amount' => $totalAmount,
            'delivery_deadline' => $deliveryDeadline,
            'payment_terms' => $paymentTerms,
            'message' => $message !== '' ? $message : null,
        ]);

        $updateTarget = $pdo->prepare(
            'UPDATE quote_store_targets
             SET responded = 1
             WHERE quote_id = :quote_id AND store_id = :store_id'
        );
        $updateTarget->execute([
            'quote_id' => $quoteId,
            'store_id' => $storeUserId,
        ]);

        create_notification(
            (int) $quote['client_id'],
            sprintf(
                'A loja %s respondeu seu orçamento "%s".',
                $user['name'],
                $quote['title']
            )
        );

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        set_flash('error', 'Erro ao enviar proposta. Tente novamente.');
        redirect('/store/quote.php?id=' . $quoteId);
    }

    set_flash('success', 'Proposta enviada com sucesso.');
    redirect('/store/quote.php?id=' . $quoteId);
}

$itemsStmt = db()->prepare(
    'SELECT item_name, quantity, unit
     FROM quote_items
     WHERE quote_id = :quote_id
     ORDER BY id ASC'
);
$itemsStmt->execute(['quote_id' => $quoteId]);
$items = $itemsStmt->fetchAll();

$responseStmt = db()->prepare(
    'SELECT total_amount, delivery_deadline, payment_terms, message, created_at, updated_at
     FROM quote_responses
     WHERE quote_id = :quote_id AND store_id = :store_id
     LIMIT 1'
);
$responseStmt->execute([
    'quote_id' => $quoteId,
    'store_id' => $storeUserId,
]);
$existingResponse = $responseStmt->fetch();

render_header('Responder orçamento', $user);
?>
<section class="card">
    <div class="section-head">
        <h2><?= e($quote['title']) ?></h2>
        <a class="btn btn-outline" href="/store/dashboard.php">Voltar</a>
    </div>
    <p><?= e((string) ($quote['description'] ?: 'Sem observações adicionais.')) ?></p>
    <div class="chip-row">
        <span class="chip">Cliente: <?= e($quote['client_name']) ?></span>
        <span class="chip">Status do pedido: <?= $quote['status'] === 'open' ? 'aberto' : 'fechado' ?></span>
        <span class="chip">Recebido em: <?= e((string) $quote['created_at']) ?></span>
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
    <h3><?= $existingResponse ? 'Atualizar proposta' : 'Enviar proposta' ?></h3>
    <form method="post" class="form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            Valor total (R$)
            <input
                type="number"
                step="0.01"
                min="0.01"
                name="total_amount"
                required
                value="<?= e($existingResponse ? (string) $existingResponse['total_amount'] : '') ?>"
                placeholder="2500.00"
            >
        </label>
        <label>
            Prazo de entrega
            <input
                type="text"
                name="delivery_deadline"
                required
                value="<?= e($existingResponse ? $existingResponse['delivery_deadline'] : '') ?>"
                placeholder="3 dias úteis"
            >
        </label>
        <label>
            Condições de pagamento
            <input
                type="text"
                name="payment_terms"
                required
                value="<?= e($existingResponse ? $existingResponse['payment_terms'] : '') ?>"
                placeholder="Pix à vista ou 3x cartão"
            >
        </label>
        <label>
            Mensagem adicional
            <textarea name="message" rows="4" placeholder="Detalhes extras para o cliente"><?= e($existingResponse ? (string) $existingResponse['message'] : '') ?></textarea>
        </label>
        <button class="btn btn-primary" type="submit">
            <?= $existingResponse ? 'Atualizar proposta' : 'Enviar proposta' ?>
        </button>
    </form>

    <?php if ($existingResponse): ?>
        <p class="muted">
            Proposta criada em <?= e((string) $existingResponse['created_at']) ?> e atualizada em
            <?= e((string) $existingResponse['updated_at']) ?>.
        </p>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
