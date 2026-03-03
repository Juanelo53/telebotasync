<?php

/**
 * GitHub Webhook Deploy Script.
 *
 * Cuando haces push a GitHub, este script recibe la notificación
 * y ejecuta git pull para actualizar el servidor automáticamente.
 *
 * Configurar en GitHub:
 *   Settings > Webhooks > Add webhook
 *   URL: https://juaneloserver.com/telebot/deploy.php
 *   Content type: application/json
 *   Secret: (el mismo que DEPLOY_SECRET abajo)
 *   Events: Just the push event
 */

define('DEPLOY_SECRET', '37a8a27fc2416ed07dfc62ffd6f6c1da3ef8eb4d');
define('REPO_DIR', __DIR__);
define('LOG_FILE', __DIR__ . '/logs/deploy.log');
define('BRANCH', 'main');

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
    echo json_encode(['ok' => true, 'msg' => 'Not target branch, skipping']);
    exit;
}

$pusher = $data['pusher']['name'] ?? 'unknown';
$commits = count($data['commits'] ?? []);

deployLog("Deploy triggered by {$pusher} ({$commits} commits)");

$output = [];
$exitCode = 0;

$sshCmd = 'ssh -i /root/.ssh/telebot_deploy -o StrictHostKeyChecking=no';
$env = "GIT_SSH_COMMAND=" . escapeshellarg($sshCmd);

$commands = [
    "cd " . escapeshellarg(REPO_DIR),
    "{$env} git fetch origin " . BRANCH,
    "git reset --hard origin/" . BRANCH,
];

$cmd = implode(' && ', $commands) . ' 2>&1';
exec($cmd, $output, $exitCode);

$result = implode("\n", $output);
deployLog("Exit code: {$exitCode}\n{$result}");

// Asegurar ownership correcto después del pull (cPanel)
exec('chown -R juanelo:juanelo ' . escapeshellarg(REPO_DIR) . ' 2>&1');

http_response_code(200);
echo json_encode([
    'ok' => $exitCode === 0,
    'exit_code' => $exitCode,
    'output' => $result,
    'branch' => BRANCH,
]);

function deployLog(string $msg): void
{
    $date = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[{$date}] {$msg}\n", FILE_APPEND | LOCK_EX);
}
