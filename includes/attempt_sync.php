<?php
declare(strict_types=1);

/**
 * Shared attempt status synchronization.
 * This helper is explicitly included by every admin/API entry point that needs it.
 */
if (!function_exists('sync_attempt_statuses')) {
    function sync_attempt_statuses(?PDO $pdo=null, ?int $examId=null): int {
        $pdo ??= db();

        $where = "a.status='active' AND a.started_at IS NOT NULL";
        $params = [];
        if ($examId !== null && $examId > 0) {
            $where .= " AND a.exam_id=?";
            $params[] = $examId;
        }

        $deadlineExpr = "LEAST(DATE_ADD(a.started_at, INTERVAL e.duration_seconds SECOND), e.end_at)";

        $repair = $pdo->prepare(
            "UPDATE attempts a
             JOIN exams e ON e.id=a.exam_id
             SET a.deadline_at={$deadlineExpr}
             WHERE {$where}
               AND (a.deadline_at IS NULL OR a.deadline_at <> {$deadlineExpr})"
        );
        $repair->execute($params);

        $expire = $pdo->prepare(
            "UPDATE attempts a
             JOIN exams e ON e.id=a.exam_id
             SET a.status='expired',
                 a.submitted_at=COALESCE(a.submitted_at, {$deadlineExpr}),
                 a.deadline_at={$deadlineExpr}
             WHERE {$where} AND {$deadlineExpr} <= NOW()"
        );
        $expire->execute($params);

        return $expire->rowCount();
    }
}
