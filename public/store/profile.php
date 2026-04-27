<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_auth('store');
$storeUserId = (int) $user['id'];

$storeStmt = db()->prepare(
    'SELECT store_name, city, phone
     FROM stores
     WHERE user_id = :user_id
     LIMIT 1'
);
$storeStmt->execute(['user_id' => $storeUserId]);
$store = $storeStmt->fetch();

if (!$store) {
    set_flash('error', 'Perfil da loja não encontrado.');
    redirect('/store/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token inválido.');
        redirect('/store/profile.php');
    }

    $storeName = trim((string) ($_POST['store_name'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if ($storeName === '') {
        set_flash('error', 'O nome da loja é obrigatório.');
        redirect('/store/profile.php');
    }

    $updateStore = db()->prepare(
        'UPDATE stores
         SET store_name = :store_name, city = :city, phone = :phone
         WHERE user_id = :user_id'
    );
    $updateStore->execute([
        'store_name' => $storeName,
        'city' => $city !== '' ? $city : null,
        'phone' => $phone !== '' ? $phone : null,
        'user_id' => $storeUserId,
    ]);

    set_flash('success', 'Dados da loja atualizados.');
    redirect('/store/profile.php');
}

render_header('Perfil da loja', $user);
?>
<section class="card">
    <div class="section-head">
        <h2>Perfil da loja</h2>
        <a class="btn btn-outline" href="/store/dashboard.php">Voltar</a>
    </div>
    <form method="post" class="form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            Nome da loja
            <input type="text" name="store_name" required value="<?= e($store['store_name']) ?>">
        </label>
        <label>
            Cidade
            <input type="text" name="city" value="<?= e((string) ($store['city'] ?? '')) ?>" placeholder="São Paulo">
        </label>
        <label>
            Telefone
            <input type="text" name="phone" value="<?= e((string) ($store['phone'] ?? '')) ?>" placeholder="(11) 99999-9999">
        </label>
        <button class="btn btn-primary" type="submit">Salvar alterações</button>
    </form>
</section>
<?php render_footer(); ?>
