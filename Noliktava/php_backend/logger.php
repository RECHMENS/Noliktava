<?php
function logAction(PDO $conn, ?int $userId, string $action, string $details = '', ?string $username = null): void {
    try {
        if ($username === null && $userId !== null) {
            $stmt = $conn->prepare('SELECT `lietotajvārds` FROM lietotaju_registrs WHERE lietotaji_id = ?');
            $stmt->execute([$userId]);
            $found = $stmt->fetchColumn();
            $username = $found === false ? null : (string)$found;
        }
        $stmt = $conn->prepare(
            'INSERT INTO zurnals (lietotaji_id, `lietotajvards`, darbiba, detalas, ip)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $username,
            $action,
            mb_substr($details, 0, 1000),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Throwable $e) {
        error_log('Log write failed: ' . $e->getMessage());
    }
}
