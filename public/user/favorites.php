<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_auth('client');
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token inválido.');
        redirect('/user/favorites.php');
    }

    $storeId = (int) ($_POST['store_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($storeId <= 0 || !in_array($action, ['add', 'remove'], true)) {
        set_flash('error', 'Operação inválida.');
        redirect('/user/favorites.php');
    }

    if ($action === 'add') {
        $insert = db()->prepare(
            'INSERT IGNORE INTO favorites (client_id, store_id, created_at) VALUES (:client_id, :store_id, NOW())'
        );
        $insert->execute([
            'client_id' => $userId,
            'store_id' => $storeId,
        ]);
        set_flash('success', 'Loja adicionada aos favoritos.');
    } else {
        $delete = db()->prepare(
            'DELETE FROM favorites WHERE client_id = :client_id AND store_id = :store_id'
        );
        $delete->execute([
            'client_id' => $userId,
            'store_id' => $storeId,
        ]);
        set_flash('success', 'Loja removida dos favoritos.');
    }

    redirect('/user/favorites.php');
}

$storesStmt = db()->prepare(
    'SELECT
        u.id,
        s.store_name,
        s.city,
        s.phone,
        CASE WHEN f.id IS NULL THEN 0 ELSE 1 END AS is_favorite
     FROM users u
     INNER JOIN stores s ON s.user_id = u.id
     LEFT JOIN favorites f ON f.store_id = u.id AND f.client_id = :client_id
     WHERE u.role = "store"
     ORDER BY s.store_name ASC'
);
$storesStmt->execute(['client_id' => $userId]);
$stores = $storesStmt->fetchAll();

render_header('Lojas favoritas', $user);
?>
<section class="card">
    <div class="section-head">
        <h2>Gerenciar favoritas</h2>
        <a class="btn btn-outline" href="/user/dashboard.php">Voltar</a>
    </div>

    <?php if (!$stores): ?>
        <p>Nenhuma loja cadastrada no momento.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($stores as $store): ?>
                <li class="list-item">
                    <div>
                        <strong><?= e($store['store_name']) ?></strong>
                        <p><?= e((string) ($store['city'] ?: 'Cidade não informada')) ?></p>
                        <small><?= e((string) ($store['phone'] ?: 'Telefone não informado')) ?></small>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="store_id" value="<?= e((string) $store['id']) ?>">
                        <?php if ((int) $store['is_favorite'] === 1): ?>
                            <input type="hidden" name="action" value="remove">
                            <button class="btn btn-outline" type="submit">Remover</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="add">
                            <button class="btn btn-primary" type="submit">Favoritar</button>
                        <?php endif; ?>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
