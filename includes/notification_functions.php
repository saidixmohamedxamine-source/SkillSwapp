<?php

/**
 * Insert a notification for a user.
 */
function sendNotification(PDO $pdo, int $user_id, string $type, string $message, string $link = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$user_id, $type, $message, $link]);
}
