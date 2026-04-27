<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_auth('client');
$userId = (int) $user['id'];

$storesStmt = db()->query(
    'SELECT u.id, s.store_name, s.city
     FROM users u
     INNER JOIN stores s ON s.user_id = u.id
     WHERE u.role = "store"
     ORDER BY s.store_name ASC'
);
$stores = $storesStmt->fetchAll();

$favoritesStmt = db()->prepare('SELECT store_id FROM favorites WHERE client_id = :client_id');
$favoritesStmt->execute(['client_id' => $userId]);
$favoriteIds = array_map('intval', array_column($favoritesStmt->fetchAll(), 'store_id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token inválido.');
        redirect('/user/create_quote.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $targetType = (string) ($_POST['target_type'] ?? 'all');

    $itemNames = $_POST['item_name'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $units = $_POST['unit'] ?? [];

    if ($title === '' || !in_array($targetType, ['all', 'favorites'], true)) {
        set_flash('error', 'Preencha os dados do orçamento corretamente.');
        redirect('/user/create_quote.php');
    }

    $items = [];
    foreach ($itemNames as $index => $itemName) {
        $name = trim((string) $itemName);
        $qty = isset($quantities[$index]) ? (float) $quantities[$index] : 0;
        $unit = trim((string) ($units[$index] ?? ''));

        if ($name === '' || $qty <= 0 || $unit === '') {
            continue;
        }

        $items[] = [
            'item_name' => $name,
            'quantity' => $qty,
            'unit' => $unit,
        ];
    }

    if (!$items) {
        set_flash('error', 'Adicione ao menos um item válido.');
        redirect('/user/create_quote.php');
    }

    if ($targetType === 'favorites' && !$favoriteIds) {
        set_flash('error', 'Você não possui lojas favoritas para enviar este orçamento.');
        redirect('/user/favorites.php');
    }

    $targetStoreIds = [];
    if ($targetType === 'all') {
        $targetStoreIds = array_map('intval', array_column($stores, 'id'));
    } else {
        $targetStoreIds = $favoriteIds;
    }

    if (!$targetStoreIds) {
        set_flash('error', 'Não há lojas disponíveis para receber este orçamento.');
        redirect('/user/create_quote.php');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $insertQuote = $pdo->prepare(
            'INSERT INTO quotes (client_id, title, description, target_type, status, created_at)
             VALUES (:client_id, :title, :description, :target_type, "open", NOW())'
        );
        $insertQuote->execute([
            'client_id' => $userId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'target_type' => $targetType,
        ]);

        $quoteId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO quote_items (quote_id, item_name, quantity, unit, created_at)
             VALUES (:quote_id, :item_name, :quantity, :unit, NOW())'
        );
        foreach ($items as $item) {
            $insertItem->execute([
                'quote_id' => $quoteId,
                'item_name' => $item['item_name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
            ]);
        }

        $insertTarget = $pdo->prepare(
            'INSERT INTO quote_store_targets (quote_id, store_id, responded, created_at)
             VALUES (:quote_id, :store_id, 0, NOW())'
        );
        foreach ($targetStoreIds as $storeId) {
            $insertTarget->execute([
                'quote_id' => $quoteId,
                'store_id' => $storeId,
            ]);

            create_notification(
                $storeId,
                sprintf('Novo orçamento recebido: "%s".', $title)
            );
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        set_flash('error', 'Não foi possível criar o orçamento. Tente novamente.');
        redirect('/user/create_quote.php');
    }

    set_flash('success', 'Orçamento enviado com sucesso para as lojas selecionadas.');
    redirect('/user/quote_details.php?id=' . $quoteId);
}

render_header('Novo orçamento', $user);
?>
<section class="card">
    <h2>Novo orçamento</h2>
    <p>Informe os materiais e escolha se deseja enviar para todas as lojas ou apenas favoritas.</p>

    <form method="post" class="form" id="quote-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label>
            Título do orçamento
            <input type="text" name="title" required placeholder="Ex.: Reforma da cozinha">
        </label>

        <label>
            Observações (opcional)
            <textarea name="description" rows="3" placeholder="Detalhes importantes do pedido"></textarea>
        </label>

        <label>
            Enviar para
            <select name="target_type" required>
                <option value="all">Todas as lojas</option>
                <option value="favorites">Somente favoritas</option>
            </select>
        </label>

        <div class="chip-row">
            <span class="chip">Lojas disponíveis: <?= e((string) count($stores)) ?></span>
            <span class="chip">Favoritas: <?= e((string) count($favoriteIds)) ?></span>
        </div>

        <div id="items-wrapper" class="items-wrapper">
            <div class="item-card">
                <label>Item
                    <input type="text" name="item_name[]" required placeholder="Cimento CP II">
                </label>
                <div class="grid-2">
                    <label>Quantidade
                        <input type="number" step="0.01" min="0.01" name="quantity[]" required placeholder="10">
                    </label>
                    <label>Unidade
                        <input type="text" name="unit[]" required placeholder="sacos">
                    </label>
                </div>
            </div>
        </div>

        <div class="button-stack">
            <button type="button" id="add-item" class="btn btn-outline">Adicionar item</button>
            <button type="submit" class="btn btn-primary">Enviar orçamento</button>
        </div>
    </form>
</section>

<script>
const wrapper = document.getElementById('items-wrapper');
const addItemButton = document.getElementById('add-item');
addItemButton.addEventListener('click', () => {
    const card = document.createElement('div');
    card.className = 'item-card';
    card.innerHTML = `
        <label>Item
            <input type="text" name="item_name[]" required placeholder="Areia média">
        </label>
        <div class="grid-2">
            <label>Quantidade
                <input type="number" step="0.01" min="0.01" name="quantity[]" required placeholder="5">
            </label>
            <label>Unidade
                <input type="text" name="unit[]" required placeholder="m3">
            </label>
        </div>
    `;
    wrapper.appendChild(card);
});
</script>
<?php render_footer(); ?>
