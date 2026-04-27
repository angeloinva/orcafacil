<?php

declare(strict_types=1);

function render_header(string $title, ?array $user = null): void
{
    $flash = get_flash();
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <title><?= e($title) ?> | OrcaFacil</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
    <div class="app-shell">
        <header class="topbar">
            <div>
                <h1 class="brand">OrcaFacil</h1>
                <p class="subtitle">Orçamentos de obras em tempo real</p>
            </div>
            <?php if ($user): ?>
                <a class="btn btn-outline" href="/logout.php">Sair</a>
            <?php endif; ?>
        </header>
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>
        <main class="content">
    <?php
}

function render_footer(): void
{
    ?>
        </main>
    </div>
    </body>
    </html>
    <?php
}
