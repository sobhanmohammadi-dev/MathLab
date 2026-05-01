<?php

/**
 * MathLab – calculate.php
 * ──────────────────────────────────────────────────────────────────
 * Secure API endpoint.  Receives a math expression, evaluates it
 * with MathLibrary, returns structured JSON for the step viewer.
 *
 * Security  : strict input allow-list · rate-limiting · security headers
 *             · CORS same-origin · no stack-trace leaks · method guard
 * Performance: output-buffer gzip · ETag short-circuit · early exits
 * ──────────────────────────────────────────────────────────────────
 * GET / POST  ?equation=3x+6=21  [&lang=fa|en] [&precision=10]
 *             [&vars[x]=5]
 */

declare(strict_types=1);

/* ── 0. Config ─────────────────────────────────────────────────── */
define('ML_PRODUCTION',  true);    // hides PHP errors in production
define('ML_RATE_LIMIT',  30);      // max requests per IP per window
define('ML_RATE_WINDOW', 60);      // window in seconds
define('ML_MAX_EXPR',    4096);     // max expression length (chars)
define('ML_MAX_VARS',    64);      // max variable bindings per request
define('ML_RATE_DIR',    '');      // temp dir for rate-limit files; '' = sys_get_temp_dir()

/* ── 1. Error suppression (we return JSON errors ourselves) ────── */
ini_set('display_errors', '0');
error_reporting(E_ALL);
if (ML_PRODUCTION) {
    ini_set('log_errors', '1');
} else {
    // Dev: log full stack traces to PHP error log for debugging
    set_exception_handler(function (\Throwable $e) {
        error_log('[MathLab] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    });
}

/* ── 2. Output buffer → gzip ───────────────────────────────────── */
if (function_exists('ob_gzip_handler')) {
    ob_start('ob_gzip_handler');
} else {
    ob_start();
}

/* ── 3. Load library ───────────────────────────────────────────── */
require_once __DIR__ . '/MathLibrary.php';

/* ── 4. Security headers (sent on EVERY response) ──────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'none'");
header('Cache-Control: no-store');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
// HSTS: enforce HTTPS for 1 year; only set on HTTPS connections
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) == 443
) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header_remove('X-Powered-By');

/* ── 5. CORS: same-origin only ─────────────────────────────────── */
$origin     = $_SERVER['HTTP_ORIGIN'] ?? '';
$serverHost = $_SERVER['HTTP_HOST']   ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST) ?? '';
    if ($originHost === $serverHost) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept');
        header('Access-Control-Max-Age: 86400');
        header('Vary: Origin');
    }
}

/* ── 6. Method guard ───────────────────────────────────────────── */
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    ob_end_clean();
    exit;
}
if (!in_array($method, ['GET', 'POST'], true)) bail(405, 'Method not allowed. Use GET or POST.');

/* ── 7. Rate limiting ──────────────────────────────────────────── */
rateLimit();

/* ── 8. Input: merge GET + POST (POST wins) ────────────────────── */
$input = array_merge($_GET, $_POST);

/* ── 9. equation (required) ────────────────────────────────────── */
$raw = isset($input['equation']) ? trim((string)$input['equation']) : '';
if ($raw === '') bail(400, 'Missing required parameter: equation.');

// Allow-list — only characters valid in a math expression.
// Blocks shell-injection, SQL, HTML, path traversal.
if (!preg_match('/^[\d\s+\-*\/^()=.,a-zA-Z_{}]+$/', $raw)) {
    bail(400, 'Expression contains invalid characters. '
        . 'Allowed: digits · letters · spaces · + - * / ^ ( ) = . , _ { }');
}
if (strlen($raw) > ML_MAX_EXPR) {
    bail(400, 'Expression too long (max ' . ML_MAX_EXPR . ' characters).');
}

/* ── 10. lang ─────────────────────────────────────────────────── */
$lang = strtolower(trim((string)($input['lang'] ?? 'en')));
if (!in_array($lang, ['fa', 'en'], true)) $lang = 'en';

/* ── 11. precision ─────────────────────────────────────────────── */
$prec = isset($input['precision']) ? (int)$input['precision'] : 10;
$prec = max(0, min(20, $prec));

/* ── 12. vars ──────────────────────────────────────────────────── */
$vars   = [];
$rawV   = $input['vars'] ?? [];
if (is_array($rawV)) {
    $n = 0;
    foreach ($rawV as $k => $v) {
        if (++$n > ML_MAX_VARS) break;
        $k = (string)$k;
        $v = (string)$v;
        // Must start with a letter (not underscore), followed by letters/digits/underscores,
        // max 64 chars total — mirrors RegexCache::isValidIdentifier in MathLibrary v1.0.0-stable.
        // The D modifier (DOLLAR_ENDONLY) prevents $ matching before embedded newlines (ReDoS hardening).
        if ($k === '_' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $k)) continue;
        if (!is_numeric($v)) continue;
        $f = (float)$v;
        if (is_nan($f) || is_infinite($f)) continue;
        $vars[$k] = $f;
    }
}

/* ── 13. ETag short-circuit ────────────────────────────────────── */
$cacheKey = md5($raw . '|' . $lang . '|' . $prec . '|' . serialize($vars));
$etag     = '"ml-' . $cacheKey . '"';
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    ob_end_clean();
    exit;
}

/* ── 14. Evaluate ──────────────────────────────────────────────── */
$res = MathLibrary::safeEvaluate($raw, $vars, $prec);

if (!$res['ok']) {
    $errMsg  = sanitizeError($res['error'] ?? 'Evaluation failed.');
    $errType = classifyError($errMsg);
    $friendlyMsg = translateError($errMsg, $errType, $lang);
    error_log('[MathLab] 422 (' . $errType . '): ' . $errMsg);
    http_response_code(422);
    echo json_encode([
        'valid'         => false,
        'error'         => $friendlyMsg,       // user-friendly translated message
        'error_raw'     => $errMsg,            // technical detail (logged, available for debug)
        'error_type'    => $errType,
        'code'          => 422,
        'steps'         => [],
        'final_result'  => null,
        'final_display' => null,
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

/* ── 15. Shape response ────────────────────────────────────────── */
$detected  = MathLibrary::detect($raw, $vars);

// variable report
$varReport = new stdClass();
foreach ($detected['variables_found'] as $vn) {
    $varReport->$vn = array_key_exists($vn, $vars) ? $vars[$vn] : 'unknown';
}

// steps
$steps = array_map(
    static fn(array $s): array => shapeStep($s, $lang),
    $res['steps']
);

// final display
[$finalDisplay, $finalType] = shapeFinal($res['final_result'], $prec);

// Cache: short TTL for valid results (same expression = same answer)
header('Cache-Control: private, max-age=30');
header('X-RateLimit-Limit: ' . ML_RATE_LIMIT);

send(200, [
    'valid'               => true,
    'original_expression' => $raw,
    'type'                => $finalType,
    'variables'           => $varReport,
    'detected'            => $detected,
    'steps'               => $steps,
    'step_count'          => count($steps),
    'final_result'        => $res['final_result'],
    'final_display'       => $finalDisplay,
    'lang'                => $lang,
    'precision'           => $prec,
]);

/* ════════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════════ */

function shapeStep(array $s, string $lang): array
{
    $meta  = $s['metadata'] ?? [];
    $after = $meta['result_display'] ?? (string)($s['result'] ?? '');
    return [
        'step_number'    => (int)   ($s['step_number']    ?? 0),
        'description'    => (string)($lang === 'fa'
            ? ($s['description_fa'] ?? $s['description_en'] ?? '')
            : ($s['description_en'] ?? '')),
        'description_en' => (string)($s['description_en'] ?? ''),
        'description_fa' => (string)($s['description_fa'] ?? ''),
        'formula'        => (string)($s['formula']        ?? ''),
        'calculation'    => (string)($s['calculation']    ?? ''),
        'before'         => (string)($meta['expression_part'] ?? $s['calculation'] ?? ''),
        'after'          => $after,
        'result'         => $s['result'] ?? null,
        'result_display' => $after,
        'operation'      => (string)($meta['operation']   ?? 'compute'),
        'node_type'      => (string)($meta['node_type']   ?? ''),
        'is_symbolic'    => (bool)  ($meta['is_symbolic'] ?? false),
    ];
}

function shapeFinal(mixed $final, int $prec): array
{
    if (is_array($final)) {
        $var = (string)($final['variable'] ?? '?');
        $val = $final['value'] ?? null;
        if ($val === 'identity')    return ["{$var} = any value (identity — infinite solutions)", 'equation'];
        if ($val === 'no_solution') return ['No solution exists (contradiction)', 'equation'];
        if (is_numeric($val))       return ["{$var} = " . MathEvaluator::fmt((float)$val, $prec), 'equation'];
        return ["{$var} = " . (string)$val, 'equation'];
    }
    if (is_numeric($final)) return [MathEvaluator::fmt((float)$final, $prec), 'expression'];
    return [(string)$final, 'expression'];
}

function sanitizeError(string $msg): string
{
    // Remove file paths so server internals are never leaked
    $msg = preg_replace('#(/[^\s:,]+|[A-Z]:\\\\[^\s:,]+)#', '[path]', $msg) ?? $msg;
    return mb_substr(trim($msg), 0, 300);
}

/**
 * Classify a library error message string into a UI error type.
 *
 * Updated for MathLibrary v1.0.0-stable exception hierarchy:
 *   MathOverflowException   → overflow_error
 *   MathSolverException     → variable_error (unknown/no-unknown)
 *   MathDomainException     → domain_error   (sqrt neg, div-by-zero, NaN)
 *   MathParseException      → syntax_error
 *   MathEvaluationException → evaluation_error (fallback)
 */
function classifyError(string $msg): string
{
    $lower = strtolower($msg);

    // MathOverflowException / large-result overflow
    if (
        strpos($lower, 'overflow') !== false || strpos($lower, 'exceeds') !== false
        || strpos($lower, 'astronomically') !== false || strpos($lower, 'approx 10^') !== false
    ) {
        return 'overflow_error';
    }

    // MathSolverException — variable-count mismatches
    if (
        strpos($lower, 'no unknown variable') !== false
        || strpos($lower, 'equation has no unknown') !== false
        || (strpos($lower, 'unknowns') !== false && strpos($lower, 'provide') !== false)
        || strpos($lower, 'all variables have values') !== false
    ) {
        return 'variable_error';
    }

    // MathDomainException — mathematical domain violations
    if ((strpos($lower, 'sqrt') !== false || strpos($lower, 'square root') !== false) && strpos($lower, 'negative') !== false) return 'domain_error';
    if (strpos($lower, 'division by zero') !== false || strpos($lower, 'cannot divide') !== false) return 'domain_error';
    if (
        strpos($lower, 'imaginary') !== false || strpos($lower, 'nan') !== false
        || strpos($lower, 'no real value') !== false || strpos($lower, 'undefined') !== false
    ) {
        return 'domain_error';
    }
    // Solver overflow / INF-INF cancellation
    if (
        strpos($lower, 'infinity') !== false || strpos($lower, 'singularity') !== false
        || strpos($lower, 'inf') !== false
    ) {
        return 'overflow_error';
    }

    // MathParseException — lexer / parser errors
    if (
        strpos($lower, 'unexpected') !== false || strpos($lower, 'expected') !== false
        || strpos($lower, 'malformed') !== false || strpos($lower, 'unclosed') !== false
        || strpos($lower, 'missing') !== false   || strpos($lower, 'only one') !== false
        || strpos($lower, 'inside parentheses') !== false
        || strpos($lower, 'invalid character') !== false
    ) {
        return 'syntax_error';
    }

    return 'evaluation_error';
}

/**
 * Translate a technical library error into a user-friendly bilingual message.
 * Returns ['en' => '...', 'fa' => '...']
 */
function translateError(string $msg, string $errType, string $lang): string
{
    $lower = strtolower($msg);

    // Overflow
    if ($errType === 'overflow_error' || strpos($lower, 'overflow') !== false || strpos($lower, 'exceeds') !== false) {
        return $lang === 'fa'
            ? 'نتیجه بسیار بزرگ است و از ظرفیت محاسباتی فراتر می‌رود (سرریز). توان‌های کوچک‌تری استفاده کنید یا متغیرها را وارد نمایید.'
            : 'The result is astronomically large (overflow). Try smaller exponent values or provide variable values to reduce complexity.';
    }

    // Domain errors (sqrt of negative, division by zero)
    if ($errType === 'domain_error') {
        if (strpos($lower, 'negative') !== false && (strpos($lower, 'sqrt') !== false || strpos($lower, 'square root') !== false)) {
            return $lang === 'fa'
                ? 'جذر یک عدد منفی تعریف نشده است (عدد موهومی). مقدار داخل رادیکال باید بزرگتر یا مساوی صفر باشد.'
                : 'Square root of a negative number is undefined (imaginary result). The value inside √ must be ≥ 0.';
        }
        if (strpos($lower, 'division by zero') !== false || strpos($lower, 'divide') !== false) {
            return $lang === 'fa'
                ? 'تقسیم بر صفر تعریف نشده است. مخرج نباید صفر باشد.'
                : 'Division by zero is undefined. The denominator must not be zero.';
        }
        if (strpos($lower, 'nan') !== false) {
            return $lang === 'fa'
                ? 'نتیجه عبارت عدد معتبری نیست (مثلاً صفر به توان صفر). ترکیب توان‌ها را بررسی کنید.'
                : 'The expression has no valid numeric result (e.g. 0^0 is undefined). Check the power combination.';
        }
        return $lang === 'fa'
            ? 'مقدار وارد شده خارج از دامنه تعریف است (مثلاً جذر منفی یا تقسیم بر صفر).'
            : 'The input value is outside the allowed domain (e.g. square root of negative, or division by zero).';
    }

    // Variable errors
    if ($errType === 'variable_error') {
        if (strpos($lower, 'no unknown') !== false) {
            return $lang === 'fa'
                ? 'همه متغیرها مقدار دارند — معادله‌ای برای حل وجود ندارد. برای حل معادله، یک متغیر را بدون مقدار بگذارید.'
                : 'All variables have values — there is nothing to solve. To solve an equation, leave exactly one variable without a value.';
        }
        if (strpos($lower, 'unknowns') !== false) {
            return $lang === 'fa'
                ? 'بیش از یک متغیر مجهول وجود دارد. برای حل، مقادیر همه متغیرها به جز یکی را وارد کنید.'
                : 'More than one unknown variable. Provide values for all variables except one to solve the equation.';
        }
        return $lang === 'fa'
            ? 'خطای متغیر — مقادیر متغیرها را در پانل متغیرها وارد کنید.'
            : 'Variable error — please enter variable values in the Variables panel.';
    }

    // Syntax errors
    if ($errType === 'syntax_error') {
        return $lang === 'fa'
            ? 'فرمت معادله نادرست است. پرانتزها را بررسی کنید و مطمئن شوید همه عملگرها بین مقادیر قرار دارند.'
            : 'The expression has a formatting error. Check that parentheses are balanced and operators are placed between values.';
    }

    // Generic fallback — return sanitized message but don't expose raw internals
    $clean = sanitizeError($msg);
    // Strip common technical prefixes users shouldn't see
    $clean = preg_replace('/^(RuntimeException|InvalidArgumentException|Error):\s*/i', '', $clean) ?? $clean;
    return $clean;
}

function rateLimit(): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Sanitize IP: only allow valid IP characters to prevent path injection
    $ip   = preg_replace('/[^0-9a-fA-F.:]+/', '', $ip) ?? '0.0.0.0';
    $dir  = ML_RATE_DIR !== '' ? ML_RATE_DIR : sys_get_temp_dir();
    // Ensure the rate-limit directory exists and is writable
    if (!is_dir($dir) || !is_writable($dir)) {
        // Graceful degradation: if temp dir is inaccessible, skip rate limiting
        return;
    }
    $file = $dir . '/mathlab_rl_' . md5($ip) . '.json';
    $now  = time();
    $data = ['count' => 0, 'reset' => $now + ML_RATE_WINDOW];

    // Use a lock file for atomic read-modify-write to prevent TOCTOU races
    $lock = $file . '.lock';
    $fh   = @fopen($lock, 'c');
    if ($fh === false) {
        return;
    } // graceful degradation
    flock($fh, LOCK_EX);

    try {
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $d = json_decode($raw, true);
                if (is_array($d) && isset($d['count'], $d['reset'])) $data = $d;
            }
        }
        if ($now > (int)($data['reset'] ?? 0)) {
            $data = ['count' => 0, 'reset' => $now + ML_RATE_WINDOW];
        }
        $data['count']++;
        @file_put_contents($file, json_encode($data));
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    $remaining = max(0, ML_RATE_LIMIT - $data['count']);
    header('X-RateLimit-Limit: ' . ML_RATE_LIMIT);
    header('X-RateLimit-Remaining: ' . $remaining);
    header('X-RateLimit-Reset: ' . $data['reset']);

    if ($data['count'] > ML_RATE_LIMIT) {
        $retry = max(0, $data['reset'] - $now);
        header('Retry-After: ' . $retry);
        bail(429, "Too many requests. Please wait {$retry} seconds.");
    }
}

function bail(int $code, string $msg): never
{
    // Log full technical detail to server log (never exposed to client)
    error_log('[MathLab ' . date('H:i:s') . '] HTTP ' . $code . ': ' . $msg);
    http_response_code($code);
    echo json_encode([
        'valid'         => false,
        'error'         => $msg,
        'code'          => $code,
        'steps'         => [],
        'final_result'  => null,
        'final_display' => null,
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

/* Recursively replace PHP INF / NAN (invalid in JSON) with strings */
function sanitizeForJson(mixed $v): mixed
{
    if (is_float($v)) {
        if (is_nan($v))       return 'NaN';
        if (is_infinite($v))  return $v > 0 ? '∞' : '-∞';
    }
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $item) $out[$k] = sanitizeForJson($item);
        return $out;
    }
    if ($v instanceof stdClass) {
        $out = new stdClass();
        foreach ((array)$v as $k => $item) $out->$k = sanitizeForJson($item);
        return $out;
    }
    return $v;
}

function send(int $code, array $data): never
{
    http_response_code($code);
    $safe = sanitizeForJson($data);
    $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($json === false || $json === '') {
        error_log('[MathLab] json_encode failed: ' . json_last_error_msg());
        $json = json_encode([
            'valid' => false,
            'error' => 'Response could not be encoded (overflow or invalid value).',
            'error_type' => 'encoding_error',
            'code' => 500,
            'steps' => [],
            'final_result' => null,
            'final_display' => null,
        ]);
    }
    echo $json;
    ob_end_flush();
    exit;
}
