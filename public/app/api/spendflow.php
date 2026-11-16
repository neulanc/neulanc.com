<?php
declare(strict_types=1);

/*
 * SpendFlow encrypted sync relay.
 *
 * The browser owns the encryption key. This endpoint only stores AES-GCM
 * ciphertext and hashed, randomly generated device credentials. Pairing
 * invitations expire after one minute and are removed atomically on first use.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

const MAX_BODY_BYTES = 2097152;
const MAX_CIPHER_BYTES = 1572864;
const INVITE_TTL_SECONDS = 60;
const MAX_DEVICES = 20;
const MAX_INVITES = 10;

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(int $status, string $code): never
{
    respond($status, ['ok' => false, 'error' => $code]);
}

function base64url(int $bytes): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function safe_id(mixed $value, int $min = 20, int $max = 90): string
{
    if (!is_string($value) || strlen($value) < $min || strlen($value) > $max || !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
        fail(400, 'invalid_identifier');
    }
    return $value;
}

function safe_label(mixed $value): string
{
    if (!is_string($value)) {
        return 'Device';
    }
    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    return substr($value !== '' ? $value : 'Device', 0, 60);
}

function read_body(): array
{
    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($length > MAX_BODY_BYTES) {
        fail(413, 'request_too_large');
    }
    $raw = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
    if ($raw === false || strlen($raw) > MAX_BODY_BYTES) {
        fail(413, 'request_too_large');
    }
    try {
        $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        fail(400, 'invalid_json');
    }
    if (!is_array($decoded)) {
        fail(400, 'invalid_json');
    }
    return $decoded;
}

function storage_root(): string
{
    $cursor = __DIR__;
    $privateParent = dirname((string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
    for ($depth = 0; $depth < 6; $depth++) {
        if (basename($cursor) === 'public_html') {
            $privateParent = dirname($cursor);
            break;
        }
        $parent = dirname($cursor);
        if ($parent === $cursor) {
            break;
        }
        $cursor = $parent;
    }
    $root = $privateParent . DIRECTORY_SEPARATOR . 'spendflow-private';
    foreach ([$root, $root . '/projects', $root . '/limits'] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            fail(503, 'storage_unavailable');
        }
    }
    $deny = $root . '/.htaccess';
    if (!is_file($deny)) {
        @file_put_contents($deny, "Require all denied\nDeny from all\n", LOCK_EX);
        @chmod($deny, 0600);
    }
    return $root;
}

function secret(string $root): string
{
    $path = $root . '/server-secret';
    if (!is_file($path)) {
        $created = @fopen($path, 'x');
        if (is_resource($created)) {
            fwrite($created, base64url(48));
            fclose($created);
            @chmod($path, 0600);
        }
    }
    $value = @file_get_contents($path);
    if (!is_string($value) || strlen(trim($value)) < 40) {
        fail(503, 'storage_unavailable');
    }
    return trim($value);
}

function token_hash(string $token, string $secret): string
{
    return hash_hmac('sha256', $token, $secret);
}

function project_path(string $root, string $projectId): string
{
    return $root . '/projects/' . $projectId . '.json';
}

function open_project(string $path)
{
    $handle = @fopen($path, 'r+');
    if (!is_resource($handle)) {
        fail(404, 'project_not_found');
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        fail(503, 'storage_busy');
    }
    rewind($handle);
    $raw = stream_get_contents($handle);
    $record = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($record)) {
        flock($handle, LOCK_UN);
        fclose($handle);
        fail(500, 'project_corrupt');
    }
    return [$handle, $record];
}

function save_project($handle, array $record): void
{
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) {
        fail(500, 'encode_failed');
    }
    rewind($handle);
    if (!ftruncate($handle, 0) || fwrite($handle, $json) === false || !fflush($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
        fail(503, 'storage_unavailable');
    }
    @chmod(stream_get_meta_data($handle)['uri'], 0600);
}

function close_project($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function validate_cipher(mixed $value): array
{
    if (!is_array($value) || !isset($value['iv'], $value['data']) || !is_string($value['iv']) || !is_string($value['data'])) {
        fail(400, 'invalid_ciphertext');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{16}$/D', $value['iv']) || !preg_match('/^[A-Za-z0-9_-]+$/D', $value['data'])) {
        fail(400, 'invalid_ciphertext');
    }
    if (strlen($value['data']) > MAX_CIPHER_BYTES) {
        fail(413, 'project_too_large');
    }
    return ['iv' => $value['iv'], 'data' => $value['data']];
}

function authorization_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = is_array($headers) ? (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '') : '';
    }
    if (!preg_match('/^Bearer ([A-Za-z0-9_-]{32,160})$/D', $header, $match)) {
        fail(401, 'authorization_required');
    }
    return $match[1];
}

function authorize(array $record, string $secret): array
{
    $hash = token_hash(authorization_token(), $secret);
    foreach (($record['devices'] ?? []) as $deviceId => $device) {
        if (is_array($device) && isset($device['tokenHash']) && hash_equals((string) $device['tokenHash'], $hash)) {
            return [(string) $deviceId, $device];
        }
    }
    fail(401, 'invalid_device');
}

function prune_invites(array &$record, int $now): void
{
    foreach (($record['invites'] ?? []) as $hash => $invite) {
        if (!is_array($invite) || (int) ($invite['expiresAt'] ?? 0) < $now) {
            unset($record['invites'][$hash]);
        }
    }
}

function prune_ownership_transfers(array &$record, int $now): void
{
    foreach (($record['ownershipTransfers'] ?? []) as $hash => $transfer) {
        if (!is_array($transfer) || (int) ($transfer['expiresAt'] ?? 0) < $now) {
            unset($record['ownershipTransfers'][$hash]);
        }
    }
}

function require_owner(array $device): void
{
    if (($device['role'] ?? 'member') !== 'owner') {
        fail(403, 'owner_required');
    }
}

function enforce_origin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $originScheme = strtolower((string) parse_url($origin, PHP_URL_SCHEME));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    if ($originScheme !== 'https' || $originHost !== $host) {
        fail(403, 'origin_rejected');
    }
}

function rate_limit(string $root, string $secret): void
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash_hmac('sha256', $ip, $secret);
    $path = $root . '/limits/' . $key;
    $window = intdiv(time(), 60);
    $handle = @fopen($path, 'c+');
    if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
        return;
    }
    $current = json_decode((string) stream_get_contents($handle), true);
    $count = is_array($current) && ($current['window'] ?? -1) === $window ? (int) ($current['count'] ?? 0) + 1 : 1;
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode(['window' => $window, 'count' => $count]));
    flock($handle, LOCK_UN);
    fclose($handle);
    $cleanupPath = $root . '/limits/.cleanup';
    if (!is_file($cleanupPath) || filemtime($cleanupPath) < time() - 3600) {
        $cleanup = @fopen($cleanupPath, 'c+');
        if (is_resource($cleanup) && flock($cleanup, LOCK_EX | LOCK_NB)) {
            if (filemtime($cleanupPath) < time() - 3600) {
                touch($cleanupPath);
                $cutoff = time() - 86400;
                foreach (glob($root . '/limits/*') ?: [] as $candidate) {
                    if (is_file($candidate) && filemtime($candidate) < $cutoff) {
                        @unlink($candidate);
                    }
                }
            }
            flock($cleanup, LOCK_UN);
            fclose($cleanup);
        }
    }
    if ($count > 120) {
        fail(429, 'rate_limited');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail(405, 'method_not_allowed');
}

enforce_origin();
$root = storage_root();
$secret = secret($root);
rate_limit($root, $secret);
$body = read_body();
$action = isset($body['action']) && is_string($body['action']) ? $body['action'] : '';

if ($action === 'health') {
    respond(200, ['ok' => true, 'service' => 'spendflow-sync', 'version' => 1]);
}

$projectId = safe_id($body['projectId'] ?? null, 32, 64);
$path = project_path($root, $projectId);
$now = time();

if ($action === 'create') {
    $deviceId = safe_id($body['deviceId'] ?? null, 20, 90);
    $deviceToken = safe_id($body['deviceToken'] ?? null, 32, 160);
    $recoveryToken = safe_id($body['recoveryToken'] ?? null, 32, 160);
    $cipher = validate_cipher($body['cipher'] ?? null);
    $handle = @fopen($path, 'x');
    if (!is_resource($handle)) {
        fail(409, 'project_exists');
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        fail(503, 'storage_busy');
    }
    $record = [
        'version' => 2,
        'projectId' => $projectId,
        'revision' => 1,
        'cipher' => $cipher,
        'createdAt' => $now,
        'updatedAt' => $now,
        'devices' => [
            $deviceId => [
                'tokenHash' => token_hash($deviceToken, $secret),
                'label' => safe_label($body['label'] ?? null),
                'createdAt' => $now,
                'lastSeenAt' => $now,
                'role' => 'owner',
            ],
        ],
        'invites' => [],
        'ownershipTransfers' => [],
        'recoveryHash' => token_hash($recoveryToken, $secret),
    ];
    save_project($handle, $record);
    close_project($handle);
    respond(201, ['ok' => true, 'revision' => 1]);
}

if ($action === 'redeem') {
    $deviceId = safe_id($body['deviceId'] ?? null, 20, 90);
    $inviteToken = safe_id($body['inviteToken'] ?? null, 32, 160);
    [$handle, $record] = open_project($path);
    prune_invites($record, $now);
    $inviteHash = token_hash($inviteToken, $secret);
    $invite = $record['invites'][$inviteHash] ?? null;
    if (!is_array($invite) || (int) ($invite['expiresAt'] ?? 0) < $now) {
        save_project($handle, $record);
        close_project($handle);
        fail(410, 'invite_invalid_or_used');
    }
    if (count($record['devices'] ?? []) >= MAX_DEVICES) {
        close_project($handle);
        fail(409, 'device_limit');
    }
    unset($record['invites'][$inviteHash]);
    $deviceToken = base64url(48);
    $record['devices'][$deviceId] = [
        'tokenHash' => token_hash($deviceToken, $secret),
        'label' => safe_label($body['label'] ?? null),
        'createdAt' => $now,
        'lastSeenAt' => $now,
        'role' => 'member',
    ];
    save_project($handle, $record);
    close_project($handle);
    respond(200, [
        'ok' => true,
        'deviceToken' => $deviceToken,
        'revision' => (int) $record['revision'],
        'cipher' => $record['cipher'],
        'role' => 'member',
    ]);
}

if ($action === 'recover-owner') {
    $recoveryToken = safe_id($body['recoveryToken'] ?? null, 32, 160);
    $newRecoveryToken = safe_id($body['newRecoveryToken'] ?? null, 32, 160);
    $deviceId = safe_id($body['deviceId'] ?? null, 20, 90);
    [$handle, $record] = open_project($path);
    $expected = (string) ($record['recoveryHash'] ?? '');
    if ($expected === '' || !hash_equals($expected, token_hash($recoveryToken, $secret))) {
        close_project($handle);
        fail(401, 'invalid_recovery_key');
    }
    if (count($record['devices'] ?? []) >= MAX_DEVICES) {
        $removable = array_filter($record['devices'], static fn ($device) => ($device['role'] ?? 'member') !== 'owner');
        uasort($removable, static fn ($left, $right) => (int) ($left['lastSeenAt'] ?? 0) <=> (int) ($right['lastSeenAt'] ?? 0));
        $oldestId = array_key_first($removable);
        if (is_string($oldestId)) unset($record['devices'][$oldestId]);
    }
    foreach ($record['devices'] as &$device) $device['role'] = 'member';
    unset($device);
    if (isset($body['previousDeviceId']) && is_string($body['previousDeviceId']) && preg_match('/^[A-Za-z0-9_-]{20,90}$/D', $body['previousDeviceId'])) {
        unset($record['devices'][$body['previousDeviceId']]);
    }
    $deviceToken = base64url(48);
    $record['devices'][$deviceId] = [
        'tokenHash' => token_hash($deviceToken, $secret),
        'label' => safe_label($body['label'] ?? null),
        'createdAt' => $now,
        'lastSeenAt' => $now,
        'role' => 'owner',
    ];
    $record['recoveryHash'] = token_hash($newRecoveryToken, $secret);
    $record['ownershipTransfers'] = [];
    $record['invites'] = [];
    save_project($handle, $record);
    close_project($handle);
    respond(200, [
        'ok' => true, 'deviceToken' => $deviceToken,
        'revision' => (int) $record['revision'], 'cipher' => $record['cipher'], 'role' => 'owner',
    ]);
}

[$handle, $record] = open_project($path);
[$currentDeviceId, $currentDevice] = authorize($record, $secret);
prune_invites($record, $now);
prune_ownership_transfers($record, $now);
$hasOwner = false;
foreach (($record['devices'] ?? []) as $device) {
    if (($device['role'] ?? '') === 'owner') { $hasOwner = true; break; }
}
if (!$hasOwner) {
    $record['devices'][$currentDeviceId]['role'] = 'owner';
    $currentDevice['role'] = 'owner';
}
$record['devices'][$currentDeviceId]['lastSeenAt'] = $now;
$currentRole = (string) ($currentDevice['role'] ?? 'member');

if ($action === 'pull') {
    save_project($handle, $record);
    close_project($handle);
    respond(200, [
        'ok' => true,
        'revision' => (int) $record['revision'],
        'cipher' => $record['cipher'],
        'updatedAt' => (int) $record['updatedAt'],
        'role' => $currentRole,
    ]);
}

if ($action === 'push') {
    $cipher = validate_cipher($body['cipher'] ?? null);
    $baseRevision = filter_var($body['baseRevision'] ?? null, FILTER_VALIDATE_INT);
    if ($baseRevision === false || $baseRevision < 1) {
        close_project($handle);
        fail(400, 'invalid_revision');
    }
    if ($baseRevision !== (int) $record['revision'] && empty($body['force'])) {
        save_project($handle, $record);
        close_project($handle);
        respond(409, [
            'ok' => false,
            'error' => 'revision_conflict',
            'revision' => (int) $record['revision'],
            'cipher' => $record['cipher'],
        ]);
    }
    $record['revision'] = (int) $record['revision'] + 1;
    $record['cipher'] = $cipher;
    $record['updatedAt'] = $now;
    save_project($handle, $record);
    close_project($handle);
    respond(200, ['ok' => true, 'revision' => (int) $record['revision'], 'role' => $currentRole]);
}

if ($action === 'set-recovery') {
    require_owner($currentDevice);
    $recoveryToken = safe_id($body['recoveryToken'] ?? null, 32, 160);
    $record['recoveryHash'] = token_hash($recoveryToken, $secret);
    save_project($handle, $record);
    close_project($handle);
    respond(200, ['ok' => true]);
}

if ($action === 'invite') {
    require_owner($currentDevice);
    if (count($record['invites'] ?? []) >= MAX_INVITES) {
        close_project($handle);
        fail(429, 'invite_limit');
    }
    $inviteToken = base64url(36);
    $expiresAt = $now + INVITE_TTL_SECONDS;
    $record['invites'][token_hash($inviteToken, $secret)] = [
        'expiresAt' => $expiresAt,
        'createdBy' => $currentDeviceId,
    ];
    save_project($handle, $record);
    close_project($handle);
    respond(201, ['ok' => true, 'inviteToken' => $inviteToken, 'expiresAt' => $expiresAt]);
}

if ($action === 'cancel-invite') {
    require_owner($currentDevice);
    $inviteToken = safe_id($body['inviteToken'] ?? null, 32, 160);
    unset($record['invites'][token_hash($inviteToken, $secret)]);
    save_project($handle, $record);
    close_project($handle);
    respond(200, ['ok' => true]);
}

if ($action === 'devices') {
    require_owner($currentDevice);
    $devices = [];
    foreach (($record['devices'] ?? []) as $deviceId => $device) {
        $devices[] = [
            'id' => (string) $deviceId,
            'label' => safe_label($device['label'] ?? null),
            'createdAt' => (int) ($device['createdAt'] ?? 0),
            'lastSeenAt' => (int) ($device['lastSeenAt'] ?? 0),
            'current' => hash_equals((string) $deviceId, $currentDeviceId),
            'role' => (string) ($device['role'] ?? 'member'),
        ];
    }
    save_project($handle, $record);
    close_project($handle);
    respond(200, ['ok' => true, 'devices' => $devices]);
}

if ($action === 'revoke') {
    require_owner($currentDevice);
    $targetDeviceId = safe_id($body['targetDeviceId'] ?? null, 20, 90);
    if (hash_equals($targetDeviceId, $currentDeviceId)) {
        close_project($handle);
        fail(400, 'cannot_revoke_current_device');
    }
    if (isset($record['devices'][$targetDeviceId])) {
        unset($record['devices'][$targetDeviceId]);
    }
    save_project($handle, $record);
    close_project($handle);
    respond(200, ['ok' => true]);
}

if ($action === 'initiate-transfer') {
    require_owner($currentDevice);
    $targetDeviceId = safe_id($body['targetDeviceId'] ?? null, 20, 90);
    if (hash_equals($targetDeviceId, $currentDeviceId) || !isset($record['devices'][$targetDeviceId])) {
        close_project($handle);
        fail(400, 'invalid_transfer_target');
    }
    $transferCode = strtoupper(substr(base64url(12), 0, 12));
    $expiresAt = $now + INVITE_TTL_SECONDS;
    $record['ownershipTransfers'] = [token_hash($transferCode, $secret) => [
        'targetDeviceId' => $targetDeviceId, 'expiresAt' => $expiresAt,
    ]];
    save_project($handle, $record);
    close_project($handle);
    respond(201, ['ok' => true, 'transferCode' => $transferCode, 'expiresAt' => $expiresAt]);
}

if ($action === 'claim-transfer') {
    $transferCode = strtoupper(safe_id($body['transferCode'] ?? null, 12, 20));
    $newRecoveryToken = safe_id($body['newRecoveryToken'] ?? null, 32, 160);
    $transfer = $record['ownershipTransfers'][token_hash($transferCode, $secret)] ?? null;
    if (!is_array($transfer) || !hash_equals((string) ($transfer['targetDeviceId'] ?? ''), $currentDeviceId)) {
        save_project($handle, $record);
        close_project($handle);
        fail(401, 'invalid_transfer_code');
    }
    foreach ($record['devices'] as &$device) $device['role'] = 'member';
    unset($device);
    $record['devices'][$currentDeviceId]['role'] = 'owner';
    $record['recoveryHash'] = token_hash($newRecoveryToken, $secret);
    $record['ownershipTransfers'] = [];
    save_project($handle, $record);
    close_project($handle);
    respond(200, ['ok' => true, 'role' => 'owner']);
}

if ($action === 'leave') {
    if ($currentRole === 'owner') {
        close_project($handle);
        fail(400, 'owner_must_transfer_or_delete');
    }
    unset($record['devices'][$currentDeviceId]);
    save_project($handle, $record);
    close_project($handle);
    respond(200, ['ok' => true]);
}

if ($action === 'delete') {
    require_owner($currentDevice);
    if (!isset($body['confirmation']) || !is_string($body['confirmation']) || !hash_equals($projectId, $body['confirmation'])) {
        close_project($handle);
        fail(400, 'confirmation_required');
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!@unlink($path)) {
        fail(503, 'delete_failed');
    }
    respond(200, ['ok' => true]);
}

save_project($handle, $record);
close_project($handle);
fail(400, 'unknown_action');
