<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/participant_session.php';
require_once __DIR__ . '/../includes/attempt_sync.php';

function api_auth(): void { require_participant(); }
function participant_id(): int { $p=require_participant(); return (int)$p['id']; }

function body(): array {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function check_api_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        json_response(['error' => 'CSRF'], 419);
    }
}

/**
 * Recalculate an attempt deadline from the immutable started_at timestamp,
 * the exam duration (stored in seconds), and the exam end_at.
 *
 * This also repairs old active attempts whose deadline was accidentally
 * stored with the wrong unit/value. Refreshing the page can never add time.
 */
function normalize_attempt_deadline(array $attempt): array {
    $startedTs = strtotime((string)$attempt['started_at']);
    $storedDeadline = strtotime((string)$attempt['deadline_at']);
    $examDuration = (int)($attempt['exam_duration_seconds'] ?? 0);
    $examEndTs = strtotime((string)($attempt['exam_end_at'] ?? ''));

    if ($startedTs === false || $examDuration < 1) {
        return $attempt;
    }

    $calculated = $startedTs + $examDuration;
    if ($examEndTs !== false && $examEndTs > 0) {
        $calculated = min($calculated, $examEndTs);
    }

    // Repair only when the stored deadline is different.
    if ($storedDeadline !== $calculated) {
        $pdo = db();
        $u = $pdo->prepare("UPDATE attempts SET deadline_at=FROM_UNIXTIME(?) WHERE id=? AND status='active'");
        $u->execute([$calculated, (int)$attempt['id']]);
        $attempt['deadline_at'] = date('Y-m-d H:i:s', $calculated);
    }

    return $attempt;
}

function get_attempt(int $id): array {
    $s = db()->prepare(
        "SELECT a.*, e.duration_seconds AS exam_duration_seconds, e.end_at AS exam_end_at
         FROM attempts a
         JOIN exams e ON e.id=a.exam_id
         WHERE a.id=? AND a.user_id=? LIMIT 1"
    );
    $s->execute([$id, participant_id()]);
    $a = $s->fetch();

    if (!$a) {
        json_response(['error' => 'Attempt tidak ditemukan'], 404);
    }

    if ($a['status'] === 'active') {
        $a = normalize_attempt_deadline($a);
        if (strtotime($a['deadline_at']) <= time()) {
            finalize_attempt($a);
            $a['status'] = 'expired';
        }
    }

    return $a;
}

function calculate_attempt_score(array $a): float {
    $pdo = db();
    $q = $pdo->prepare(
        "SELECT q.type,q.correct_option,q.matrix_correct_mirip,q.matrix_correct_tidak,q.points,ans.selected_option,ans.matrix_answer,ans.essay_score
         FROM questions q
         LEFT JOIN answers ans ON ans.question_id=q.id AND ans.attempt_id=?
         WHERE q.exam_id=?"
    );
    $q->execute([$a['id'], $a['exam_id']]);
    $score = 0.0;
    foreach ($q as $r) {
        if ($r['type'] === 'mcq' && $r['selected_option'] === $r['correct_option']) {
            $score += (float)$r['points'];
        } elseif ($r['type'] === 'matrix_disc') {
            $ans = json_decode((string)($r['matrix_answer'] ?? ''), true) ?: [];
            $half = (float)$r['points'] / 2;
            if (($ans['mirip'] ?? null) === ($r['matrix_correct_mirip'] ?? null)) $score += $half;
            if (($ans['tidak_mirip'] ?? null) === ($r['matrix_correct_tidak'] ?? null)) $score += $half;
        } elseif ($r['type'] === 'essay') {
            // Essay dengan kunci jawaban dari admin dapat dinilai otomatis.
            // Jika tidak ada kunci, nilai tetap menunggu penilaian manual admin.
            if ($r['essay_score'] !== null) {
                $score += (float)$r['essay_score'];
            }
        }
    }
    return $score;
}

function auto_grade_essays(int $attemptId, int $examId): void {
    $pdo = db();
    $q = $pdo->prepare("SELECT q.id,q.points,q.essay_answer_key,a.essay_answer,a.essay_score
                        FROM questions q
                        LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=?
                        WHERE q.exam_id=? AND q.type='essay'");
    $q->execute([$attemptId,$examId]);
    $u = $pdo->prepare("INSERT INTO answers(attempt_id,question_id,essay_score)
                        VALUES(?,?,?)
                        ON DUPLICATE KEY UPDATE essay_score=VALUES(essay_score)");
    foreach ($q as $r) {
        $key = trim((string)($r['essay_answer_key'] ?? ''));
        $answer = trim((string)($r['essay_answer'] ?? ''));
        if ($key === '' || $answer === '') continue;
        $normalize = static function(string $v): string {
            $v = trim(mb_strtolower($v, 'UTF-8'));
            $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
            return $v;
        };
        if ($normalize($answer) === $normalize($key)) {
            $u->execute([$attemptId,(int)$r['id'],(float)$r['points']]);
        }
    }
}

function finalize_attempt(array $a): void {
    if ($a['status'] !== 'active') return;

    $pdo = db();
    auto_grade_essays((int)$a['id'], (int)$a['exam_id']);
    $score = calculate_attempt_score($a);

    $pdo->prepare(
        "UPDATE attempts SET status=?,submitted_at=NOW(),score=? WHERE id=? AND status='active'"
    )->execute(['expired', $score, $a['id']]);

    $pdo->prepare(
        "INSERT INTO audit_logs(user_id,exam_id,attempt_id,event_type) VALUES(?,?,?,?)"
    )->execute([$a['user_id'], $a['exam_id'], $a['id'], 'attempt_expired']);
}
