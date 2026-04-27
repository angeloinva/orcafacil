<?php

declare(strict_types=1);

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;
    static $cachedUserId = null;

    $sessionUserId = (int) $_SESSION['user_id'];

    if ($cachedUserId === $sessionUserId && is_array($cachedUser)) {
        return $cachedUser;
    }

    $statement = db()->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
    $statement->execute(['id' => $sessionUserId]);
    $user = $statement->fetch();

    if (!$user) {
        logout();
        return null;
    }

    $cachedUserId = $sessionUserId;
    $cachedUser = $user;

    return $cachedUser;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function require_auth(?string $role = null): array
{
    $user = current_user();

    if (!$user) {
        set_flash('error', 'Faça login para continuar.');
        redirect('/login.php');
    }

    if ($role !== null && $user['role'] !== $role) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    return $user;
}

function login_user(array $user): void
{
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
