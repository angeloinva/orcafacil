<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (is_authenticated()) {
    $current = current_user();
    redirect($current['role'] === 'client' ? '/user/dashboard.php' : '/store/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token inválido. Tente novamente.');
        redirect('/register.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? '');
    $storeName = trim((string) ($_POST['store_name'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if ($name === '' || $email === '' || $password === '' || !in_array($role, ['client', 'store'], true)) {
        set_flash('error', 'Preencha todos os campos corretamente.');
        redirect('/register.php');
    }

    if (strlen($password) < 6) {
        set_flash('error', 'A senha deve ter ao menos 6 caracteres.');
        redirect('/register.php');
    }

    if ($role === 'store' && $storeName === '') {
        set_flash('error', 'Informe o nome da loja.');
        redirect('/register.php');
    }

    $exists = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists->execute(['email' => $email]);
    if ($exists->fetch()) {
        set_flash('error', 'Este e-mail já está cadastrado.');
        redirect('/register.php');
    }

    $insertUser = db()->prepare(
        'INSERT INTO users (name, email, password_hash, role, created_at) VALUES (:name, :email, :password_hash, :role, NOW())'
    );
    $insertUser->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    $userId = (int) db()->lastInsertId();

    if ($role === 'store') {
        $insertStore = db()->prepare(
            'INSERT INTO stores (user_id, store_name, city, phone, created_at) VALUES (:user_id, :store_name, :city, :phone, NOW())'
        );
        $insertStore->execute([
            'user_id' => $userId,
            'store_name' => $storeName,
            'city' => $city !== '' ? $city : null,
            'phone' => $phone !== '' ? $phone : null,
        ]);
    }

    login_user([
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ]);

    set_flash('success', 'Conta criada com sucesso!');
    redirect($role === 'client' ? '/user/dashboard.php' : '/store/dashboard.php');
}

render_header('Criar conta');
?>
<section class="card">
    <h2>Cadastre-se</h2>
    <form method="post" class="form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            Nome
            <input type="text" name="name" required placeholder="Seu nome">
        </label>
        <label>
            E-mail
            <input type="email" name="email" required placeholder="voce@email.com">
        </label>
        <label>
            Senha
            <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
        </label>
        <label>
            Perfil
            <select name="role" id="role" required>
                <option value="client">Usuário final</option>
                <option value="store">Loja</option>
            </select>
        </label>

        <div class="store-only">
            <label>
                Nome da loja
                <input type="text" name="store_name" placeholder="Loja Exemplo">
            </label>
            <label>
                Cidade
                <input type="text" name="city" placeholder="São Paulo">
            </label>
            <label>
                Telefone
                <input type="text" name="phone" placeholder="(11) 99999-9999">
            </label>
        </div>

        <button class="btn btn-primary" type="submit">Criar conta</button>
    </form>
</section>

<script>
const roleSelect = document.getElementById('role');
const storeOnlySection = document.querySelector('.store-only');
const toggleStoreFields = () => {
    const isStore = roleSelect.value === 'store';
    storeOnlySection.style.display = isStore ? 'grid' : 'none';
    storeOnlySection.querySelectorAll('input').forEach((input) => {
        input.required = isStore && input.name === 'store_name';
    });
};
roleSelect.addEventListener('change', toggleStoreFields);
toggleStoreFields();
</script>
<?php render_footer(); ?>
