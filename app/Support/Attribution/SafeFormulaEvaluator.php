<?php

namespace App\Support\Attribution;

use InvalidArgumentException;

/**
 * #205 Custom Calculated KPI Formula Builder.
 * Strict tokenizer + shunting-yard RPN evaluator (zero eval(), whitelist variables, safe divide-by-zero).
 */
class SafeFormulaEvaluator
{
    public const ALLOWED_VARIABLES = [
        'spend',
        'revenue',
        'net_profit',
        'clicks',
        'impressions',
        'conversions',
        'leads',
    ];

    /**
     * @return array{valid: bool, error: ?string, variables: list<string>}
     */
    public static function validate(string $expression): array
    {
        try {
            $rpn = static::compile($expression);
            $vars = [];
            foreach ($rpn as $token) {
                if ($token['type'] === 'var' && ! in_array($token['value'], $vars, true)) {
                    $vars[] = $token['value'];
                }
            }

            return ['valid' => true, 'error' => null, 'variables' => $vars];
        } catch (InvalidArgumentException $e) {
            return ['valid' => false, 'error' => $e->getMessage(), 'variables' => []];
        }
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    public static function evaluate(string $expression, array $variables, float $onDivideByZero = 0.0): float
    {
        $rpn = static::compile($expression);
        $stack = [];

        foreach ($rpn as $token) {
            if ($token['type'] === 'num') {
                $stack[] = (float) $token['value'];

                continue;
            }

            if ($token['type'] === 'var') {
                $name = $token['value'];
                $stack[] = isset($variables[$name]) ? (float) $variables[$name] : 0.0;

                continue;
            }

            if ($token['type'] === 'uop') {
                if (count($stack) < 1) {
                    throw new InvalidArgumentException('Malformed unary expression.');
                }
                $a = array_pop($stack);
                $stack[] = $token['value'] === 'u-' ? -$a : $a;

                continue;
            }

            if ($token['type'] === 'op') {
                if (count($stack) < 2) {
                    throw new InvalidArgumentException('Malformed binary expression.');
                }
                $b = array_pop($stack);
                $a = array_pop($stack);

                $stack[] = match ($token['value']) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => abs($b) < 1e-12 ? $onDivideByZero : ($a / $b),
                    default => throw new InvalidArgumentException('Unsupported operator.'),
                };
            }
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Incomplete formula expression.');
        }

        $result = (float) $stack[0];

        return is_finite($result) ? round($result, 4) : $onDivideByZero;
    }

    public static function formatValue(float $value, string $format = 'decimal', string $currency = 'USD'): string
    {
        return match ($format) {
            'currency' => trim(number_format($value, 2).' '.$currency),
            'percent' => number_format($value, 2).'%',
            default => number_format($value, 2),
        };
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    protected static function compile(string $expression): array
    {
        $expr = trim($expression);
        if ($expr === '' || strlen($expr) > 255) {
            throw new InvalidArgumentException('Formula must be between 1 and 255 characters.');
        }

        if (preg_match('/[^a-z0-9_+\-*\/().\s]/', $expr)) {
            throw new InvalidArgumentException('Formula contains disallowed characters.');
        }

        $tokens = static::tokenize($expr);
        if ($tokens === []) {
            throw new InvalidArgumentException('Empty formula.');
        }

        $output = [];
        $ops = [];
        $precedence = [
            '+' => 1,
            '-' => 1,
            '*' => 2,
            '/' => 2,
            'u-' => 3,
            'u+' => 3,
        ];
        $rightAssociative = ['u-' => true, 'u+' => true];

        $prevType = null;
        foreach ($tokens as $tok) {
            $type = $tok['type'];
            $val = $tok['value'];

            if ($type === 'num' || $type === 'var') {
                if ($prevType === 'num' || $prevType === 'var' || $prevType === 'rparen') {
                    throw new InvalidArgumentException('Missing operator between operands.');
                }
                $output[] = $tok;
                $prevType = $type;

                continue;
            }

            if ($type === 'lparen') {
                if ($prevType === 'num' || $prevType === 'var' || $prevType === 'rparen') {
                    throw new InvalidArgumentException('Function calls and implicit multiplication are not allowed.');
                }
                $ops[] = $tok;
                $prevType = 'lparen';

                continue;
            }

            if ($type === 'rparen') {
                if ($prevType === null || $prevType === 'op' || $prevType === 'uop' || $prevType === 'lparen') {
                    throw new InvalidArgumentException('Empty or misplaced closing parenthesis.');
                }
                $matched = false;
                while ($ops !== []) {
                    $top = array_pop($ops);
                    if ($top['type'] === 'lparen') {
                        $matched = true;
                        break;
                    }
                    $output[] = $top;
                }
                if (! $matched) {
                    throw new InvalidArgumentException('Mismatched parentheses.');
                }
                $prevType = 'rparen';

                continue;
            }

            if ($type === 'op') {
                $isUnary = $prevType === null || $prevType === 'op' || $prevType === 'uop' || $prevType === 'lparen';
                if ($isUnary) {
                    if ($val !== '-' && $val !== '+') {
                        throw new InvalidArgumentException('Unexpected binary operator.');
                    }
                    $uTok = ['type' => 'uop', 'value' => 'u'.$val];
                    while ($ops !== []) {
                        $top = end($ops);
                        if ($top['type'] === 'lparen') {
                            break;
                        }
                        $topPrec = $precedence[$top['value']] ?? 0;
                        $curPrec = $precedence[$uTok['value']];
                        if ($topPrec > $curPrec || ($topPrec === $curPrec && empty($rightAssociative[$uTok['value']]))) {
                            $output[] = array_pop($ops);
                        } else {
                            break;
                        }
                    }
                    $ops[] = $uTok;
                    $prevType = 'uop';

                    continue;
                }

                while ($ops !== []) {
                    $top = end($ops);
                    if ($top['type'] === 'lparen') {
                        break;
                    }
                    $topPrec = $precedence[$top['value']] ?? 0;
                    $curPrec = $precedence[$val];
                    if ($topPrec >= $curPrec) {
                        $output[] = array_pop($ops);
                    } else {
                        break;
                    }
                }
                $ops[] = $tok;
                $prevType = 'op';
            }
        }

        if ($prevType === 'op' || $prevType === 'uop' || $prevType === 'lparen') {
            throw new InvalidArgumentException('Formula cannot end with an operator or open parenthesis.');
        }

        while ($ops !== []) {
            $top = array_pop($ops);
            if ($top['type'] === 'lparen') {
                throw new InvalidArgumentException('Unclosed parenthesis in formula.');
            }
            $output[] = $top;
        }

        return $output;
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    protected static function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            $ch = $expr[$i];
            if (ctype_space($ch)) {
                $i++;

                continue;
            }

            if (ctype_digit($ch) || $ch === '.') {
                $start = $i;
                $dots = 0;
                while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    if ($expr[$i] === '.') {
                        $dots++;
                    }
                    $i++;
                }
                $numStr = substr($expr, $start, $i - $start);
                if ($dots > 1 || $numStr === '.') {
                    throw new InvalidArgumentException("Invalid numeric literal: {$numStr}");
                }
                $tokens[] = ['type' => 'num', 'value' => $numStr];

                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($expr[$i]) || $expr[$i] === '_')) {
                    $i++;
                }
                $ident = substr($expr, $start, $i - $start);
                if (! in_array($ident, static::ALLOWED_VARIABLES, true)) {
                    throw new InvalidArgumentException("Disallowed variable or identifier: {$ident}");
                }
                $tokens[] = ['type' => 'var', 'value' => $ident];

                continue;
            }

            if ($ch === '(') {
                $tokens[] = ['type' => 'lparen', 'value' => '('];
                $i++;

                continue;
            }

            if ($ch === ')') {
                $tokens[] = ['type' => 'rparen', 'value' => ')'];
                $i++;

                continue;
            }

            if (in_array($ch, ['+', '-', '*', '/'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $ch];
                $i++;

                continue;
            }

            throw new InvalidArgumentException("Unexpected token: {$ch}");
        }

        return $tokens;
    }
}
