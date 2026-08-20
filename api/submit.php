<?php
require __DIR__.'/_common.php';

api_auth();
check_api_csrf();

$a = get_attempt((int)(body()['attempt_id'] ?? 0));

if ($a['status'] === 'active') {
    submit_attempt($a);
    $a = get_attempt($a['id']);
}

json_response([
    'status' => $a['status'],
    'score' => $a['score']
]);
