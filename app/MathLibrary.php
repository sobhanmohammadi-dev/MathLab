<?php

declare(strict_types=1);


//                        MathLibrary                            
//            Step-by-step Expression Evaluator & Equation Solver              
//                           PHP 8.2+                                           

// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 1 — CONSTANTS & LIMITS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Runtime limits and the library version string.
 * `readonly` signals that no mutable state lives here.
 */
final readonly class MathLimits
{
    public const MAX_INPUT       = 16_384;
    public const MAX_DEPTH       = 100;
    public const MAX_VARS        = 64;
    public const PRECISION       = 10;
    public const MAX_AST_CACHE   = 512;
    public const MAX_REGEX_CACHE = 2_048;
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 2 — EXCEPTION HIERARCHY
// ══════════════════════════════════════════════════════════════════════════════

/** Base class for all library-specific exceptions. */
class MathLibraryException extends \RuntimeException {}

/** Thrown when the lexer or parser cannot process the input. */
class MathParseException extends MathLibraryException {}

/** Thrown when the evaluator encounters an undefined or domain-error operation. */
class MathEvaluationException extends MathLibraryException {}

/** Thrown when the equation solver detects an unsolvable or degenerate state. */
class MathSolverException extends MathLibraryException {}

/** Thrown when a numeric computation overflows into INF or NaN. */
class MathOverflowException extends MathEvaluationException {}

/** Thrown for domain errors such as sqrt of a negative number. */
class MathDomainException extends MathEvaluationException {}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 3 — TOKEN
// ══════════════════════════════════════════════════════════════════════════════

/** Immutable token value object produced by the lexer. */
final readonly class MathToken
{
    public const NUM     = 'NUM';
    public const VAR     = 'VAR';
    public const PLUS    = '+';
    public const MINUS   = '-';
    public const STAR    = '*';
    public const SLASH   = '/';
    public const CARET   = '^';
    public const LPAREN  = '(';
    public const RPAREN  = ')';
    public const SQRT    = 'sqrt';
    public const RADICAL = 'radical';
    public const PI      = 'pi';
    public const EQ      = '=';
    public const COMMA   = ',';
    public const EOF     = 'EOF';

    public function __construct(
        public string $type,
        public mixed  $value,
        public int    $pos
    ) {}
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 4 — LEXER
// ══════════════════════════════════════════════════════════════════════════════

final class MathLexer
{
    private int $pos = 0;
    private readonly int $len;

    public function __construct(private readonly string $src)
    {
        $this->len = strlen($src);
    }

    /** @return MathToken[] */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->pos < $this->len) {
            $c = $this->src[$this->pos];

            if (ctype_space($c)) {
                $this->pos++;
                continue;
            }
            if (ctype_digit($c) || $c === '.') {
                $tokens[] = $this->readNumber();
                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $tokens[] = $this->readWord();
                continue;
            }
            if ($c === '{') {
                $tokens[] = $this->readBraced();
                continue;
            }

            $p = $this->pos++;
            $tokens[] = match ($c) {
                '+'     => new MathToken(MathToken::PLUS,   '+', $p),
                '-'     => new MathToken(MathToken::MINUS,  '-', $p),
                '*'     => new MathToken(MathToken::STAR,   '*', $p),
                '/'     => new MathToken(MathToken::SLASH,  '/', $p),
                '^'     => new MathToken(MathToken::CARET,  '^', $p),
                '('     => new MathToken(MathToken::LPAREN, '(', $p),
                ')'     => new MathToken(MathToken::RPAREN, ')', $p),
                '='     => new MathToken(MathToken::EQ,     '=', $p),
                ','     => new MathToken(MathToken::COMMA,  ',', $p),
                default => throw new MathParseException(
                    "Unexpected character '{$c}' at position {$p}."
                ),
            };
        }

        $tokens[] = new MathToken(MathToken::EOF, null, $this->pos);
        return $tokens;
    }

    private function readNumber(): MathToken
    {
        $s    = $this->pos;
        $raw  = '';
        $dots = 0;

        while ($this->pos < $this->len) {
            $c = $this->src[$this->pos];
            if (ctype_digit($c)) {
                $raw .= $c;
                $this->pos++;
            } elseif ($c === '.') {
                if ($dots > 0) {
                    throw new MathParseException(
                        "Malformed number literal: unexpected second '.' at position {$this->pos}."
                    );
                }
                $raw .= $c;
                $dots++;
                $this->pos++;
            } else {
                break;
            }
        }

        if ($raw === '' || $raw === '.') {
            throw new MathParseException("Malformed number literal at position {$s}.");
        }

        // Scientific-notation exponent: 1.5e10, 1e+3, 2.0E-4
        if (
            $this->pos < $this->len
            && ($this->src[$this->pos] === 'e' || $this->src[$this->pos] === 'E')
        ) {
            $eSave   = $this->pos;
            $rawSave = $raw;
            $raw    .= $this->src[$this->pos++];

            if (
                $this->pos < $this->len
                && ($this->src[$this->pos] === '+' || $this->src[$this->pos] === '-')
            ) {
                $raw .= $this->src[$this->pos++];
            }

            if ($this->pos >= $this->len || !ctype_digit($this->src[$this->pos])) {
                $this->pos = $eSave;
                $raw       = $rawSave;
            } else {
                while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
                    $raw .= $this->src[$this->pos++];
                }
            }
        }

        return new MathToken(MathToken::NUM, (float) $raw, $s);
    }

    private function readWord(): MathToken
    {
        $s    = $this->pos;
        $word = '';

        while (
            $this->pos < $this->len
            && (ctype_alnum($this->src[$this->pos]) || $this->src[$this->pos] === '_')
        ) {
            $word .= $this->src[$this->pos++];
        }

        return match (strtolower($word)) {
            'sqrt'    => new MathToken(MathToken::SQRT,    'sqrt',    $s),
            'radical' => new MathToken(MathToken::RADICAL, 'radical', $s),
            'pi'      => new MathToken(MathToken::PI,      'pi',      $s),
            default   => new MathToken(MathToken::VAR,     $word,     $s),
        };
    }

    private function readBraced(): MathToken
    {
        $s = $this->pos++;
        $n = '';

        while ($this->pos < $this->len && $this->src[$this->pos] !== '}') {
            $c = $this->src[$this->pos];
            if (!ctype_alnum($c) && $c !== '_') {
                throw new MathParseException(
                    "Invalid character '{$c}' inside {} at position {$this->pos}."
                );
            }
            $n .= $c;
            $this->pos++;
        }

        if ($this->pos >= $this->len) {
            throw new MathParseException("Unclosed '{' starting at position {$s}.");
        }
        $this->pos++;

        if ($n === '') {
            throw new MathParseException("Empty variable name in {} at position {$s}.");
        }

        return new MathToken(MathToken::VAR, $n, $s);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 5 — AST NODES
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Abstract base for all AST nodes.
 * $s and $e are source character positions (for step metadata and debugging).
 */
abstract class MathNode
{
    public function __construct(
        public readonly int $s = 0,
        public readonly int $e = 0,
    ) {}
}

// ── Leaf nodes ────────────────────────────────────────────────────────────────

final class NumNode extends MathNode
{
    public function __construct(public readonly float $v, int $s, int $e)
    {
        parent::__construct($s, $e);
    }
}

final class VarNode extends MathNode
{
    public function __construct(public readonly string $name, int $s, int $e)
    {
        parent::__construct($s, $e);
    }
}

final class PiNode extends MathNode
{
    public function __construct(int $s, int $e)
    {
        parent::__construct($s, $e);
    }
}

// ── Compound nodes ────────────────────────────────────────────────────────────

/**
 * Binary operator node.
 * The `$implicit` flag marks synthetic implicit-multiplication nodes (e.g. 2x)
 * and lets the evaluator phrase the merged step accordingly.
 */
final class BinNode extends MathNode
{
    public function __construct(
        public readonly MathNode $l,
        public readonly string   $op,
        public readonly MathNode $r,
        public readonly bool     $implicit = false,
    ) {
        parent::__construct($l->s, $r->e);
    }
}

final class UnaryNode extends MathNode
{
    public function __construct(
        public readonly string   $op,
        public readonly MathNode $operand,
        int $s,
    ) {
        parent::__construct($s, $operand->e);
    }
}

final class SqrtNode extends MathNode
{
    public function __construct(public readonly MathNode $arg, int $s, int $e)
    {
        parent::__construct($s, $e);
    }
}

final class RadicalNode extends MathNode
{
    public function __construct(
        public readonly MathNode $degree,
        public readonly MathNode $arg,
        int $s,
        int $e,
    ) {
        parent::__construct($s, $e);
    }
}

/**
 * Grouping node (parentheses).
 * The evaluator treats GroupNode as transparent — it evaluates the inner
 * expression and returns its value without emitting an extra step, because
 * parentheses affect parse order, not the mathematical content shown to the user.
 */
final class GroupNode extends MathNode
{
    public function __construct(public readonly MathNode $inner, int $s, int $e)
    {
        parent::__construct($s, $e);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 6 — REGEX CACHE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Memoizes variable-name validation results to avoid redundant PCRE calls.
 *
 * Pattern hardened: {1,64} length cap prevents ReDoS on pathologically long
 * identifiers. The `D` modifier (DOLLAR_ENDONLY) prevents `$` from matching
 * before embedded newlines. FIFO eviction caps memory at MAX_REGEX_CACHE.
 */
final class RegexCache
{
    /** @var array<string, bool> */
    private static array $map = [];

    /**
     * Returns true if $name is a valid, non-reserved identifier.
     * Valid identifiers start with a letter, followed by letters/digits/underscores,
     * maximum 64 characters. The lone '_' is rejected.
     */
    public static function isValidIdentifier(string $name): bool
    {
        if (array_key_exists($name, self::$map)) {
            return self::$map[$name];
        }

        $result = $name !== '_'
            && (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $name);

        if (count(self::$map) >= MathLimits::MAX_REGEX_CACHE) {
            reset(self::$map);
            unset(self::$map[key(self::$map)]);
        }

        self::$map[$name] = $result;
        return $result;
    }

    public static function clear(): void
    {
        self::$map = [];
    }
    public static function size(): int
    {
        return count(self::$map);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 7 — AST REGISTRY
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Global cache mapping expression strings to pre-parsed MathNode trees.
 *
 * Retrieval is O(1) when the same expression string is encountered again
 * (common in the solver's probe loop). FIFO eviction at MAX_AST_CACHE.
 */
final class ASTRegistry
{
    /** @var array<string, MathNode> */
    private static array $cache = [];
    private static int   $hits  = 0;

    public static function get(string $expr): ?MathNode
    {
        if (isset(self::$cache[$expr])) {
            ++self::$hits;
            return self::$cache[$expr];
        }
        return null;
    }

    public static function set(string $expr, MathNode $node): void
    {
        if (count(self::$cache) >= MathLimits::MAX_AST_CACHE) {
            reset(self::$cache);
            unset(self::$cache[key(self::$cache)]);
        }
        self::$cache[$expr] = $node;
    }

    /**
     * Returns a cached AST or builds (and caches) a fresh one.
     *
     * @throws MathParseException on lexer/parser errors
     */
    public static function getOrBuild(string $expr): MathNode
    {
        $hit = self::get($expr);
        if ($hit !== null) {
            return $hit;
        }

        try {
            $tokens = (new MathLexer($expr))->tokenize();
            $node   = (new MathParser($tokens, $expr))->parse();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new MathParseException($e->getMessage(), 0, $e);
        }

        self::set($expr, $node);
        return $node;
    }

    public static function hits(): int
    {
        return self::$hits;
    }
    public static function size(): int
    {
        return count(self::$cache);
    }

    public static function clear(): void
    {
        self::$cache = [];
        self::$hits  = 0;
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 8 — SCOPE STACK
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Lightweight nested variable-binding scope stack.
 *
 * Frames are pushed/popped around sub-evaluations that introduce local bindings
 * (e.g. the solver's probe loop substitutes the unknown at different values).
 * Inner frames shadow identically-named bindings in outer frames.
 */
final class ScopeStack
{
    /** @var array<int, array<string, float>> */
    private array $frames = [];

    /** Push a new frame with the given variable bindings. */
    public function push(array $bindings): void
    {
        $this->frames[] = $bindings;
    }

    /** Pop the innermost frame. */
    public function pop(): void
    {
        array_pop($this->frames);
    }

    /** Look up a variable from innermost to outermost frame. */
    public function lookup(string $name): ?float
    {
        for ($i = count($this->frames) - 1; $i >= 0; $i--) {
            if (array_key_exists($name, $this->frames[$i])) {
                return (float) $this->frames[$i][$name];
            }
        }
        return null;
    }

    public function has(string $name): bool
    {
        return $this->lookup($name) !== null;
    }

    /**
     * Returns a flat variable map where inner frames shadow outer ones.
     * Useful for passing the full resolved bindings to a sub-evaluator.
     */
    public function flatten(): array
    {
        $result = [];
        foreach ($this->frames as $frame) {
            foreach ($frame as $k => $v) {
                $result[$k] = $v;
            }
        }
        return $result;
    }

    public function depth(): int
    {
        return count($this->frames);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 9 — EQUATION VALIDATOR
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Pluggable constraint on what constitutes a valid equation token stream.
 * Swap in a SystemEquationValidator when systems-of-equations support arrives.
 */
interface EquationValidatorInterface
{
    /** @param MathToken[] $tokens @throws MathParseException on violation */
    public function validate(array $tokens): void;

    /** Maximum number of '=' signs this validator permits. */
    public function maxEquations(): int;
}

/**
 * Enforces exactly one '=' at the outermost level with non-empty sides.
 */
final class SingleEquationValidator implements EquationValidatorInterface
{
    public function maxEquations(): int
    {
        return 1;
    }

    /** @param MathToken[] $tokens */
    public function validate(array $tokens): void
    {
        $count      = 0;
        $depth      = 0;
        $meaningful = array_values(array_filter(
            $tokens,
            static fn(MathToken $t) => $t->type !== MathToken::EOF
        ));
        $total = count($meaningful);

        foreach ($meaningful as $i => $tok) {
            match ($tok->type) {
                MathToken::LPAREN => $depth++,
                MathToken::RPAREN => $depth--,
                MathToken::EQ     => (static function () use (
                    $tok,
                    &$count,
                    $depth,
                    $i,
                    $total
                ): void {
                    $count++;
                    if ($count > 1) {
                        throw new MathParseException(
                            "Only one '=' is allowed per expression "
                                . "(second '=' found at position {$tok->pos})."
                        );
                    }
                    if ($depth !== 0) {
                        throw new MathParseException(
                            "'=' at position {$tok->pos} is inside parentheses "
                                . "(depth {$depth}). '=' must appear at the outermost level."
                        );
                    }
                    if ($i === 0) {
                        throw new MathParseException(
                            "'=' at position {$tok->pos} has no left-hand side."
                        );
                    }
                    if ($i === $total - 1) {
                        throw new MathParseException(
                            "'=' at position {$tok->pos} has no right-hand side."
                        );
                    }
                })(),
                default => null,
            };
        }
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 10 — PARSER
// ══════════════════════════════════════════════════════════════════════════════

final class MathParser
{
    private int $pos   = 0;
    private int $depth = 0;

    /** @param MathToken[] $tokens */
    public function __construct(
        private readonly array  $tokens,
        private readonly string $src
    ) {}

    /** @throws MathParseException */
    public function parse(): MathNode
    {
        try {
            $node = $this->parseExpr(0);
        } catch (MathParseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MathParseException($e->getMessage(), 0, $e);
        }

        $cur = $this->cur();
        if ($cur->type !== MathToken::EOF && $cur->type !== MathToken::EQ) {
            throw new MathParseException(
                "Unexpected token '{$cur->value}' at position {$cur->pos}. "
                    . "Possible cause: missing operator between two terms."
            );
        }
        return $node;
    }

    // ── Core Pratt loop ───────────────────────────────────────────────────────

    /**
     * Parse an expression where all infix operators have left-binding-power
     * strictly greater than $minBP.
     *
     * The single loop handles:
     *   • explicit infix operators via infixBP()
     *   • implicit multiplication (2x, (x+1)y, 2π, etc.)
     *   • right-associativity for ^ (by using rBP < lBP)
     */
    private function parseExpr(int $minBP): MathNode
    {
        if (++$this->depth > MathLimits::MAX_DEPTH) {
            --$this->depth;
            throw new MathParseException(
                'Expression too deeply nested (max depth: ' . MathLimits::MAX_DEPTH . ').'
            );
        }

        try {
            $left = $this->parsePrefix();

            while (true) {
                // Implicit multiplication: 2x, (a+b)(c+d), 2π, 2sqrt(x)
                // Binding power 25 (tighter than explicit ×/÷ at 20).
                if ($this->isImplicitNext() && 25 > $minBP) {
                    $right = $this->parseExpr(26);
                    $left  = new BinNode($left, '*', $right, implicit: true);
                    continue;
                }

                [$lBP, $rBP] = $this->infixBP($this->cur()->type);
                if ($lBP === null || $lBP <= $minBP) {
                    break;
                }

                $op    = $this->eat()->type;
                $right = $this->parseExpr($rBP);
                $left  = new BinNode($left, $op, $right);
            }

            return $left;
        } finally {
            --$this->depth;
        }
    }

    /**
     * Parse a prefix position: unary operators or a primary atom.
     * Unary minus/plus use prefix-BP = 27 so that −x^2 → −(x²) (math convention).
     */
    private function parsePrefix(): MathNode
    {
        $t = $this->cur();

        if ($t->type === MathToken::MINUS) {
            $s = $t->pos;
            $this->eat();
            return new UnaryNode('-', $this->parseExpr(27), $s);
        }

        if ($t->type === MathToken::PLUS) {
            $this->eat();
            return $this->parseExpr(27); // unary + is the identity
        }

        return $this->parsePrimary();
    }

    /**
     * Infix binding-power table.
     * Returns [leftBP, rightBP] or [null, null] for non-infix tokens.
     * Right-associativity: leftBP > rightBP (operator re-binds its own result).
     */
    private function infixBP(string $type): array
    {
        return match ($type) {
            MathToken::PLUS, MathToken::MINUS => [10, 11],
            MathToken::STAR, MathToken::SLASH => [20, 21],
            MathToken::CARET                  => [30, 29],
            default                           => [null, null],
        };
    }

    // ── Primary atoms ─────────────────────────────────────────────────────────

    private function parsePrimary(): MathNode
    {
        $t = $this->cur();

        if ($t->type === MathToken::NUM) {
            $this->eat();
            $raw = (string) $t->value;
            return new NumNode($t->value, $t->pos, $t->pos + strlen($raw) - 1);
        }

        if ($t->type === MathToken::PI) {
            $this->eat();
            return new PiNode($t->pos, $t->pos + 1);
        }

        if ($t->type === MathToken::VAR) {
            $this->eat();
            return new VarNode($t->value, $t->pos, $t->pos + strlen((string) $t->value) - 1);
        }

        if ($t->type === MathToken::SQRT) {
            $s = $t->pos;
            $this->eat();
            $this->expect(MathToken::LPAREN, "Expected '(' after 'sqrt'");
            $arg = $this->parseExpr(0);
            $rp  = $this->expect(MathToken::RPAREN, "Missing ')' for sqrt(…)");
            return new SqrtNode($arg, $s, $rp->pos);
        }

        if ($t->type === MathToken::RADICAL) {
            $s = $t->pos;
            $this->eat();
            $this->expect(MathToken::LPAREN, "Expected '(' after 'radical'");
            $deg = $this->parseExpr(0);
            $this->expect(MathToken::COMMA, "Expected ',' in radical(degree, arg)");
            $arg = $this->parseExpr(0);
            $rp  = $this->expect(MathToken::RPAREN, "Missing ')' for radical(…)");
            return new RadicalNode($deg, $arg, $s, $rp->pos);
        }

        if ($t->type === MathToken::LPAREN) {
            $s = $t->pos;
            $this->eat();
            if ($this->cur()->type === MathToken::RPAREN) {
                throw new MathParseException("Empty parentheses at position {$s}.");
            }
            $inner = $this->parseExpr(0);
            $rp    = $this->expect(MathToken::RPAREN, "Missing ')' for '(' at {$s}");
            return new GroupNode($inner, $s, $rp->pos);
        }

        $label = $t->value ?? $t->type;
        throw new MathParseException(
            "Unexpected token '{$label}' at position {$t->pos}. "
                . "Expected a number, variable, 'pi', 'sqrt', or '('."
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cur(): MathToken
    {
        return $this->tokens[$this->pos]
            ?? new MathToken(MathToken::EOF, '', strlen($this->src));
    }

    private function eat(): MathToken
    {
        return $this->tokens[$this->pos++]
            ?? new MathToken(MathToken::EOF, '', strlen($this->src));
    }

    private function expect(string $type, string $hint = ''): MathToken
    {
        if ($this->cur()->type !== $type) {
            $got = $this->cur()->value ?? $this->cur()->type;
            $h   = $hint !== '' ? " ({$hint})" : '';
            throw new MathParseException(
                "Expected '{$type}', got '{$got}' at position {$this->cur()->pos}{$h}."
            );
        }
        return $this->eat();
    }

    /**
     * Returns true if the next token can start an implicit-multiplication factor.
     * e.g. 2x, (x+1)y, 2π, 2sqrt(x), (x+1)(x-1)
     */
    private function isImplicitNext(): bool
    {
        return match ($this->cur()->type) {
            MathToken::VAR, MathToken::NUM, MathToken::PI,
            MathToken::LPAREN, MathToken::SQRT, MathToken::RADICAL => true,
            default => false,
        };
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 11 — VARIABLE COLLECTOR
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Scope-aware AST variable scanner.
 *
 * The static collect() method returns every variable name found in the tree.
 * The static collectUnknowns() filters out variables that are already bound
 * in the given ScopeStack (inner frames shadow outer ones).
 */
final class VarCollector
{
    /** @return string[] Unique variable names found in the AST. */
    public static function collect(MathNode $root): array
    {
        $out = [];
        self::walk($root, $out);
        return array_values(array_unique($out));
    }

    /**
     * Returns variable names that are NOT resolved in the given scope stack.
     *
     * @return string[]
     */
    public static function collectUnknowns(MathNode $root, ScopeStack $scope): array
    {
        return array_values(array_filter(
            self::collect($root),
            static fn(string $name) => !$scope->has($name)
        ));
    }

    private static function walk(MathNode $node, array &$out): void
    {
        match (true) {
            $node instanceof VarNode     => ($out[] = $node->name),
            $node instanceof BinNode     => (static function () use ($node, &$out): void {
                self::walk($node->l, $out);
                self::walk($node->r, $out);
            })(),
            $node instanceof UnaryNode   => self::walk($node->operand, $out),
            $node instanceof SqrtNode    => self::walk($node->arg, $out),
            $node instanceof RadicalNode => (static function () use ($node, &$out): void {
                self::walk($node->degree, $out);
                self::walk($node->arg, $out);
            })(),
            $node instanceof GroupNode   => self::walk($node->inner, $out),
            default                      => null,
        };
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 12 — FRACTION HELPER
// ══════════════════════════════════════════════════════════════════════════════

final class MathFraction
{
    public readonly int $num;
    public readonly int $den;

    public function __construct(int $num, int $den)
    {
        if ($den === 0) {
            throw new MathEvaluationException('Fraction: denominator must not be zero.');
        }
        $g         = self::gcd(abs($num), abs($den));
        $sign      = ($den < 0) ? -1 : 1;
        $this->num = $sign * intdiv($num, $g);
        $this->den = abs(intdiv($den, $g));
    }

    public function isWhole(): bool
    {
        return $this->den === 1;
    }
    public function toFloat(): float
    {
        return $this->num / $this->den;
    }

    public function __toString(): string
    {
        return $this->isWhole() ? (string) $this->num : "{$this->num}/{$this->den}";
    }

    /**
     * Returns a fraction if both floats are finite integers; null otherwise.
     * Uses relative-epsilon for whole-number detection to handle large floats.
     */
    public static function tryFromFloats(float $a, float $b): ?self
    {
        if (!is_finite($a) || !is_finite($b) || $b == 0.0) {
            return null;
        }
        $isWholeA = abs($a - round($a)) <= 1e-9 * max(1.0, abs($a));
        $isWholeB = abs($b - round($b)) <= 1e-9 * max(1.0, abs($b));
        return ($isWholeA && $isWholeB)
            ? new self((int) round($a), (int) round($b))
            : null;
    }

    private static function gcd(int $a, int $b): int
    {
        if ($a === 0 && $b === 0) {
            return 1;
        }
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return $a;
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 13 — STEP TEXT & STEP EXPLAINER
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Immutable value object carrying the four text fields emitted in each step.
 */
final readonly class StepText
{
    public function __construct(
        public string $en,
        public string $fa,
        public string $formula,
        public string $calculation,
    ) {}
}

/**
 * Central repository for ALL human-readable step descriptions.
 *
 * Design contract:
 *   • Only text generation lives here — no math, no AST, no floats computed.
 *   • All numeric arguments arrive pre-formatted as strings (via fmt()).
 *   • Each public static method returns a StepText ready for the recorder.
 *   • English (en) and Persian (fa) strings stay in parallel.
 */
final class StepExplainer
{
    // ── Constants / substitutions ─────────────────────────────────────────────

    public static function piSubstitution(string $decFmt): StepText
    {
        return new StepText(
            en: "Substituting the numerical value of π (pi). "
                . "π is an irrational constant — the ratio of a circle's circumference to its diameter. "
                . "Substituted value: π = {$decFmt}.",
            fa: "جایگذاری مقدار عددی π (پی). π یک ثابت گنگ ریاضی است: نسبت محیط دایره به قطر آن. "
                . "مقدار عددی: π = {$decFmt}.",
            formula: "pi = {$decFmt}",
            calculation: "pi → {$decFmt}",
        );
    }

    public static function variableSubstitution(string $varName, string $valFmt): StepText
    {
        return new StepText(
            en: "Substituting variable '{$varName}' with its given value {$valFmt}. "
                . "Every occurrence of '{$varName}' in the expression is replaced by {$valFmt}.",
            fa: "جایگذاری متغیر '{$varName}' با مقدار داده‌شده {$valFmt}.",
            formula: "{$varName} = {$valFmt}",
            calculation: "{$varName} → {$valFmt}",
        );
    }

    // ── Merged substitution + operation (human-centric step) ──────────────────

    /**
     * Used when a BinNode's direct children include one or more known variables.
     * Instead of emitting separate substitution and arithmetic steps, we emit
     * one human-readable step: "Substituting x=5, we get 2(5) = 10".
     *
     * @param array<string, string> $substitutions  e.g. ['x' => '5', 'y' => '3']
     */
    public static function mergedLeafOperation(
        array  $substitutions,
        string $originalExpr,
        string $instantiated,
        string $opName,
        string $opNameFa,
        string $resFmt
    ): StepText {
        $subList = implode(', ', array_map(
            static fn($k, $v) => "{$k} = {$v}",
            array_keys($substitutions),
            array_values($substitutions)
        ));
        return new StepText(
            en: "Substituting {$subList}: {$originalExpr} → {$instantiated} = {$resFmt}.",
            fa: "جایگذاری {$subList}: {$originalExpr} → {$instantiated} = {$resFmt}.",
            formula: "{$instantiated}",
            calculation: "{$instantiated} = {$resFmt}",
        );
    }

    /**
     * Used when an entire constant-only sub-tree is collapsed into one step.
     * e.g. 2+3+4 → "Evaluating 2+3+4 = 9"
     */
    public static function constantChain(string $exprStr, string $resFmt): StepText
    {
        return new StepText(
            en: "Evaluating constant expression {$exprStr} = {$resFmt}.",
            fa: "محاسبه عبارت ثابت {$exprStr} = {$resFmt}.",
            formula: $exprStr,
            calculation: "{$exprStr} = {$resFmt}",
        );
    }

    // ── Unary ─────────────────────────────────────────────────────────────────

    public static function unaryNegation(string $origFmt, string $resFmt): StepText
    {
        return new StepText(
            en: "Negating {$origFmt}: multiplying by −1 flips the sign. −({$origFmt}) = {$resFmt}.",
            fa: "منفی کردن: −({$origFmt}) = {$resFmt}.",
            formula: "-({$origFmt})",
            calculation: "-({$origFmt}) = {$resFmt}",
        );
    }

    // ── Square root / radical ─────────────────────────────────────────────────

    public static function sqrtOperation(
        string $aFmt,
        string $vFmt,
        bool $perfect,
        int $precision,
        string $vRoundedFmt
    ): StepText {
        $note = $perfect
            ? "{$aFmt} is a perfect square ({$vRoundedFmt} × {$vRoundedFmt} = {$aFmt}), so the root is a whole number."
            : "Since {$aFmt} is not a perfect square, the result is irrational, rounded to {$precision} decimal places.";
        return new StepText(
            en: "Computing √{$aFmt}: the square root is the number that, when multiplied by itself, gives {$aFmt}. "
                . "{$note} Result: {$vFmt}.",
            fa: "محاسبه √{$aFmt}: جذر عددی است که وقتی در خودش ضرب شود برابر {$aFmt} می‌شود. نتیجه: {$vFmt}.",
            formula: "sqrt({$aFmt})",
            calculation: "sqrt({$aFmt}) = {$vFmt}",
        );
    }

    public static function radicalOperation(
        string $nFmt,
        string $aFmt,
        string $vFmt,
        string $suffix
    ): StepText {
        return new StepText(
            en: "Computing the {$nFmt}-th root ({$suffix}) of {$aFmt}. "
                . "The nth root is defined as ⁿ√a = a^(1/n). Calculation: ({$aFmt})^(1/{$nFmt}) = {$vFmt}.",
            fa: "محاسبه ریشه {$nFmt}-ام ({$suffix}) مقدار {$aFmt}. ⁿ√a = a^(1/n). نتیجه: {$vFmt}.",
            formula: "radical({$nFmt}, {$aFmt}) = ⁿ√{$aFmt}",
            calculation: "radical({$nFmt}, {$aFmt}) = {$vFmt}",
        );
    }

    // ── Symbolic binary ops ───────────────────────────────────────────────────

    public static function symbolicOperation(
        string $opName,
        string $opNameFa,
        string $lvStr,
        string $rvStr,
        string $combined,
        string $opSym
    ): StepText {
        return new StepText(
            en: "Combining terms symbolically ({$opName}). Left: {$lvStr}, Right: {$rvStr}. "
                . "Because the expression contains an unknown variable, the result is kept as a symbolic expression: {$combined}.",
            fa: "ترکیب نمادین ({$opNameFa}). چپ: {$lvStr}، راست: {$rvStr}. "
                . "چون عبارت شامل متغیر مجهول است، نتیجه به صورت نمادین باقی می‌ماند: {$combined}.",
            formula: "{$lvStr} {$opSym} {$rvStr}",
            calculation: $combined,
        );
    }

    // ── Arithmetic ────────────────────────────────────────────────────────────

    public static function addition(string $lFmt, string $rFmt, string $vFmt, bool $rIsNeg): StepText
    {
        $note = $rIsNeg ? ' (adding a negative is equivalent to subtracting ' . ltrim($rFmt, '-') . ')' : '';
        return new StepText(
            en: "Adding {$lFmt} + {$rFmt}{$note}. Result: {$vFmt}.",
            fa: "جمع: {$lFmt} + {$rFmt} = {$vFmt}.",
            formula: "{$lFmt} + {$rFmt}",
            calculation: "{$lFmt} + {$rFmt} = {$vFmt}",
        );
    }

    public static function subtraction(string $lFmt, string $rFmt, string $vFmt, bool $rIsNeg): StepText
    {
        $note = $rIsNeg ? ' (subtracting a negative is equivalent to adding ' . ltrim($rFmt, '-') . ')' : '';
        return new StepText(
            en: "Subtracting {$rFmt} from {$lFmt}{$note}. Result: {$vFmt}.",
            fa: "تفریق: {$lFmt} - {$rFmt} = {$vFmt}.",
            formula: "{$lFmt} - {$rFmt}",
            calculation: "{$lFmt} - {$rFmt} = {$vFmt}",
        );
    }

    public static function multiplicationOverflow(string $lFmt, string $rFmt): StepText
    {
        return new StepText(
            en: "Multiplying {$lFmt} × {$rFmt}. The result overflows the maximum representable number and is treated as infinity (∞).",
            fa: "ضرب {$lFmt} × {$rFmt}. نتیجه از بزرگ‌ترین عدد قابل نمایش بیشتر است و به بی‌نهایت (∞) تبدیل می‌شود.",
            formula: "{$lFmt} * {$rFmt}",
            calculation: "{$lFmt} * {$rFmt} → ∞",
        );
    }

    public static function multiplication(
        string $lFmt,
        string $rFmt,
        string $vFmt,
        bool $implicit,
        string $fracNote
    ): StepText {
        $implNote = $implicit
            ? ' (implicit multiplication — two adjacent terms without an explicit × symbol)'
            : '';
        return new StepText(
            en: "Multiplying {$lFmt} × {$rFmt}{$implNote}.{$fracNote} Result: {$vFmt}.",
            fa: "ضرب: {$lFmt} × {$rFmt} = {$vFmt}.",
            formula: "{$lFmt} * {$rFmt}",
            calculation: "{$lFmt} * {$rFmt} = {$vFmt}",
        );
    }

    public static function division(string $lFmt, string $rFmt, string $vFmt, string $fracNote): StepText
    {
        return new StepText(
            en: "Dividing {$lFmt} ÷ {$rFmt}.{$fracNote} Result: {$vFmt}.",
            fa: "تقسیم: {$lFmt} ÷ {$rFmt} = {$vFmt}.",
            formula: "({$lFmt}) / ({$rFmt})",
            calculation: "{$lFmt} / {$rFmt} = {$vFmt}",
        );
    }

    // ── Exponentiation ────────────────────────────────────────────────────────

    public static function powPreOverflow(string $lFmt, string $rFmt, int $approxExp): StepText
    {
        return new StepText(
            en: "Computing {$lFmt}^{$rFmt}. The result is astronomically large (≈ 10^{$approxExp}) and exceeds maximum float precision. Treated as ∞.",
            fa: "محاسبه {$lFmt} به توان {$rFmt}. نتیجه بسیار بزرگ است (≈ 10^{$approxExp}). به عنوان ∞ در نظر گرفته می‌شود.",
            formula: "{$lFmt}^{$rFmt}",
            calculation: "{$lFmt}^{$rFmt} ≈ 10^{$approxExp} → ∞",
        );
    }

    public static function powPostOverflow(string $lFmt, string $rFmt): StepText
    {
        return new StepText(
            en: "Computing {$lFmt}^{$rFmt}. The result exceeds the maximum storable number (overflow) and is treated as ∞.",
            fa: "محاسبه {$lFmt} به توان {$rFmt}. نتیجه از ظرفیت رایانه بیشتر است (سرریز) و ∞ در نظر گرفته می‌شود.",
            formula: "{$lFmt}^{$rFmt}",
            calculation: "{$lFmt}^{$rFmt} → ∞",
        );
    }

    public static function exponentiation(
        string $lFmt,
        string $rFmt,
        string $vFmt,
        string $typeEn,
        string $typeFa
    ): StepText {
        return new StepText(
            en: "Computing {$lFmt}^{$rFmt}. {$typeEn} Result: {$vFmt}.",
            fa: "محاسبه {$lFmt} به توان {$rFmt}. {$typeFa} نتیجه: {$vFmt}.",
            formula: "{$lFmt}^{$rFmt}",
            calculation: "{$lFmt}^{$rFmt} = {$vFmt}",
        );
    }

    /** Returns [en, fa] type-description strings for a specific exponent value. */
    public static function powTypeDescription(
        string $lFmt,
        string $rFmt,
        string $vFmt,
        float $r
    ): array {
        return match (true) {
            abs($r) < 1e-9           => ['Any non-zero number to the power 0 equals 1 (a^0 = 1).', 'هر عدد غیرصفر به توان 0 برابر 1 است.'],
            abs($r - 1.0) < 1e-9     => ['Any number to the power 1 equals itself (a^1 = a).', 'هر عدد به توان 1 برابر خودش است.'],
            abs($r - 2.0) < 1e-9     => ["Squaring: {$lFmt} × {$lFmt} = {$vFmt}.", "مربع کردن: {$lFmt} × {$lFmt} = {$vFmt}."],
            abs($r - 3.0) < 1e-9     => ["Cubing: {$lFmt}³ = {$vFmt}.", "مکعب کردن: {$lFmt}³ = {$vFmt}."],
            $r < 0.0                 => ["Negative exponent: a^(−b) = 1/(a^b) = {$vFmt}.", "توان منفی: 1/(a^b) = {$vFmt}."],
            default                  => [
                "Repeated multiplication: {$lFmt} multiplied by itself " . (int) abs($r) . ' times.',
                'ضرب مکرر: ' . (int) abs($r) . " بار ضرب شده.",
            ],
        };
    }

    // ── Solver steps ──────────────────────────────────────────────────────────

    public static function solverStart(string $eq, string $unk): StepText
    {
        return new StepText(
            en: "Start with the original equation: {$eq}. Goal: isolate '{$unk}' and find its exact numerical value.",
            fa: "با معادله اصلی شروع می‌کنیم: {$eq}. هدف: جدا کردن '{$unk}' و یافتن مقدار عددی آن.",
            formula: $eq,
            calculation: $eq,
        );
    }

    public static function solverSimplify(
        string $unk,
        string $lhsConstFmt,
        string $lhsCoeffFmt,
        string $rhsConstFmt,
        string $rhsCoeffFmt,
        string $equationFmt
    ): StepText {
        return new StepText(
            en: "Simplify the known (constant) values on each side. "
                . "Left side constant: {$lhsConstFmt}. Left coefficient of {$unk}: {$lhsCoeffFmt}. "
                . "Right side constant: {$rhsConstFmt}. Right coefficient of {$unk}: {$rhsCoeffFmt}. "
                . "Equation: {$equationFmt}.",
            fa: "مقادیر ثابت را در هر طرف ساده می‌کنیم. "
                . "طرف چپ ثابت: {$lhsConstFmt}، ضریب {$unk}: {$lhsCoeffFmt}. "
                . "طرف راست ثابت: {$rhsConstFmt}، ضریب {$unk}: {$rhsCoeffFmt}. "
                . "معادله: {$equationFmt}.",
            formula: $equationFmt,
            calculation: $equationFmt,
        );
    }

    public static function solverCollect(
        string $unk,
        string $lhsCoeffFmt,
        string $rhsCoeffFmt,
        string $netCoeffFmt,
        string $lhsConstFmt,
        string $rhsConstFmt,
        string $netRhsFmt,
        string $resultFmt
    ): StepText {
        return new StepText(
            en: "Collect all terms containing '{$unk}' on the LEFT and all constants on the RIGHT. "
                . "Subtract {$rhsCoeffFmt}·{$unk} from both sides → net coefficient: {$lhsCoeffFmt} − {$rhsCoeffFmt} = {$netCoeffFmt}. "
                . "Subtract {$lhsConstFmt} from both sides → right side: {$rhsConstFmt} − {$lhsConstFmt} = {$netRhsFmt}. "
                . "Result: {$resultFmt}.",
            fa: "همه جملات شامل '{$unk}' را به طرف چپ و همه ثابت‌ها را به طرف راست منتقل می‌کنیم. "
                . "ضریب خالص {$unk}: {$lhsCoeffFmt} − {$rhsCoeffFmt} = {$netCoeffFmt}. "
                . "طرف راست: {$rhsConstFmt} − {$lhsConstFmt} = {$netRhsFmt}. "
                . "نتیجه: {$resultFmt}.",
            formula: $resultFmt,
            calculation: $resultFmt,
        );
    }

    public static function solverDegenerate(string $unk, bool $isIdentity, string $constFmt): StepText
    {
        if ($isIdentity) {
            return new StepText(
                en: "The coefficient of '{$unk}' is 0 on both sides — '{$unk}' cancels out entirely. "
                    . "The equation reduces to 0 = 0, which is ALWAYS true. "
                    . "Therefore 0·{$unk} = 0 has INFINITELY MANY solutions: {$unk} can be any real number.",
                fa: "ضریب '{$unk}' صفر است — '{$unk}' از هر دو طرف حذف می‌شود. "
                    . "معادله به 0 = 0 تبدیل می‌شود که همیشه درست است. بی‌نهایت جواب وجود دارد.",
                formula: "0·{$unk} = 0",
                calculation: "0 = 0 → ∞ solutions",
            );
        }
        return new StepText(
            en: "The coefficient of '{$unk}' is 0, but the constant term is {$constFmt} ≠ 0. "
                . "The equation reduces to {$constFmt} = 0, which is NEVER true. "
                . "Therefore 0·{$unk} = {$constFmt} has NO solution.",
            fa: "ضریب '{$unk}' صفر است، ولی ثابت {$constFmt} ≠ 0. "
                . "معادله به {$constFmt} = 0 تبدیل می‌شود که هرگز درست نیست. جوابی وجود ندارد.",
            formula: "0·{$unk} = {$constFmt}",
            calculation: "{$constFmt} = 0 → no solution",
        );
    }

    public static function solverDivideIsolated(string $unk, string $solFmt): StepText
    {
        return new StepText(
            en: "The coefficient of '{$unk}' is already 1, so '{$unk}' is directly isolated. Therefore: {$unk} = {$solFmt}.",
            fa: "ضریب '{$unk}' از قبل 1 است، بنابراین '{$unk}' مستقیماً جدا شده است: {$unk} = {$solFmt}.",
            formula: "{$unk} = {$solFmt}",
            calculation: "{$unk} = {$solFmt}",
        );
    }

    public static function solverDivide(
        string $unk,
        string $netCoeffFmt,
        string $netRhsFmt,
        string $solFmt
    ): StepText {
        return new StepText(
            en: "Divide BOTH sides by the coefficient {$netCoeffFmt} to isolate '{$unk}': "
                . "{$netCoeffFmt}·{$unk} ÷ {$netCoeffFmt} = {$netRhsFmt} ÷ {$netCoeffFmt}. "
                . "Result: {$unk} = {$solFmt}.",
            fa: "هر دو طرف را بر ضریب {$netCoeffFmt} تقسیم می‌کنیم: "
                . "{$netRhsFmt} ÷ {$netCoeffFmt} = {$solFmt}. "
                . "نتیجه: {$unk} = {$solFmt}.",
            formula: "{$unk} = {$netRhsFmt} ÷ {$netCoeffFmt}",
            calculation: "{$unk} = {$solFmt}",
        );
    }

    public static function solverNonLinear(string $unk, string $solFmt, string $devDetail): StepText
    {
        return new StepText(
            en: "⚠ Non-linear equation detected. The linear model fails at: {$devDetail}. "
                . "The value {$unk} = {$solFmt} is a LINEAR APPROXIMATION only — it may not be an exact root, "
                . "and the equation may have additional roots. For a complete solution use Newton–Raphson, bisection, or a CAS.",
            fa: "⚠ معادله غیرخطی تشخیص داده شد. مدل خطی در نقاط زیر خطا دارد: {$devDetail}. "
                . "مقدار {$unk} = {$solFmt} فقط یک تقریب خطی است و ممکن است ریشه‌های دیگری نیز وجود داشته باشد.",
            formula: "Non-linear — linear approximation only",
            calculation: "{$unk} ≈ {$solFmt} (approximation)",
        );
    }

    public static function solverVerify(
        string $unk,
        string $solFmt,
        string $eq,
        string $lhsFmt,
        string $rhsFmt,
        string $diffFmt,
        bool $ok
    ): StepText {
        $verdictEn = $ok
            ? "Both sides equal {$lhsFmt} ✓ — the answer {$unk} = {$solFmt} is CORRECT."
            : "Difference = {$diffFmt} ⚠ — the answer is a close approximation (due to non-linearity).";
        $verdictFa = $ok
            ? "هر دو طرف برابر {$lhsFmt} هستند ✓ — جواب {$unk} = {$solFmt} صحیح است."
            : "تفاوت = {$diffFmt} ⚠ — جواب تقریبی است.";
        return new StepText(
            en: "Verify by substituting {$unk} = {$solFmt} into {$eq}. "
                . "Left side = {$lhsFmt}. Right side = {$rhsFmt}. {$verdictEn}",
            fa: "جواب را با جایگذاری {$unk} = {$solFmt} در معادله تأیید می‌کنیم. "
                . "طرف چپ: {$lhsFmt}، طرف راست: {$rhsFmt}. {$verdictFa}",
            formula: "Substitute {$unk} = {$solFmt} → LHS = {$lhsFmt}, RHS = {$rhsFmt}",
            calculation: "{$unk} = {$solFmt} → LHS = {$lhsFmt}, RHS = {$rhsFmt} " . ($ok ? "✓" : "⚠"),
        );
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 14 — EVALUATOR
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Human-centric AST evaluator with intelligent step reduction.
 *
 * Step-reduction strategy (mirrors how a human would write out their work):
 *
 *   1. GroupNode (parentheses) — TRANSPARENT. Parentheses control parse order
 *      but a human does not write "evaluated parentheses" as a separate step.
 *      The inner expression's steps appear directly.
 *
 *   2. Constant-chain collapsing — When an entire BinNode sub-tree consists
 *      only of numeric literals (no variables), it is computed in one step
 *      rather than one step per binary node.
 *      Example: 2+3+4 → "Evaluating 2+3+4 = 9" (one step, not two).
 *
 *   3. Merged substitution + arithmetic — When a BinNode's direct children are
 *      leaf nodes (numbers or known variables), the variable substitution and
 *      the arithmetic are merged into a single step.
 *      Example: 2x where x=5 → "Substituting x=5: 2(5) = 10" (one step).
 */
final class MathEvaluator
{
    private array $steps   = [];
    private int   $stepNum;

    /**
     * Per-evaluation node cache (short-circuit for repeated sub-trees).
     * Must be cleared when variable bindings change between calls on the same AST.
     */
    private array $cache = [];

    public function __construct(
        private readonly array  $vars,
        private readonly string $src,
        private readonly int    $precision,
        int                     $stepOffset = 0
    ) {
        $this->stepNum = $stepOffset;
    }

    /**
     * Evaluate an AST node, recording human-readable steps.
     *
     * @param bool $clearCache  Set true when variable bindings differ from the
     *                          previous call on the same AST object.
     */
    public function evaluate(MathNode $node, bool $clearCache = false): float|string
    {
        if ($clearCache) {
            $this->cache = [];
        }

        $id = spl_object_id($node);
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        try {
            $result = $this->dispatch($node);
        } catch (MathLibraryException $e) {
            throw $e;
        } catch (\DivisionByZeroError | \ArithmeticError $e) {
            throw new MathEvaluationException('Arithmetic error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new MathEvaluationException($e->getMessage(), 0, $e);
        }

        $this->cache[$id] = $result;
        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function getSteps(): array
    {
        return $this->steps;
    }
    public function getStepNum(): int
    {
        return $this->stepNum;
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    private function dispatch(MathNode $node): float|string
    {
        return match (true) {
            $node instanceof NumNode     => $node->v,
            $node instanceof PiNode      => $this->ePi($node),
            $node instanceof VarNode     => $this->eVar($node),
            $node instanceof UnaryNode   => $this->eUnary($node),
            $node instanceof SqrtNode    => $this->eSqrt($node),
            $node instanceof RadicalNode => $this->eRadical($node),
            $node instanceof GroupNode   => $this->eGroup($node),
            $node instanceof BinNode     => $this->eBin($node),
            default => throw new MathEvaluationException(
                'Unknown AST node type: ' . get_class($node)
            ),
        };
    }

    // ── Node evaluators ───────────────────────────────────────────────────────

    private function ePi(PiNode $node): float
    {
        $v    = M_PI;
        $text = StepExplainer::piSubstitution(number_format($v, $this->precision, '.', ''));
        $this->rec('constant_substitution', $text, $v, $node, [
            'operation_type' => 'constant_substitution',
            'constant_name'  => 'pi',
            'is_irrational'  => true,
        ]);
        return $v;
    }

    private function eVar(VarNode $node): float|string
    {
        if (array_key_exists($node->name, $this->vars)) {
            $v    = (float) $this->vars[$node->name];
            $text = StepExplainer::variableSubstitution($node->name, $this->f($v));
            $this->rec('variable_substitution', $text, $v, $node, [
                'operation_type'    => 'variable_substitution',
                'variable_name'     => $node->name,
                'substituted_value' => $v,
            ]);
            return $v;
        }
        return '{' . $node->name . '}';
    }

    private function eUnary(UnaryNode $node): float|string
    {
        $val = $this->evaluate($node->operand);

        if ($node->op === '+') {
            return $val; // identity — no step needed
        }

        if (!is_numeric($val)) {
            return '-(' . $val . ')';
        }

        $orig = (float) $val;
        $v    = -$orig;
        $text = StepExplainer::unaryNegation($this->f($orig), $this->f($v));
        $this->rec('unary_negation', $text, $v, $node, [
            'operation_type' => 'unary_negation',
            'operand_value'  => $orig,
        ]);
        return $v;
    }

    private function eSqrt(SqrtNode $node): float|string
    {
        $val = $this->evaluate($node->arg);

        if (!is_numeric($val)) {
            return 'sqrt(' . $val . ')';
        }

        $a = (float) $val;
        if ($a < 0.0) {
            throw new MathDomainException(
                "Cannot take the square root of a negative number: √({$a}). "
                    . "Square roots of negative numbers are imaginary."
            );
        }

        $v        = sqrt($a);
        $vRounded = round($v);
        $perfect  = (abs($vRounded - $v) < 1e-9) && (abs($vRounded * $vRounded - $a) < 1e-9);
        $text     = StepExplainer::sqrtOperation(
            $this->f($a),
            $this->f($v),
            $perfect,
            $this->precision,
            $this->f($vRounded)
        );
        $this->rec('square_root', $text, $v, $node, [
            'operation_type'    => 'square_root',
            'argument_value'    => $a,
            'is_perfect_square' => $perfect,
        ]);
        return $v;
    }

    private function eRadical(RadicalNode $node): float|string
    {
        $degVal = $this->evaluate($node->degree);
        $argVal = $this->evaluate($node->arg);

        if (!is_numeric($degVal) || !is_numeric($argVal)) {
            $dStr = is_numeric($degVal) ? $this->f((float) $degVal) : (string) $degVal;
            $aStr = is_numeric($argVal) ? $this->f((float) $argVal) : (string) $argVal;
            return "radical({$dStr}, {$aStr})";
        }

        $n = (float) $degVal;
        $a = (float) $argVal;

        if (abs($n) < 1e-12) {
            throw new MathDomainException('radical: degree must not be zero.');
        }
        $isEvenDeg = (fmod(abs($n), 2.0) < 1e-9);
        if ($isEvenDeg && $a < 0.0) {
            throw new MathDomainException(
                "radical: cannot compute even-degree ({$this->f($n)}) root of a negative number ({$this->f($a)})."
            );
        }

        $v      = ($a < 0.0) ? - ((-$a) ** (1.0 / $n)) : ($a ** (1.0 / $n));
        $nInt   = (int) round(abs($n));
        $suffix = match ($nInt) {
            2 => 'square root',
            3 => 'cube root',
            default => "order-{$nInt} root"
        };
        $text   = StepExplainer::radicalOperation($this->f($n), $this->f($a), $this->f($v), $suffix);
        $this->rec('nth_root', $text, $v, $node, [
            'operation_type' => 'nth_root',
            'degree'         => $n,
            'argument_value' => $a,
        ]);
        return $v;
    }

    /**
     * GroupNode is transparent — parentheses affect parse order only.
     * No "parentheses resolved" step is emitted; the inner expression's
     * own steps appear directly in the output.
     */
    private function eGroup(GroupNode $node): float|string
    {
        return $this->evaluate($node->inner);
    }

    private function eBin(BinNode $node): float|string
    {
        // ── Constant-chain collapsing ─────────────────────────────────────────
        // If the entire subtree contains only numeric literals (no variables, no
        // pi, no functions), collapse it into a single step regardless of depth.
        // This covers cases like 2+3+4 (normally two BinNode steps → one step).
        if ($this->isConstantOnlyTree($node)) {
            $v = $this->computeConstantQuiet($node);
            if ($v !== null) {
                if (!is_finite($v)) {
                    throw new MathOverflowException(
                        'Overflow in constant expression: ' . $this->nodeToString($node)
                    );
                }
                $exprStr = $this->nodeToString($node);
                $text    = StepExplainer::constantChain($exprStr, $this->f($v));
                $this->rec('arithmetic', $text, $v, $node, [
                    'operation_type' => 'constant_chain',
                    'result_value'   => $v,
                ]);
                return $v;
            }
        }

        // ── Merged leaf substitution + arithmetic ─────────────────────────────
        // When both immediate children are leaves (NumNode / VarNode / PiNode)
        // and at least one is a known variable, merge substitution and arithmetic
        // into one step instead of emitting a substitution step then an arithmetic step.
        $lLeaf = $this->isLeaf($node->l);
        $rLeaf = $this->isLeaf($node->r);

        if ($lLeaf && $rLeaf) {
            $lv = $this->leafValue($node->l);
            $rv = $this->leafValue($node->r);

            $lHasVar = ($node->l instanceof VarNode && array_key_exists($node->l->name, $this->vars))
                || ($node->l instanceof PiNode);
            $rHasVar = ($node->r instanceof VarNode && array_key_exists($node->r->name, $this->vars))
                || ($node->r instanceof PiNode);

            if ($lv !== null && $rv !== null && ($lHasVar || $rHasVar)) {
                return $this->eBinMergedLeaf($node, $lv, $rv, $lHasVar, $rHasVar);
            }
        }

        // ── Standard recursive path ───────────────────────────────────────────
        $lv = $this->evaluate($node->l);
        $rv = $this->evaluate($node->r);

        if (!is_numeric($lv) || !is_numeric($rv)) {
            return $this->eBinSymbolic($node, $lv, $rv);
        }

        $l = (float) $lv;
        $r = (float) $rv;

        return match ($node->op) {
            '+' => $this->oAdd($l, $r, $node),
            '-' => $this->oSub($l, $r, $node),
            '*' => $this->oMul($l, $r, $node),
            '/' => $this->oDiv($l, $r, $node),
            '^' => $this->oPow($l, $r, $node),
            default => throw new MathEvaluationException("Unknown binary operator '{$node->op}'."),
        };
    }

    /**
     * Emits a single merged step: "Substituting x=5: 2(5) = 10".
     * Called when both BinNode children are leaves with at least one known variable.
     */
    private function eBinMergedLeaf(
        BinNode $node,
        float $lv,
        float $rv,
        bool $lHasVar,
        bool $rHasVar
    ): float {
        $substitutions = [];

        if ($node->l instanceof VarNode && $lHasVar) {
            $substitutions[$node->l->name] = $this->f($lv);
        } elseif ($node->l instanceof PiNode && $lHasVar) {
            $substitutions['π'] = $this->f($lv);
        }
        if ($node->r instanceof VarNode && $rHasVar) {
            $substitutions[$node->r->name] = $this->f($rv);
        } elseif ($node->r instanceof PiNode && $rHasVar) {
            $substitutions['π'] = $this->f($rv);
        }

        $lOrigStr = $this->leafOrigStr($node->l);
        $rOrigStr = $this->leafOrigStr($node->r);
        $lSubStr  = $this->f($lv);
        $rSubStr  = $this->f($rv);

        $opSym = match ($node->op) {
            '+' => '+',
            '-' => '−',
            '*' => ($node->implicit ? '' : '×'),
            '/' => '÷',
            '^' => '^',
            default => $node->op,
        };

        $originalExpr  = $lOrigStr . ($opSym !== '' ? " {$opSym} " : '') . $rOrigStr;
        $instantiated  = $node->implicit
            ? "{$lSubStr}({$rSubStr})"
            : "{$lSubStr} {$opSym} {$rSubStr}";

        $v = match ($node->op) {
            '+' => $lv + $rv,
            '-' => $lv - $rv,
            '*' => $lv * $rv,
            '/' => abs($rv) < 1e-300
                ? throw new MathDomainException(
                    "Division by zero: cannot divide {$this->f($lv)} by 0."
                )
                : $lv / $rv,
            '^' => $lv ** $rv,
            default => throw new MathEvaluationException("Unknown operator '{$node->op}'."),
        };

        if (!is_finite($v)) {
            // Fall through to standard overflow handling
            return match ($node->op) {
                '*' => $this->oMul($lv, $rv, $node),
                '^' => $this->oPow($lv, $rv, $node),
                default => throw new MathOverflowException(
                    "Overflow in {$originalExpr}."
                ),
            };
        }

        [$opName, $opNameFa] = $this->opLabels($node->op);
        $text = StepExplainer::mergedLeafOperation(
            $substitutions,
            $originalExpr,
            $instantiated,
            $opName,
            $opNameFa,
            $this->f($v)
        );
        $this->rec($opName, $text, $v, $node, [
            'operation_type'  => $opName . '_merged',
            'left_operand'    => $lv,
            'right_operand'   => $rv,
            'substitutions'   => $substitutions,
            'result_value'    => $v,
        ]);
        return $v;
    }

    /** Symbolic (unknown variable present) binary operation. */
    private function eBinSymbolic(BinNode $node, float|string $lv, float|string $rv): string
    {
        [$opName, $opNameFa] = $this->opLabels($node->op);
        $opSym = match ($node->op) {
            '+' => '+',
            '-' => '−',
            '*' => '×',
            '/' => '/',
            '^' => '^',
            default => $node->op,
        };
        $lvStr    = is_numeric($lv) ? $this->f((float) $lv) : (string) $lv;
        $rvStr    = is_numeric($rv) ? $this->f((float) $rv) : (string) $rv;
        $combined = "({$lvStr} {$opSym} {$rvStr})";
        $text     = StepExplainer::symbolicOperation($opName, $opNameFa, $lvStr, $rvStr, $combined, $opSym);
        $this->rec($opName, $text, $combined, $node, [
            'operation_type' => $opName,
            'is_symbolic'    => true,
        ]);
        return $combined;
    }

    // ── Arithmetic helpers ────────────────────────────────────────────────────

    private function oAdd(float $l, float $r, BinNode $n): float
    {
        $v    = $l + $r;
        $text = StepExplainer::addition($this->f($l), $this->f($r), $this->f($v), $r < 0);
        $this->rec('addition', $text, $v, $n, [
            'operation_type' => 'addition',
            'left_operand'   => $l,
            'right_operand'  => $r,
            'result_value'   => $v,
        ]);
        return $v;
    }

    private function oSub(float $l, float $r, BinNode $n): float
    {
        $v    = $l - $r;
        $text = StepExplainer::subtraction($this->f($l), $this->f($r), $this->f($v), $r < 0);
        $this->rec('subtraction', $text, $v, $n, [
            'operation_type' => 'subtraction',
            'left_operand'   => $l,
            'right_operand'  => $r,
            'result_value'   => $v,
        ]);
        return $v;
    }

    private function oMul(float $l, float $r, BinNode $n): float
    {
        $v = $l * $r;

        if (is_infinite($v)) {
            $text = StepExplainer::multiplicationOverflow($this->f($l), $this->f($r));
            $this->rec('multiplication', $text, INF, $n, [
                'operation_type' => 'multiplication',
                'left_operand'   => $l,
                'right_operand'  => $r,
                'overflow'       => true,
            ]);
            return INF;
        }

        $fracNote = '';
        $fracL    = MathFraction::tryFromFloats($l, 1.0);
        $fracR    = MathFraction::tryFromFloats($r, 1.0);
        if ($fracL !== null && !$fracL->isWhole()) $fracNote .= " ({$this->f($l)} = {$fracL})";
        if ($fracR !== null && !$fracR->isWhole()) $fracNote .= " ({$this->f($r)} = {$fracR})";

        $text = StepExplainer::multiplication($this->f($l), $this->f($r), $this->f($v), $n->implicit, $fracNote);
        $this->rec('multiplication', $text, $v, $n, [
            'operation_type' => 'multiplication',
            'left_operand'   => $l,
            'right_operand'  => $r,
            'is_implicit'    => $n->implicit,
            'result_value'   => $v,
        ]);
        return $v;
    }

    private function oDiv(float $l, float $r, BinNode $n): float
    {
        if (abs($r) < 1e-300) {
            throw new MathDomainException(
                "Division by zero: cannot divide {$this->f($l)} by 0. Division by zero is undefined."
            );
        }
        $v        = $l / $r;
        $frac     = MathFraction::tryFromFloats($l, $r);
        $fracNote = ($frac !== null && !$frac->isWhole()) ? " In simplified fraction form: {$frac}." : '';
        $text     = StepExplainer::division($this->f($l), $this->f($r), $this->f($v), $fracNote);
        $this->rec('division', $text, $v, $n, [
            'operation_type' => 'division',
            'left_operand'   => $l,
            'right_operand'  => $r,
            'result_value'   => $v,
        ]);
        return $v;
    }

    private function oPow(float $l, float $r, BinNode $n): float
    {
        // Pre-overflow check via logarithm (avoids computing the number at all)
        if ($l !== 0.0 && abs($r) > 1000) {
            $logMag = abs($r) * log10(abs($l));
            if ($logMag > 308) {
                $sign      = ($l < 0.0 && fmod(abs($r), 2.0) >= 0.5) ? -INF : INF;
                $approxExp = (int) round($logMag);
                $text      = StepExplainer::powPreOverflow($this->f($l), $this->f($r), $approxExp);
                $this->rec('exponentiation', $text, $sign, $n, [
                    'operation_type' => 'exponentiation',
                    'base'           => $l,
                    'exponent'       => $r,
                    'overflow'       => true,
                    'approx_log10'   => $approxExp,
                ]);
                return $sign;
            }
        }

        $v = $l ** $r;

        if (is_infinite($v)) {
            $text = StepExplainer::powPostOverflow($this->f($l), $this->f($r));
            $this->rec('exponentiation', $text, INF, $n, [
                'operation_type' => 'exponentiation',
                'base'           => $l,
                'exponent'       => $r,
                'overflow'       => true,
            ]);
            return INF;
        }

        if (is_nan($v)) {
            throw new MathDomainException(
                "Undefined: {$this->f($l)}^{$this->f($r)} has no real value. "
                    . "Check for 0^0 or a negative base with a fractional exponent."
            );
        }

        [$typeEn, $typeFa] = StepExplainer::powTypeDescription($this->f($l), $this->f($r), $this->f($v), $r);
        $text = StepExplainer::exponentiation($this->f($l), $this->f($r), $this->f($v), $typeEn, $typeFa);
        $this->rec('exponentiation', $text, $v, $n, [
            'operation_type' => 'exponentiation',
            'base'           => $l,
            'exponent'       => $r,
            'result_value'   => $v,
        ]);
        return $v;
    }

    // ── Step recorder ─────────────────────────────────────────────────────────

    private function rec(
        string       $operation,
        StepText     $text,
        float|string $result,
        MathNode     $node,
        array        $extra = []
    ): void {
        $exprPart = substr($this->src, $node->s, max(1, $node->e - $node->s + 1));
        $nodeType = match (true) {
            $node instanceof NumNode     => 'NumNode',
            $node instanceof VarNode     => 'VarNode',
            $node instanceof PiNode      => 'PiNode',
            $node instanceof BinNode     => 'BinNode',
            $node instanceof UnaryNode   => 'UnaryNode',
            $node instanceof SqrtNode    => 'SqrtNode',
            $node instanceof RadicalNode => 'RadicalNode',
            $node instanceof GroupNode   => 'GroupNode',
            default                      => get_class($node),
        };

        $this->steps[] = [
            'step_number'    => ++$this->stepNum,
            'description_en' => $text->en,
            'description_fa' => $text->fa,
            'formula'        => $text->formula,
            'calculation'    => $text->calculation,
            'result'         => is_numeric($result) ? (float) $result : $result,
            'metadata'       => array_merge([
                'start_index'     => $node->s,
                'end_index'       => $node->e,
                'expression_part' => $exprPart,
                'char_count'      => strlen($exprPart),
                'node_type'       => $nodeType,
                'operation'       => $operation,
                'precision'       => $this->precision,
                'is_symbolic'     => !is_numeric($result),
                'result_display'  => is_numeric($result)
                    ? $this->f((float) $result)
                    : (string) $result,
            ], $extra),
        ];
    }

    // ── Step-reduction helpers ────────────────────────────────────────────────

    /**
     * Returns true if the node tree contains ONLY NumNode leaves
     * (no variables, no pi, no functions). These are safe to collapse.
     */
    private function isConstantOnlyTree(MathNode $node): bool
    {
        return match (true) {
            $node instanceof NumNode   => true,
            $node instanceof BinNode   => $this->isConstantOnlyTree($node->l)
                && $this->isConstantOnlyTree($node->r),
            $node instanceof UnaryNode => $this->isConstantOnlyTree($node->operand),
            $node instanceof GroupNode => $this->isConstantOnlyTree($node->inner),
            default                    => false,
        };
    }

    /**
     * Computes the value of a constant-only tree without emitting any steps.
     * Returns null on overflow or domain error (caller falls back to normal path).
     */
    private function computeConstantQuiet(MathNode $node): ?float
    {
        return match (true) {
            $node instanceof NumNode => $node->v,
            $node instanceof GroupNode => $this->computeConstantQuiet($node->inner),
            $node instanceof UnaryNode => (function () use ($node): ?float {
                $v = $this->computeConstantQuiet($node->operand);
                return $v === null ? null : ($node->op === '-' ? -$v : $v);
            })(),
            $node instanceof BinNode => (function () use ($node): ?float {
                $l = $this->computeConstantQuiet($node->l);
                $r = $this->computeConstantQuiet($node->r);
                if ($l === null || $r === null) {
                    return null;
                }
                if ($node->op === '/' && abs($r) < 1e-300) {
                    return null;
                }
                $v = match ($node->op) {
                    '+' => $l + $r,
                    '-' => $l - $r,
                    '*' => $l * $r,
                    '/' => $l / $r,
                    '^' => $l ** $r,
                    default => null,
                };
                return ($v === null || !is_finite($v)) ? null : $v;
            })(),
            default => null,
        };
    }

    /**
     * Renders an AST sub-tree as a human-readable string for the constant-chain step.
     */
    private function nodeToString(MathNode $node): string
    {
        return match (true) {
            $node instanceof NumNode    => $this->f($node->v),
            $node instanceof PiNode     => 'π',
            $node instanceof VarNode    => $node->name,
            $node instanceof GroupNode  => '(' . $this->nodeToString($node->inner) . ')',
            $node instanceof UnaryNode  => '-(' . $this->nodeToString($node->operand) . ')',
            $node instanceof BinNode    => (function () use ($node): string {
                $l   = $this->nodeToString($node->l);
                $r   = $this->nodeToString($node->r);
                $sym = match ($node->op) {
                    '+' => ' + ',
                    '-' => ' - ',
                    '*' => ($node->implicit ? '' : ' × '),
                    '/' => ' ÷ ',
                    '^' => '^',
                    default => " {$node->op} ",
                };
                return "{$l}{$sym}{$r}";
            })(),
            $node instanceof SqrtNode   => 'sqrt(' . $this->nodeToString($node->arg) . ')',
            $node instanceof RadicalNode => 'radical(' . $this->nodeToString($node->degree)
                . ', ' . $this->nodeToString($node->arg) . ')',
            default => '?',
        };
    }

    /** Returns true if the node is a leaf (NumNode, VarNode, or PiNode). */
    private function isLeaf(MathNode $node): bool
    {
        return $node instanceof NumNode
            || $node instanceof VarNode
            || $node instanceof PiNode;
    }

    /**
     * Returns the float value of a leaf node if it is resolvable, or null.
     * Does NOT emit any steps.
     */
    private function leafValue(MathNode $node): ?float
    {
        return match (true) {
            $node instanceof NumNode => $node->v,
            $node instanceof PiNode  => M_PI,
            $node instanceof VarNode => array_key_exists($node->name, $this->vars)
                ? (float) $this->vars[$node->name]
                : null,
            default => null,
        };
    }

    /**
     * Returns the display label for a leaf node as it appears in the original expression.
     * VarNodes → their name, PiNode → 'π', NumNode → formatted value.
     */
    private function leafOrigStr(MathNode $node): string
    {
        return match (true) {
            $node instanceof VarNode => $node->name,
            $node instanceof PiNode  => 'π',
            $node instanceof NumNode => $this->f($node->v),
            default => '?',
        };
    }

    /** Returns [en-label, fa-label] for a binary operator. */
    private function opLabels(string $op): array
    {
        return match ($op) {
            '+' => ['addition',       'جمع'],
            '-' => ['subtraction',    'تفریق'],
            '*' => ['multiplication', 'ضرب'],
            '/' => ['division',       'تقسیم'],
            '^' => ['exponentiation', 'توان'],
            default => ['operation',  'عملیات'],
        };
    }

    // ── Formatting helpers ────────────────────────────────────────────────────

    public function f(float $v): string
    {
        return self::fmt($v, $this->precision);
    }

    public static function fmt(float $v, int $prec = MathLimits::PRECISION): string
    {
        if (is_nan($v))      return 'NaN';
        if (is_infinite($v)) return $v > 0.0 ? '∞' : '-∞';
        if ($v === 0.0)      return '0';
        if ($v == floor($v) && abs($v) < 1e15) return (string) (int) $v;
        return rtrim(rtrim(number_format($v, max(1, $prec), '.', ''), '0'), '.');
    }
}


// ══════════════════════════════════════════════════════════════════════════════
//  SECTION 15 — PUBLIC API  (MathLibrary)
// ══════════════════════════════════════════════════════════════════════════════

final class MathLibrary
{
    // ── Public entry points ───────────────────────────────────────────────────

    /**
     * Universal entry point: auto-detects whether input is an expression or
     * equation and routes accordingly.
     *
     * @param  array<string, int|float> $vars      Known variable bindings.
     * @param  int                      $precision  0–20 decimal places.
     * @return array{steps: array, final_result: float|string|array}
     * @throws MathLibraryException on any error
     */
    public static function evaluate(
        string $expr,
        array  $vars      = [],
        int    $precision = MathLimits::PRECISION,
        ?EquationValidatorInterface $validator = null
    ): array {
        $expr = self::guardInput($expr, $vars, $precision);
        $validator ??= new SingleEquationValidator();

        $tokens = self::lex($expr);
        $validator->validate($tokens);

        $hasEq = false;
        foreach ($tokens as $t) {
            if ($t->type === MathToken::EQ) {
                $hasEq = true;
                break;
            }
        }

        return $hasEq
            ? self::handleEquation($expr, $tokens, $vars, $precision)
            : self::handleExpr($expr, $vars, $precision);
    }

    /**
     * Safe wrapper: catches all library exceptions and returns a status array.
     *
     * @return array{ok: bool, error?: string, steps?: array, final_result?: mixed}
     */
    public static function safeEvaluate(
        string $expr,
        array  $vars      = [],
        int    $precision = MathLimits::PRECISION
    ): array {
        try {
            return array_merge(['ok' => true], self::evaluate($expr, $vars, $precision));
        } catch (MathLibraryException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()];
        }
    }

    /**
     * Evaluate a pure expression (no '=' sign).
     *
     * @throws MathParseException   if '=' is present
     * @throws MathLibraryException on evaluation errors
     */
    public static function expression(
        string $expr,
        array  $vars      = [],
        int    $precision = MathLimits::PRECISION
    ): array {
        $expr   = trim($expr);
        $tokens = self::lex($expr);
        foreach ($tokens as $tok) {
            if ($tok->type === MathToken::EQ) {
                throw new MathParseException(
                    "expression() does not accept equations (found '=' at pos {$tok->pos})."
                );
            }
        }
        return self::handleExpr($expr, $vars, $precision);
    }

    /**
     * Solve an equation (requires exactly one '=' sign).
     *
     * @throws MathParseException if no '=' is present
     * @throws MathLibraryException on solver errors
     */
    public static function equation(
        string $expr,
        array  $vars      = [],
        int    $precision = MathLimits::PRECISION
    ): array {
        $expr   = trim($expr);
        $tokens = self::lex($expr);
        $hasEq  = false;
        foreach ($tokens as $tok) {
            if ($tok->type === MathToken::EQ) {
                $hasEq = true;
                break;
            }
        }
        if (!$hasEq) {
            throw new MathParseException(
                "equation() requires '=' sign. None found in: \"{$expr}\"."
            );
        }
        return self::evaluate($expr, $vars, $precision);
    }

    /**
     * Inspect an expression/equation without evaluating it.
     *
     * @return array{type: string, variables_found: string[], unknowns: string[],
     *              has_sqrt: bool, has_radical: bool, has_pi: bool, has_power: bool,
     *              token_count: int, char_count: int, solvable: bool, solve_target: ?string}
     */
    public static function detect(string $expr, array $vars = []): array
    {
        $expr       = trim($expr);
        $tokens     = self::lex($expr);
        $type       = 'expression';
        $hasSqrt    = false;
        $hasRadical = false;
        $hasPi      = false;
        $hasPower   = false;
        $varNames   = [];

        foreach ($tokens as $tok) {
            match ($tok->type) {
                MathToken::EQ      => ($type = 'equation'),
                MathToken::SQRT    => ($hasSqrt = true),
                MathToken::RADICAL => ($hasRadical = true),
                MathToken::PI      => ($hasPi = true),
                MathToken::CARET   => ($hasPower = true),
                MathToken::VAR     => (RegexCache::isValidIdentifier((string) $tok->value)
                    ? ($varNames[] = $tok->value) : null),
                default            => null,
            };
        }

        $varNames = array_values(array_unique($varNames));
        $unknowns = array_values(array_diff($varNames, array_keys($vars)));
        $solvable = ($type === 'equation' && count($unknowns) === 1);

        return [
            'type'            => $type,
            'variables_found' => $varNames,
            'unknowns'        => $unknowns,
            'has_sqrt'        => $hasSqrt,
            'has_radical'     => $hasRadical,
            'has_pi'          => $hasPi,
            'has_power'       => $hasPower,
            'token_count'     => count($tokens) - 1,
            'char_count'      => strlen($expr),
            'solvable'        => $solvable,
            'solve_target'    => $solvable ? $unknowns[0] : null,
        ];
    }

    // ── Internal: input guards ────────────────────────────────────────────────

    private static function guardInput(string $expr, array $vars, int $precision): string
    {
        $expr = trim($expr);
        if ($expr === '') {
            throw new MathParseException('Expression must not be empty.');
        }
        if (strlen($expr) > MathLimits::MAX_INPUT) {
            throw new MathParseException('Input too long (max ' . MathLimits::MAX_INPUT . ' chars).');
        }
        if ($precision < 0 || $precision > 20) {
            throw new \InvalidArgumentException('Precision must be 0–20.');
        }
        if (count($vars) > MathLimits::MAX_VARS) {
            throw new \InvalidArgumentException(
                'Too many variable bindings (max ' . MathLimits::MAX_VARS . ').'
            );
        }

        foreach ($vars as $name => $value) {
            self::assertValidVarName((string) $name);
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException("Value of '{$name}' must be numeric.");
            }
            $fv = (float) $value;
            if (is_nan($fv) || is_infinite($fv)) {
                throw new \InvalidArgumentException("Value of '{$name}' must be finite.");
            }
        }

        return $expr;
    }

    /** @return MathToken[] */
    private static function lex(string $expr): array
    {
        try {
            return (new MathLexer($expr))->tokenize();
        } catch (MathParseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MathParseException($e->getMessage(), 0, $e);
        }
    }

    // ── Internal: expression handler ──────────────────────────────────────────

    private static function handleExpr(string $expr, array $vars, int $prec): array
    {
        $ast = ASTRegistry::getOrBuild($expr);
        $ev  = new MathEvaluator($vars, $expr, $prec);

        try {
            $res = $ev->evaluate($ast);
        } catch (MathLibraryException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MathEvaluationException($e->getMessage(), 0, $e);
        }

        return ['steps' => $ev->getSteps(), 'final_result' => $res];
    }

    // ── Internal: equation handler ────────────────────────────────────────────

    /**
     * @param MathToken[] $tokens
     */
    private static function handleEquation(
        string $equation,
        array  $tokens,
        array  $vars,
        int    $prec
    ): array {
        $eqTok = null;
        foreach ($tokens as $t) {
            if ($t->type === MathToken::EQ) {
                $eqTok = $t;
                break;
            }
        }

        $p      = $eqTok->pos;
        $lhsStr = trim(substr($equation, 0, $p));
        $rhsStr = trim(substr($equation, $p + 1));

        if ($lhsStr === '' || $rhsStr === '') {
            throw new MathParseException("Both sides of '=' must be non-empty.");
        }

        $lhsAst   = ASTRegistry::getOrBuild($lhsStr);
        $rhsAst   = ASTRegistry::getOrBuild($rhsStr);
        $allVars  = array_unique(array_merge(
            VarCollector::collect($lhsAst),
            VarCollector::collect($rhsAst)
        ));
        $unknowns = array_values(array_diff($allVars, array_keys($vars)));

        if (count($unknowns) !== 1) {
            throw new MathSolverException(
                count($unknowns) === 0
                    ? "Equation has no unknown variable — all variables have values."
                    : 'Equation has ' . count($unknowns) . ' unknowns ('
                    . implode(', ', $unknowns)
                    . '). Provide values for all but one.'
            );
        }

        return self::solveLinear(
            $lhsStr,
            $lhsAst,
            $rhsStr,
            $rhsAst,
            $unknowns[0],
            $vars,
            $equation,
            $prec
        );
    }

    // ── Solver ────────────────────────────────────────────────────────────────

    /**
     * Linear equation solver with step recording.
     *
     * Strategy:
     *   1. Show the original equation.
     *   2. Evaluate LHS and RHS at the unknown = 0 and = 1 (with steps) to
     *      derive the linear coefficients: f(x) ≈ a·x + b.
     *   3. Test linearity silently at four additional probe points.
     *   4. Handle degenerate cases: 0x = 0 (infinite solutions), 0x = c (no solution).
     *   5. Solve a·x + b = 0 → x = −b/a.
     *   6. Verify by substitution.
     *
     * All is_finite() guards are applied to coefficients before use.
     */
    private static function solveLinear(
        string   $lhs,
        MathNode $lhsAst,
        string   $rhs,
        MathNode $rhsAst,
        string   $unk,
        array    $vars,
        string   $eq,
        int      $prec
    ): array {
        $fmt   = static fn(float $v): string => MathEvaluator::fmt($v, $prec);
        $steps = [];
        $n     = 0;

        $mkStep = static function (
            string       $operation,
            StepText     $text,
            float|string $res
        ) use (&$steps, &$n, $eq): void {
            $steps[] = [
                'step_number'    => ++$n,
                'description_en' => $text->en,
                'description_fa' => $text->fa,
                'formula'        => $text->formula,
                'calculation'    => $text->calculation,
                'result'         => $res,
                'metadata'       => [
                    'start_index'     => 0,
                    'end_index'       => strlen($eq) - 1,
                    'expression_part' => $eq,
                    'char_count'      => strlen($eq),
                    'node_type'       => 'SolverStep',
                    'operation'       => $operation,
                    'is_symbolic'     => !is_numeric($res),
                    'result_display'  => is_numeric($res)
                        ? MathEvaluator::fmt((float) $res)
                        : (string) $res,
                ],
            ];
        };

        // Silent probe: evaluates h(x) = LHS − RHS without emitting any steps.
        $hExpr = "({$lhs}) - ({$rhs})";
        $hAst  = ASTRegistry::getOrBuild($hExpr);

        $evalHSilent = static function (float $x) use (
            $hAst,
            $hExpr,
            $unk,
            $vars,
            $prec
        ): float {
            try {
                $ev = new MathEvaluator(array_merge($vars, [$unk => $x]), $hExpr, $prec, 0);
                $r  = $ev->evaluate($hAst, clearCache: true);
                return is_numeric($r) ? (float) $r : 0.0;
            } catch (\Throwable) {
                return 0.0;
            }
        };

        // ── STEP 1: Show the original equation ───────────────────────────────
        $mkStep('equation_solving', StepExplainer::solverStart($eq, $unk), 'equation');

        // ── STEP 2: Evaluate at x=0 and x=1 to derive linear coefficients ───
        $lhsEv0   = new MathEvaluator(array_merge($vars, [$unk => 0.0]), $lhs, $prec, 0);  // ← شمارنده‌ی داخلی صفر
        $lhsConst = (float) $lhsEv0->evaluate($lhsAst, clearCache: true);

        $rhsEv0   = new MathEvaluator(array_merge($vars, [$unk => 0.0]), $rhs, $prec, 0);
        $rhsConst = (float) $rhsEv0->evaluate($rhsAst, clearCache: true);

        $lhsEv1   = new MathEvaluator(array_merge($vars, [$unk => 1.0]), $lhs, $prec, 0);
        $lhsCoeff = (float) $lhsEv1->evaluate($lhsAst, clearCache: true) - $lhsConst;

        $rhsEv1   = new MathEvaluator(array_merge($vars, [$unk => 1.0]), $rhs, $prec, 0);
        $rhsCoeff = (float) $rhsEv1->evaluate($rhsAst, clearCache: true) - $rhsConst;

        // Finite guards on all derived coefficients
        foreach (
            [
                'lhsConst' => $lhsConst,
                'rhsConst' => $rhsConst,
                'lhsCoeff' => $lhsCoeff,
                'rhsCoeff' => $rhsCoeff,
            ] as $label => $val
        ) {
            if (!is_finite($val)) {
                throw new MathSolverException(
                    "Cannot solve: evaluating '{$label}' yields "
                        . (is_nan($val) ? 'NaN (undefined result)' : 'infinity (overflow)')
                        . ". The equation has a singularity or overflow at the probe points for '{$unk}'."
                );
            }
        }

        // Linear coefficients: f(x) = a·x + b where a = net coefficient, b = net constant
        $a = $lhsCoeff - $rhsCoeff;
        $b = $lhsConst - $rhsConst;

        if (!is_finite($a)) {
            throw new MathSolverException(
                "Cannot solve: the net coefficient of '{$unk}' is "
                    . (is_nan($a) ? 'NaN (INF − INF)' : 'infinite')
                    . ". The equation may have equal infinities on both sides."
            );
        }

        $linearityEps = max(1e-6, 10 ** (-$prec));
        $equationFmt  = "{$fmt($lhsCoeff)}·{$unk} + {$fmt($lhsConst)} = {$fmt($rhsCoeff)}·{$unk} + {$fmt($rhsConst)}";
        $mkStep(
            'equation_solving',
            StepExplainer::solverSimplify(
                $unk,
                $fmt($lhsConst),
                $fmt($lhsCoeff),
                $fmt($rhsConst),
                $fmt($rhsCoeff),
                $equationFmt
            ),
            $equationFmt
        );

        // ── Linearity detection (silent probes) ───────────────────────────────
        $linear     = true;
        $deviations = [];
        foreach ([2.0, 3.0, -1.0, 0.5] as $probe) {
            $hProbe   = $evalHSilent($probe);
            $expected = $a * $probe + $b;
            $dev      = abs($hProbe - $expected);
            if ($dev > $linearityEps) {
                $linear       = false;
                $deviations[] = "h({$probe}) = {$fmt($hProbe)}, linear predicts {$fmt($expected)}, Δ = {$fmt($dev)}";
            }
        }

        // ── STEP 3: Collect variable terms left, constants right ──────────────
        $netCoeff   = $lhsCoeff - $rhsCoeff;
        $netConst   = $lhsConst - $rhsConst;
        $netRhsFmt  = $fmt(-$netConst);
        $collectFmt = "{$fmt($netCoeff)}·{$unk} = {$netRhsFmt}";
        $mkStep(
            'equation_solving',
            StepExplainer::solverCollect(
                $unk,
                $fmt($lhsCoeff),
                $fmt($rhsCoeff),
                $fmt($netCoeff),
                $fmt($lhsConst),
                $fmt($rhsConst),
                $netRhsFmt,
                $collectFmt
            ),
            $collectFmt
        );

        // ── Degenerate: coefficient is zero (0x = 0 or 0x = c) ───────────────
        if (abs($a) < $linearityEps) {
            $isId = abs($b) < $linearityEps;
            $val  = $isId ? 'identity' : 'no_solution';
            $mkStep(
                'equation_solving',
                StepExplainer::solverDegenerate($unk, $isId, $fmt($b)),
                $val
            );
            return ['steps' => $steps, 'final_result' => ['variable' => $unk, 'value' => $val]];
        }

        // ── STEP 4: Divide to isolate the unknown ─────────────────────────────
        $sol = -$b / $a;

        if (abs($netCoeff - 1.0) < $linearityEps) {
            $mkStep('equation_solving', StepExplainer::solverDivideIsolated($unk, $fmt($sol)), $sol);
        } else {
            $mkStep(
                'equation_solving',
                StepExplainer::solverDivide($unk, $fmt($netCoeff), $netRhsFmt, $fmt($sol)),
                $sol
            );
        }

        // Non-linear warning
        if (!$linear) {
            $devDetail = implode('; ', $deviations);
            $mkStep(
                'equation_solving',
                StepExplainer::solverNonLinear($unk, $fmt($sol), $devDetail),
                'approx'
            );
        }

        // ── STEP 5: Verify by substitution (silent evaluation) ─────────────────
        $verVars = array_merge($vars, [$unk => $sol]);

        $evL = new MathEvaluator($verVars, $lhs, $prec, 0);
        $lr  = $evL->evaluate($lhsAst, clearCache: true);

        $evR = new MathEvaluator($verVars, $rhs, $prec, 0);
        $rr  = $evR->evaluate($rhsAst, clearCache: true);

        $lv   = is_numeric($lr) ? (float) $lr : 0.0;
        $rv   = is_numeric($rr) ? (float) $rr : 0.0;
        $diff = abs($lv - $rv);
        $ok   = $diff < $linearityEps;

        $mkStep(
            'equation_solving',
            StepExplainer::solverVerify(
                $unk,
                $fmt($sol),
                $eq,
                $fmt($lv),
                $fmt($rv),
                $fmt($diff),
                $ok
            ),
            $ok ? 'verified' : 'approximate'
        );

        return ['steps' => $steps, 'final_result' => ['variable' => $unk, 'value' => $sol]];
    }

    // ── Shared validation helpers ─────────────────────────────────────────────

    private static function isValidVarName(string $name): bool
    {
        return RegexCache::isValidIdentifier($name);
    }

    private static function assertValidVarName(string $name): void
    {
        if (!self::isValidVarName($name)) {
            throw new \InvalidArgumentException(
                "Invalid variable name: '{$name}'. "
                    . "Names must start with a letter, contain only letters/digits/underscores, "
                    . "be at most 64 characters, and the lone underscore '_' is reserved."
            );
        }
    }
}
