<?php

declare(strict_types=1);

function unread_notifications_count(int $userId): int
{
    $statement = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}

function create_notification(int $userId, string $message): void
{
    $statement = db()->prepare(
        'INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 0, NOW())'
    );
    $statement->execute([
        'user_id' => $userId,
        'message' => $message,
    ]);
}
