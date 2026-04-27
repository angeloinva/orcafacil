<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token inválido.');
        redirect('/notifications.php');
    }

    $markRead = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id');
    $markRead->execute(['user_id' => (int) $user['id']]);
    set_flash('success', 'Notificações marcadas como lidas.');
    redirect('/notifications.php');
}

$statement = db()->prepare(
    'SELECT id, message, is_read, created_at
     FROM notifications
     WHERE user_id = :user_id
     ORDER BY created_at DESC
     LIMIT 100'
);
$statement->execute(['user_id' => (int) $user['id']]);
$notifications = $statement->fetchAll();

render_header('Notificações', $user);
?>
<section class="card">
    <div class="section-head">
        <h2>Notificações</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-outline">Marcar todas como lidas</button>
        </form>
    </div>

    <?php if (!$notifications): ?>
        <p>Nenhuma notificação por enquanto.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($notifications as $notification): ?>
                <li class="list-item <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>">
                    <p><?= e($notification['message']) ?></p>
                    <small><?= e((string) $notification['created_at']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
