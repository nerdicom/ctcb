<?php
declare(strict_types=1);

// This endpoint sends project inquiries only to the company's fixed mailbox.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
ini_set('display_errors', '0');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Please use the consultation form to send your request.']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['https://ctcustombuilders.com', 'https://www.ctcustombuilders.com'];
if (($origin !== '' && !in_array($origin, $allowedOrigins, true)) ||
    (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site')) {
    respond(403, ['ok' => false, 'message' => 'Please open the form on ctcustombuilders.com and try again.']);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384 || !empty($_FILES)) {
    respond(413, ['ok' => false, 'message' => 'Please shorten your project description and try again.']);
}

$input = $_POST;
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType === 'application/json') {
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if ($raw === false || strlen($raw) > 16384) {
        respond(413, ['ok' => false, 'message' => 'Please shorten your project description and try again.']);
    }
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        respond(400, ['ok' => false, 'message' => 'We could not read the request. Please try again.']);
    }
}

// A hidden field discourages automated submissions without interrupting visitors.
if (isset($input['company_website']) && $input['company_website'] !== '') {
    respond(422, ['ok' => false, 'message' => 'We could not validate this request. Please email info@ctcustombuilders.com.']);
}

$limits = ['name' => 120, 'address' => 400, 'phone' => 40, 'email' => 254, 'project' => 5000];
$labels = ['name' => 'your name', 'address' => 'the project address', 'phone' => 'your phone number', 'email' => 'your email address', 'project' => 'a little about your project'];
$data = [];
$errors = [];
foreach ($limits as $field => $limit) {
    $value = $input[$field] ?? '';
    if (!is_string($value) || preg_match('//u', $value) !== 1 || strpos($value, "\0") !== false) {
        $errors[$field] = 'Please enter ' . $labels[$field] . '.';
        continue;
    }
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($value === '' || $length > $limit) {
        $errors[$field] = $value === '' ? 'Please enter ' . $labels[$field] . '.' : 'Please shorten this field.';
    }
    $data[$field] = $value;
}
if (isset($data['email']) && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $data['email']))) {
    $errors['email'] = 'Please enter a valid email address.';
}
if (isset($data['phone'])) {
    $digits = preg_replace('/\D/', '', $data['phone']);
    if (strlen($digits) < 7 || strlen($digits) > 15 || preg_match('/[^0-9+().\s-]/u', $data['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number, including the area code.';
    }
}
if ($errors) {
    respond(422, ['ok' => false, 'message' => 'Please check the highlighted details and try again.', 'errors' => $errors]);
}

if (!function_exists('mail')) {
    error_log('CTCB consultation: server mail is unavailable.');
    respond(503, ['ok' => false, 'message' => 'Requests are temporarily unavailable. Please email info@ctcustombuilders.com.']);
}

// Keep rate-limit metadata outside the public website; never store inquiry content.
$ratePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ctcb-consultation-' . substr(hash('sha256', __DIR__), 0, 24) . '.json';
$rateFile = @fopen($ratePath, 'c+');
if ($rateFile === false || !flock($rateFile, LOCK_EX)) {
    if (is_resource($rateFile)) {
        fclose($rateFile);
    }
    error_log('CTCB consultation: rate-limit storage unavailable.');
    respond(503, ['ok' => false, 'message' => 'We could not send your request right now. Please try again or email info@ctcustombuilders.com.']);
}
@chmod($ratePath, 0600);
$stored = stream_get_contents($rateFile);
$state = json_decode($stored === false ? '' : $stored, true);
if (!is_array($state)) {
    $state = ['global' => [], 'visitors' => []];
}
$now = time();
$global = array_values(array_filter($state['global'] ?? [], static function ($t) use ($now): bool {
    return is_int($t) && $t > $now - 86400;
}));
$visitors = [];
foreach (($state['visitors'] ?? []) as $key => $times) {
    if (!is_array($times)) {
        continue;
    }
    $recent = array_values(array_filter($times, static function ($t) use ($now): bool {
        return is_int($t) && $t > $now - 3600;
    }));
    if ($recent) {
        $visitors[$key] = $recent;
    }
}
$visitorKey = hash('sha256', (__DIR__ . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')));
$visitorTimes = $visitors[$visitorKey] ?? [];
$lastMinute = array_filter($global, static function ($t) use ($now): bool { return $t > $now - 60; });
if (count($visitorTimes) >= 5 || count($lastMinute) >= 8 || count($global) >= 80) {
    flock($rateFile, LOCK_UN);
    fclose($rateFile);
    header('Retry-After: 3600');
    respond(429, ['ok' => false, 'message' => 'Please allow some time before sending another request, or email info@ctcustombuilders.com directly.']);
}
$global[] = $now;
$visitorTimes[] = $now;
$visitors[$visitorKey] = $visitorTimes;
$encoded = json_encode(['global' => $global, 'visitors' => $visitors]);
rewind($rateFile);
$saved = ftruncate($rateFile, 0) && $encoded !== false && fwrite($rateFile, $encoded) === strlen($encoded) && fflush($rateFile);
flock($rateFile, LOCK_UN);
fclose($rateFile);
if (!$saved) {
    respond(503, ['ok' => false, 'message' => 'We could not send your request right now. Please email info@ctcustombuilders.com.']);
}

$page = isset($input['page']) && is_string($input['page']) ? $input['page'] : '/';
if (!preg_match('~^/[a-z0-9/_.-]{0,180}$~i', $page)) {
    $page = '/';
}
$received = (new DateTimeImmutable('now', new DateTimeZone('America/Denver')))->format('Y-m-d g:i A T');
$message = "NEW CONSULTATION REQUEST\nCT Custom Builders\n\n" .
    "Name: " . $data['name'] . "\n" .
    "Project address: " . $data['address'] . "\n" .
    "Phone: " . $data['phone'] . "\n" .
    "Email: " . $data['email'] . "\n\n" .
    "ABOUT THE PROJECT\n" . $data['project'] . "\n\n" .
    "Received: " . $received . "\n" .
    "Page: https://ctcustombuilders.com" . $page . "\n\n" .
    "This is a consultation request. Please contact the customer to arrange a time.\n";
$headers = [
    'From' => 'CT Custom Builders Website <info@ctcustombuilders.com>',
    'Reply-To' => $data['email'],
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => 'base64',
];
$body = chunk_split(base64_encode($message), 76, "\r\n");
try {
    $accepted = @mail('info@ctcustombuilders.com', 'New consultation request | CT Custom Builders', $body, $headers, '-finfo@ctcustombuilders.com');
} catch (Throwable $error) {
    $accepted = false;
}
if (!$accepted) {
    error_log('CTCB consultation: server mail did not accept the request.');
    respond(503, ['ok' => false, 'message' => 'We could not send your request right now. Your details are still here. Please try again or email info@ctcustombuilders.com.']);
}
respond(200, ['ok' => true, 'message' => 'Your request has been submitted. We will contact you to arrange a time.']);
