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
        redirect('/login.php');
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        set_flash('error', 'Credenciais inválidas.');
        redirect('/login.php');
    }

    login_user($user);
    set_flash('success', 'Login realizado com sucesso!');
    redirect($user['role'] === 'client' ? '/user/dashboard.php' : '/store/dashboard.php');
}

render_header('Entrar');
?>
<section class="card">
    <h2>Acesse sua conta</h2>
    <form method="post" class="form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            E-mail
            <input type="email" name="email" required placeholder="voce@email.com">
        </label>
        <label>
            Senha
            <input type="password" name="password" required placeholder="********">
        </label>
        <button class="btn btn-primary" type="submit">Entrar</button>
    </form>
</section>
<?php render_footer(); ?>
