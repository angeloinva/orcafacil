<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user();

if ($user) {
    if ($user['role'] === 'client') {
        redirect('/user/dashboard.php');
    }

    redirect('/store/dashboard.php');
}

render_header('Bem-vindo');
?>
<section class="card">
    <h2>Gerenciador de Orçamentos de Obras</h2>
    <p>
        Crie solicitações de materiais e receba propostas de lojas de construção em um fluxo simples e rápido.
    </p>
    <div class="button-stack">
        <a class="btn btn-primary" href="/login.php">Entrar</a>
        <a class="btn btn-outline" href="/register.php">Criar conta</a>
    </div>
</section>
<?php render_footer(); ?>
