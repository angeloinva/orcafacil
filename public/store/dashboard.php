<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_auth('store');
$storeUserId = (int) $user['id'];

$summaryStmt = db()->prepare(
    'SELECT
        COUNT(*) AS total_received,
        SUM(CASE WHEN qst.responded = 1 THEN 1 ELSE 0 END) AS responded_total
     FROM quote_store_targets qst
     WHERE qst.store_id = :store_id'
);
$summaryStmt->execute(['store_id' => $storeUserId]);
$summary = $summaryStmt->fetch() ?: ['total_received' => 0, 'responded_total' => 0];

$storeStmt = db()->prepare(
    'SELECT store_name, city, phone
     FROM stores
     WHERE user_id = :user_id
     LIMIT 1'
);
$storeStmt->execute(['user_id' => $storeUserId]);
$store = $storeStmt->fetch() ?: ['store_name' => $user['name'], 'city' => null, 'phone' => null];

$quotesStmt = db()->prepare(
    'SELECT
        q.id,
        q.title,
        q.description,
        q.status,
        q.created_at,
        u.name AS client_name,
        COUNT(DISTINCT qi.id) AS items_count,
        MAX(CASE WHEN qr.id IS NULL THEN 0 ELSE 1 END) AS has_response
     FROM quote_store_targets qst
     INNER JOIN quotes q ON q.id = qst.quote_id
     INNER JOIN users u ON u.id = q.client_id
     LEFT JOIN quote_items qi ON qi.quote_id = q.id
     LEFT JOIN quote_responses qr ON qr.quote_id = q.id AND qr.store_id = qst.store_id
     WHERE qst.store_id = :store_id
     GROUP BY q.id
     ORDER BY q.created_at DESC
     LIMIT 40'
);
$quotesStmt->execute(['store_id' => $storeUserId]);
$quotes = $quotesStmt->fetchAll();

$unread = unread_notifications_count($storeUserId);
$pending = (int) ($summary['total_received'] ?? 0) - (int) ($summary['responded_total'] ?? 0);

render_header('Painel da loja', $user);
?>
<section class="card chat-hero">
    <div>
        <h2><?= e($store['store_name']) ?></h2>
        <p>Gerencie os orçamentos recebidos e envie propostas com agilidade.</p>
    </div>
    <div class="button-stack">
        <a class="btn btn-outline" href="/store/profile.php">Perfil da loja</a>
        <a class="btn btn-outline" href="/notifications.php">
            Notificações<?= $unread > 0 ? ' (' . e((string) $unread) . ')' : '' ?>
        </a>
    </div>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <strong><?= e((string) ($summary['total_received'] ?? 0)) ?></strong>
        <span>Orçamentos recebidos</span>
    </article>
    <article class="stat-card">
        <strong><?= e((string) max($pending, 0)) ?></strong>
        <span>Aguardando resposta</span>
    </article>
    <article class="stat-card">
        <strong><?= e((string) ($summary['responded_total'] ?? 0)) ?></strong>
        <span>Respondidos</span>
    </article>
</section>

<section class="card">
    <h2>Fila de orçamentos</h2>
    <?php if (!$quotes): ?>
        <p>Sem orçamentos no momento.</p>
    <?php else: ?>
        <ul class="chat-list">
            <?php foreach ($quotes as $quote): ?>
                <li class="chat-list-item">
                    <a href="/store/quote.php?id=<?= e((string) $quote['id']) ?>">
                        <div class="chat-list-header">
                            <strong><?= e($quote['title']) ?></strong>
                            <span><?= e((string) $quote['created_at']) ?></span>
                        </div>
                        <p>Cliente: <?= e($quote['client_name']) ?> · Itens: <?= e((string) $quote['items_count']) ?></p>
                        <small>
                            <?= (int) $quote['has_response'] === 1 ? 'Proposta enviada' : 'Aguardando envio de proposta' ?>
                            · Status do pedido: <?= $quote['status'] === 'open' ? 'aberto' : 'fechado' ?>
                        </small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
