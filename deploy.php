<?php

/**
 * GitHub Webhook Deploy Script.
 *
 * Cuando haces push a GitHub, este script recibe la notificación
 * y ejecuta git pull para actualizar el servidor automáticamente.
 */

define('DEPLOY_SECRET', '37a8a27fc2416ed07dfc62ffd6f6c1da3ef8eb4d');
define('REPO_DIR', __DIR__);
define('LOG_FILE', __DIR__ . '/logs/deploy.log');
define('BRANCH', 'main');
define('SSH_KEY', '/home/juanelo/.ssh/telebot_deploy');

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

if (!$signature || !$payload) {
    http_response_code(403);
    exit('No signature');
}

$expected = 'sha256=' . hash_hmac('sha256', $payload, DEPLOY_SECRET);
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

$data = json_decode($payload, true);
$ref = $data['ref'] ?? '';

if ($ref !== 'refs/heads/' . BRANCH) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'Not target branch']);
    exit;
}

$pusher = $data['pusher']['name'] ?? 'unknown';
$commits = count($data['commits'] ?? []);

deployLog("Deploy triggered by {$pusher} ({$commits} commits)");

$sshCmd = 'ssh -i ' . SSH_KEY . ' -o StrictHostKeyChecking=no';
$cmd = sprintf(
    'cd %s && GIT_SSH_COMMAND=%s git fetch origin %s 2>&1 && git reset --hard origin/%s 2>&1',
    escapeshellarg(REPO_DIR),
    escapeshellarg($sshCmd),
    BRANCH,
    BRANCH
);

$result = runCommand($cmd);
deployLog("Exit: {$result['code']} | {$result['output']}");

if ($result['code'] === 0) {
    deployLog("Deploy OK");
} else {
    deployLog("Deploy FAILED");
}

http_response_code(200);
echo json_encode([
    'ok' => $result['code'] === 0,
    'exit_code' => $result['code'],
    'output' => $result['output'],
]);

function runCommand(string $cmd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['code' => -1, 'output' => 'proc_open failed'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($process);
    $output = trim($stdout . "\n" . $stderr);

    return ['code' => $code, 'output' => $output];
}

function deployLog(string $msg): void
{
    $date = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[{$date}] {$msg}\n", FILE_APPEND | LOCK_EX);
}
