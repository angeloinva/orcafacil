<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

logout();
set_flash('success', 'Sessão encerrada com sucesso.');
redirect('/login.php');
