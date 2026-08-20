<?php
require __DIR__.'/_common.php';

api_auth();
check_api_csrf();

$a = get_attempt((int)(body()['attempt_id'] ?? 0));

if ($a['status'] === 'active') {
    finalize_attempt($a);
    $a = get_attempt($a['id']);
}

db()->prepare(
    "INSERT INTO audit_logs(user_id,exam_id,attempt_id,event_type)
     VALUES(?,?,?,?)"
)->execute([
    participant_id(), $a['exam_id'], $a['id'], 'attempt_submitted'
]);

json_response([
    'status'=>$a['status'],
    'score'=>$a['score']
]);
