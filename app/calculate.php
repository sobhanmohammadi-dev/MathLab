<?php
/**
 * MathLab – calculate.php
 * ──────────────────────────────────────────────────────────────────
 * Supports both numeric (humanmode=false) and symbolic (humanmode=true)
 * evaluation of expressions and linear equations with step-by-step output.
 */

declare(strict_types=1);

/* ── 0. Config ─────────────────────────────────────────────────── */
define('ML_PRODUCTION',  true);
define('ML_RATE_LIMIT',  30);
define('ML_RATE_WINDOW', 60);
define('ML_MAX_EXPR',    4096);
define('ML_MAX_VARS',    64);
define('ML_RATE_DIR',    '');

/* ── 1. Error reporting ───────────────────────────────────────── */
ini_set('display_errors', '0');
error_reporting(E_ALL);
if (ML_PRODUCTION) {
    ini_set('log_errors', '1');
}

/* ── 2. Output buffer → gzip ─────────────────────────────────── */
if (function_exists('ob_gzip_handler')) {
    ob_start('ob_gzip_handler');
} else {
    ob_start();
}

/* ── 3. Load library (Composer autoload) ─────────────────────── */
require_once __DIR__ . '/vendor/autoload.php';

use Sobhanmohammadi\Cas\Nodes\{
    MathNode, NumericNode,
    IntegerNode, RationalNode, PlusNode, MinusNode, MultiplyNode, DivideNode,
    PowerNode, UnaryNode, SqrtNode, RootNode, VariableNode, PiNode, EquationNode
};
use Sobhanmohammadi\Cas\Parser\{Lexer, Parser};
use Sobhanmohammadi\Cas\Services\{SymbolTable, Simplifier, NumericEvaluator, SymbolicEvaluator};
use Sobhanmohammadi\Cas\StepExplainer\{
    StepEvaluator,
    StepExplainer,
    StepSolver,
    SymbolicStepEvaluator,
    SymbolicStepSolver,
    StepText,
    StepRecorder
};

/* ══════════════════════════════════════════════════════════════════
   CORE EVALUATION LOGIC (replaces MathLibrary)
   ══════════════════════════════════════════════════════════════════ */

function evaluate(string $raw, array $vars, int $prec, bool $humanmode): array
{
    // Build symbol table from variable bindings
    $symTable = new SymbolTable();
    foreach ($vars as $name => $stringValue) {
        $node = NumericNode::fromDecimalString($stringValue, 0, 0);
        $symTable->assign($name, $node);
    }

    // Decide if equation or expression
    $isEquation = (strpos($raw, '=') !== false);

    try {
        if ($isEquation) {
            return evaluateEquation($raw, $vars, $symTable, $humanmode);
        } else {
            return evaluateExpression($raw, $vars, $symTable, $prec, $humanmode);
        }
    } catch (\RuntimeException $e) {
        return [
            'ok'    => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Convert any MathNode to a presentable math string.
 * NumericNode uses toMathString() (simplified fraction/integer),
 * all other nodes fall back to __toString().
 */
function nodeToString(MathNode $node): string
{
    if ($node instanceof NumericNode) {
        return $node->toMathString();
    }
    return $node->__toString();
}

function evaluateExpression(string $raw, array $vars, SymbolTable $symTable, int $prec, bool $humanmode): array
{
    if ($humanmode) {
        $evaluator = new SymbolicStepEvaluator($symTable);
        $lexer  = new Lexer($raw);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $raw);
        $ast    = $parser->parse();
        $simplified = $evaluator->evaluate($ast);
        $steps = $evaluator->getSteps();
        $resultStr = nodeToString($simplified);

        if (empty($steps)) {
            $steps = [
                new StepText(
                    'Expression simplified symbolically.',
                    'عبارت به صورت نمادین ساده شد.',
                    $resultStr,
                    $resultStr
                )
            ];
        }

        $steps[] = StepExplainer::finalSimplified($resultStr);

        return [
            'ok'           => true,
            'steps'        => stepTextToArray($steps),
            'final_result' => $resultStr,
        ];
    } else {
        $evaluator = new StepEvaluator($symTable, $prec);
        $steps = $evaluator->evaluateExpression($raw);
        $final = end($steps);
        return [
            'ok'           => true,
            'steps'        => stepTextToArray($steps),
            'final_result' => $final instanceof StepText ? $final->getCalculation() : '',
        ];
    }
}

function evaluateEquation(string $raw, array $vars, SymbolTable $symTable, bool $humanmode): array
{
    $detected = detect($raw, $vars);
    $unknowns = [];
    foreach ($detected['variables_found'] as $v) {
        if (!array_key_exists($v, $vars)) {
            $unknowns[] = $v;
        }
    }
    if (count($unknowns) !== 1) {
        throw new \RuntimeException(
            count($unknowns) === 0 ? 'All variables have values – nothing to solve.' : 'More than one unknown variable.'
        );
    }
    $unknown = $unknowns[0];

    if ($humanmode) {
        $solver = new SymbolicStepSolver($symTable);
        $steps  = $solver->solve($raw, $unknown);
        $final  = end($steps);
        // extract final answer safely
        $finalVal = $final instanceof StepText
            ? (preg_match('/[=→]\s*(.+)$/', $final->getCalculation(), $m) ? trim($m[1]) : $final->getCalculation())
            : '';
        return [
            'ok'           => true,
            'steps'        => stepTextToArray($steps),
            'final_result' => ['variable' => $unknown, 'value' => $finalVal],
        ];
    } else {
        $solver = new StepSolver($symTable);
        $steps  = $solver->solve($raw, $unknown);
        $final  = end($steps);
        $finalVal = $final instanceof StepText
            ? (preg_match('/[=→]\s*(.+)$/', $final->getCalculation(), $m) ? trim($m[1]) : $final->getCalculation())
            : '';
        return [
            'ok'           => true,
            'steps'        => stepTextToArray($steps),
            'final_result' => ['variable' => $unknown, 'value' => $finalVal],
        ];
    }
}

function stepTextToArray(array $steps): array
{
    $out = [];
    $num = 1;
    foreach ($steps as $step) {
        if (!$step instanceof StepText) continue;
        $calc = $step->getCalculation();
        $form = $step->getFormula();
        // Extract result string from calculation (last part after ' = ' or arrow)
        $after = $calc;
        if (preg_match('/[=→]\s*([^=→]+)$/', $calc, $m)) {
            $after = trim($m[1]);
        }
        $out[] = [
            'step_number'    => $num++,
            'description_en' => $step->getEn(),
            'description_fa' => $step->getFa(),
            'formula'        => $form,
            'calculation'    => $calc,
            'before'         => $form,
            'after'          => $after,
            'result'         => $after,
            'result_display' => $after,
        ];
    }
    return $out;
}

function detect(string $raw, array $vars): array
{
    $lexer  = new Lexer($raw);
    $tokens = $lexer->tokenize();
    $varNames = [];
    $hasEquals = false;
    foreach ($tokens as $tok) {
        if ($tok->getType() === \CAS\Parser\Token::IDENTIFIER) {
            $varNames[$tok->getValue()] = true;
        }
        if ($tok->getType() === \CAS\Parser\Token::EQUALS) {
            $hasEquals = true;
        }
    }
    $found = array_keys($varNames);
    return [
        'type'             => $hasEquals ? 'equation' : 'expression',
        'variables_found'  => $found,
        'has_equals'       => $hasEquals,
    ];
}

/* ══════════════════════════════════════════════════════════════════
   HELPERS (mostly unchanged from original, except shapeStep/final)
   ══════════════════════════════════════════════════════════════════ */

function shapeStep(array $s, string $lang): array
{
    return [
        'step_number'    => (int)   ($s['step_number']    ?? 0),
        'description'    => $lang === 'fa' ? $s['description_fa'] : $s['description_en'],
        'description_en' => (string) ($s['description_en'] ?? ''),
        'description_fa' => (string) ($s['description_fa'] ?? ''),
        'formula'        => (string) ($s['formula']        ?? ''),
        'calculation'    => (string) ($s['calculation']    ?? ''),
        'before'         => (string) ($s['before']         ?? ''),
        'after'          => (string) ($s['after']          ?? ''),
        'result'         => $s['result'] ?? null,
        'result_display' => (string) ($s['result_display'] ?? ''),
    ];
}

function shapeFinal($final, int $prec): array
{
    if (is_array($final)) {
        $var = (string)($final['variable'] ?? '?');
        $val = $final['value'] ?? null;
        if ($val === 'identity')    return ["{$var} = any value (identity)", 'equation'];
        if ($val === 'no_solution') return ['No solution exists.', 'equation'];
        if (is_numeric($val))       return ["{$var} = " . round((float)$val, $prec), 'equation'];
        return ["{$var} = " . (string)$val, 'equation'];
    }
    if (is_numeric($final)) return [(string) round((float)$final, $prec), 'expression'];
    return [(string)$final, 'expression'];
}

function sanitizeError(string $msg): string
{
    $msg = preg_replace('#(/[^\s:,]+|[A-Z]:\\\\[^\s:,]+)#', '[path]', $msg) ?? $msg;
    return mb_substr(trim($msg), 0, 300);
}

function classifyError(string $msg): string
{
    $lower = strtolower($msg);
    if (
        strpos($lower, 'overflow') !== false || strpos($lower, 'exceeds') !== false
        || strpos($lower, 'astronomically') !== false || strpos($lower, 'approx 10^') !== false
    ) return 'overflow_error';

    if (
        strpos($lower, 'no unknown') !== false || strpos($lower, 'equation has no unknown') !== false
        || (strpos($lower, 'unknowns') !== false && strpos($lower, 'provide') !== false)
        || strpos($lower, 'all variables have values') !== false
    ) return 'variable_error';

    if ((strpos($lower, 'sqrt') !== false || strpos($lower, 'square root') !== false) && strpos($lower, 'negative') !== false) return 'domain_error';
    if (strpos($lower, 'division by zero') !== false || strpos($lower, 'cannot divide') !== false) return 'domain_error';
    if (
        strpos($lower, 'imaginary') !== false || strpos($lower, 'nan') !== false
        || strpos($lower, 'no real value') !== false || strpos($lower, 'undefined') !== false
    ) return 'domain_error';
    if (
        strpos($lower, 'infinity') !== false || strpos($lower, 'singularity') !== false
        || strpos($lower, 'inf') !== false
    ) return 'overflow_error';

    if (
        strpos($lower, 'unexpected') !== false || strpos($lower, 'expected') !== false
        || strpos($lower, 'malformed') !== false || strpos($lower, 'unclosed') !== false
        || strpos($lower, 'missing') !== false   || strpos($lower, 'only one') !== false
        || strpos($lower, 'inside parentheses') !== false
        || strpos($lower, 'invalid character') !== false
    ) return 'syntax_error';

    return 'evaluation_error';
}

function translateError(string $msg, string $errType, string $lang): string
{
    $lower = strtolower($msg);
    if ($errType === 'overflow_error' || strpos($lower, 'overflow') !== false) {
        return $lang === 'fa'
            ? 'نتیجه بسیار بزرگ است (سرریز). توان‌های کوچک‌تری استفاده کنید.'
            : 'The result is too large (overflow). Try smaller exponent values.';
    }
    if ($errType === 'domain_error') {
        if (strpos($lower, 'negative') !== false && (strpos($lower, 'sqrt') !== false || strpos($lower, 'square root') !== false)) {
            return $lang === 'fa'
                ? 'جذر یک عدد منفی تعریف نشده است.'
                : 'Square root of a negative number is undefined.';
        }
        if (strpos($lower, 'division by zero') !== false) {
            return $lang === 'fa'
                ? 'تقسیم بر صفر تعریف نشده است.'
                : 'Division by zero is undefined.';
        }
        return $lang === 'fa'
            ? 'مقدار خارج از دامنه تعریف است.'
            : 'Value outside allowed domain.';
    }
    if ($errType === 'variable_error') {
        if (strpos($lower, 'no unknown') !== false) {
            return $lang === 'fa'
                ? 'همه متغیرها مقدار دارند – معادله‌ای برای حل وجود ندارد.'
                : 'All variables have values – nothing to solve.';
        }
        if (strpos($lower, 'unknowns') !== false) {
            return $lang === 'fa'
                ? 'بیش از یک متغیر مجهول وجود دارد.'
                : 'More than one unknown variable.';
        }
        return $lang === 'fa'
            ? 'خطای متغیر – مقادیر متغیرها را وارد کنید.'
            : 'Variable error – please enter variable values.';
    }
    if ($errType === 'syntax_error') {
        return $lang === 'fa'
            ? 'فرمت معادله نادرست است. پرانتزها را بررسی کنید.'
            : 'The expression has a formatting error.';
    }
    return sanitizeError($msg);
}

function rateLimit(): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ip   = preg_replace('/[^0-9a-fA-F.:]+/', '', $ip) ?? '0.0.0.0';
    $dir  = ML_RATE_DIR !== '' ? ML_RATE_DIR : sys_get_temp_dir();
    if (!is_dir($dir) || !is_writable($dir)) return;
    $file = $dir . '/mathlab_rl_' . md5($ip) . '.json';
    $now  = time();
    $data = ['count' => 0, 'reset' => $now + ML_RATE_WINDOW];

    $lock = $file . '.lock';
    $fh   = @fopen($lock, 'c');
    if ($fh === false) return;
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

function bail(int $code, string $msg): void
{
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

function sanitizeForJson($v)
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

function send(int $code, array $data): void
{
    http_response_code($code);
    $safe = sanitizeForJson($data);
    $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($json === false || $json === '') {
        error_log('[MathLab] json_encode failed: ' . json_last_error_msg());
        $json = json_encode([
            'valid' => false,
            'error' => 'Response could not be encoded.',
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

/* ══════════════════════════════════════════════════════════════════
   MAIN REQUEST HANDLING  (placed after all function definitions)
   ══════════════════════════════════════════════════════════════════ */

/* ── 4. Security headers ─────────────────────────────────────── */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'none'");
header('Cache-Control: no-store');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) == 443
) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header_remove('X-Powered-By');

/* ── 5. CORS: same-origin only ──────────────────────────────── */
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

/* ── 6. Method guard ────────────────────────────────────────── */
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    ob_end_clean();
    exit;
}
if (!in_array($method, ['GET', 'POST'], true)) bail(405, 'Method not allowed.');

/* ── 7. Rate limiting ───────────────────────────────────────── */
rateLimit();

/* ── 8. Input ────────────────────────────────────────────────── */
$input = array_merge($_GET, $_POST);

/* ── 9. equation (required) ─────────────────────────────────── */
$raw = isset($input['equation']) ? trim((string)$input['equation']) : '';
if ($raw === '') bail(400, 'Missing required parameter: equation.');

if (!preg_match('/^[\d\s+\-*\/^()=.,a-zA-Z_{}]+$/', $raw)) {
    bail(400, 'Expression contains invalid characters.');
}
if (strlen($raw) > ML_MAX_EXPR) {
    bail(400, 'Expression too long (max ' . ML_MAX_EXPR . ' characters).');
}

/* ── 10. humanmode (numeric vs symbolic) ────────────────────── */
$humanmode = isset($input['humanmode'])
    ? filter_var($input['humanmode'], FILTER_VALIDATE_BOOLEAN)
    : false;

/* ── 11. lang ────────────────────────────────────────────────── */
$lang = strtolower(trim((string)($input['lang'] ?? 'en')));
if (!in_array($lang, ['fa', 'en'], true)) $lang = 'en';

/* ── 12. precision (used only in numeric mode) ──────────────── */
$prec = isset($input['precision']) ? (int)$input['precision'] : 5;
$prec = max(0, min(20, $prec));

/* ── 13. vars ────────────────────────────────────────────────── */
$vars = [];
$rawV = $input['vars'] ?? [];
if (is_array($rawV)) {
    $n = 0;
    foreach ($rawV as $k => $v) {
        if (++$n > ML_MAX_VARS) break;
        $k = (string)$k;
        $v = (string)$v;
        if ($k === '_' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $k)) continue;
        if (!is_numeric($v)) continue;
        $f = (float)$v;
        if (is_nan($f) || is_infinite($f)) continue;
        $vars[$k] = $v;
    }
}

/* ── 14. ETag short-circuit ──────────────────────────────────── */
$cacheKey = md5($raw . '|' . $lang . '|' . $prec . '|' . ($humanmode ? 'sym' : 'num') . '|' . serialize($vars));
$etag     = '"ml-' . $cacheKey . '"';
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    ob_end_clean();
    exit;
}

/* ── 15. Evaluate ─────────────────────────────────────────────── */
$result = evaluate($raw, $vars, $prec, $humanmode);

if (!$result['ok']) {
    $errMsg  = sanitizeError($result['error'] ?? 'Evaluation failed.');
    $errType = classifyError($errMsg);
    $friendlyMsg = translateError($errMsg, $errType, $lang);
    error_log('[MathLab] 422 (' . $errType . '): ' . $errMsg);
    http_response_code(422);
    echo json_encode([
        'valid'         => false,
        'error'         => $friendlyMsg,
        'error_raw'     => $errMsg,
        'error_type'    => $errType,
        'code'          => 422,
        'steps'         => [],
        'final_result'  => null,
        'final_display' => null,
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

/* ── 16. Shape response ──────────────────────────────────────── */
$detected = detect($raw, $vars);

$varReport = new stdClass();
foreach ($detected['variables_found'] as $vn) {
    $varReport->$vn = array_key_exists($vn, $vars) ? $vars[$vn] : 'unknown';
}

$steps = array_map(
    fn(array $s): array => shapeStep($s, $lang),
    $result['steps']
);

[$finalDisplay, $finalType] = shapeFinal($result['final_result'], $prec);

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
    'final_result'        => $result['final_result'],
    'final_display'       => $finalDisplay,
    'lang'                => $lang,
    'precision'           => $prec,
]);