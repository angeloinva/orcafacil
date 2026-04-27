<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_auth('client');
$userId = (int) $user['id'];

$summaryStmt = db()->prepare(
    'SELECT
        COUNT(*) AS total_quotes,
        SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) AS open_quotes
     FROM quotes
     WHERE client_id = :client_id'
);
$summaryStmt->execute(['client_id' => $userId]);
$summary = $summaryStmt->fetch() ?: ['total_quotes' => 0, 'open_quotes' => 0];

$quotesStmt = db()->prepare(
    'SELECT
        q.id,
        q.title,
        q.status,
        q.target_type,
        q.created_at,
        COUNT(DISTINCT qst.store_id) AS stores_targeted,
        COUNT(DISTINCT qr.id) AS responses_count
     FROM quotes q
     LEFT JOIN quote_store_targets qst ON qst.quote_id = q.id
     LEFT JOIN quote_responses qr ON qr.quote_id = q.id
     WHERE q.client_id = :client_id
     GROUP BY q.id
     ORDER BY q.created_at DESC
     LIMIT 30'
);
$quotesStmt->execute(['client_id' => $userId]);
$quotes = $quotesStmt->fetchAll();

$unread = unread_notifications_count($userId);

render_header('Painel do cliente', $user);
?>
<section class="card chat-hero">
    <div>
        <h2>Olá, <?= e($user['name']) ?>!</h2>
        <p>Crie um orçamento em poucos passos e acompanhe as respostas das lojas.</p>
    </div>
    <div class="button-stack">
        <a class="btn btn-primary" href="/user/create_quote.php">Novo orçamento</a>
        <a class="btn btn-outline" href="/notifications.php">
            Notificações<?= $unread > 0 ? ' (' . e((string) $unread) . ')' : '' ?>
        </a>
    </div>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <strong><?= e((string) ($summary['total_quotes'] ?? 0)) ?></strong>
        <span>Orçamentos criados</span>
    </article>
    <article class="stat-card">
        <strong><?= e((string) ($summary['open_quotes'] ?? 0)) ?></strong>
        <span>Orçamentos em aberto</span>
    </article>
    <article class="stat-card">
        <strong><?= e((string) $unread) ?></strong>
        <span>Novas notificações</span>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h2>Minhas conversas de orçamento</h2>
        <a class="btn btn-outline" href="/user/favorites.php">Lojas favoritas</a>
    </div>

    <?php if (!$quotes): ?>
        <p>Você ainda não criou orçamentos. Toque em "Novo orçamento" para começar.</p>
    <?php else: ?>
        <ul class="chat-list">
            <?php foreach ($quotes as $quote): ?>
                <li class="chat-list-item">
                    <a href="/user/quote_details.php?id=<?= e((string) $quote['id']) ?>">
                        <div class="chat-list-header">
                            <strong><?= e($quote['title']) ?></strong>
                            <span><?= e((string) $quote['created_at']) ?></span>
                        </div>
                        <p>
                            <?= (int) $quote['responses_count'] ?> respostas de
                            <?= (int) $quote['stores_targeted'] ?> lojas alvo
                            · envio: <?= $quote['target_type'] === 'favorites' ? 'favoritas' : 'todas' ?>
                        </p>
                        <small>Status: <?= $quote['status'] === 'open' ? 'aberto' : 'fechado' ?></small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
