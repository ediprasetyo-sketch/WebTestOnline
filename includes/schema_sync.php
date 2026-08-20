<?php
declare(strict_types=1);

/**
 * Idempotent database compatibility checks.
 * This file is updateable independently from config.php, so ZIP updates never
 * depend on replacing environment/database credentials.
 */
if (!function_exists('ensure_matrix_disc_schema')) {
    function ensure_matrix_disc_schema(?PDO $pdo=null): void {
        static $done = false;
        if ($done) return;
        $pdo ??= db();

        $qcols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM questions") as $row) {
            $qcols[(string)$row['Field']] = $row;
        }

        if (isset($qcols['type'])) {
            $type = strtolower((string)($qcols['type']['Type'] ?? ''));
            if (!str_contains($type, "'matrix_disc'")) {
                $pdo->exec("ALTER TABLE questions MODIFY type ENUM('mcq','essay','matrix_disc') NOT NULL DEFAULT 'mcq'");
            }
        }

        if (!isset($qcols['matrix_correct_mirip'])) {
            $pdo->exec("ALTER TABLE questions ADD COLUMN matrix_correct_mirip CHAR(1) NULL AFTER correct_option");
        }

        $qcols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM questions") as $row) {
            $qcols[(string)$row['Field']] = true;
        }
        if (!isset($qcols['matrix_correct_tidak'])) {
            $pdo->exec("ALTER TABLE questions ADD COLUMN matrix_correct_tidak CHAR(1) NULL AFTER matrix_correct_mirip");
        }

        $acols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM answers") as $row) {
            $acols[(string)$row['Field']] = true;
        }
        if (!isset($acols['matrix_answer'])) {
            $pdo->exec("ALTER TABLE answers ADD COLUMN matrix_answer JSON NULL AFTER essay_answer");
        }

        $done = true;
    }
}
