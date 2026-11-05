<?php

declare(strict_types=1);

namespace app\core\dbal;

use app\core\models\Messages;
use Throwable;

final class SafeMysqlDiagnostic
{
    /**
     * Reports IN (...) clauses that receive values bound with different mysqli types.
     *
     * This is diagnostic only: queries continue to execute unchanged.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     */
    public static function reportMixedInParameterTypes(string $sql, array $params): void
    {
        try {
            foreach (self::findMixedInParameterTypes($sql, $params) as $group) {
                self::sendMixedInParameterTypesMessage(
                    $sql,
                    $group['fragment'],
                    $group['placeholders'],
                    $group['values'],
                    $group['types']
                );
            }
        } catch (Throwable $e) {
            error_log('[SafeMysql] Mixed IN parameter types diagnostic failed: ' . $e->getMessage());
        }
    }

    /**
     * Finds IN (...) clauses that receive values bound with different mysqli types.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<int, array{fragment: string, placeholders: array<int, string>, values: array<int, mixed>, types: array<string, int>}>
     */
    public static function findMixedInParameterTypes(string $sql, array $params): array
    {
        $result = [];

        foreach (self::collectInParameterGroups($sql, $params) as $group) {
            $types = self::countDiagnosticTypes($group['values']);

            if (count($types) <= 1) {
                continue;
            }

            $group['types'] = $types;
            $result[] = $group;
        }

        return $result;
    }

    /**
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<int, array{fragment: string, placeholders: array<int, string>, values: array<int, mixed>}>
     */
    private static function collectInParameterGroups(string $sql, array $params): array
    {
        if (!preg_match_all(
            '/\b(?:NOT\s+)?IN\s*\(([^()]*)\)/i',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $groups = [];

        foreach ($matches as $match) {
            // IN (SELECT ...) is a subquery, not a value list: its placeholders belong to separate comparisons.
            // Value lists nested inside the subquery have no inner parentheses and are matched on their own.
            if (preg_match('/^\s*SELECT\b/i', $match[1]))
                continue;

            if (!preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $match[1], $placeholderMatches)) {
                continue;
            }

            $placeholders = array_values(array_unique(array_map(
                static function (string $placeholder): string {
                    return substr($placeholder, 1);
                },
                $placeholderMatches[0]
            )));
            $values = [];

            foreach ($placeholders as $placeholder) {
                if (!array_key_exists($placeholder, $params)) {
                    continue;
                }

                $value = $params[$placeholder];

                if (is_array($value)) {
                    foreach (array_values($value) as $item) {
                        $values[] = $item;
                    }
                } else {
                    $values[] = $value;
                }
            }

            if (count($values) > 1) {
                $groups[] = [
                    'fragment' => $match[0],
                    'placeholders' => $placeholders,
                    'values' => $values,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<string, int>
     */
    private static function countDiagnosticTypes(array $values): array
    {
        $types = [];

        foreach ($values as $value) {
            $type = self::getDiagnosticType($value);

            if ($type === null) {
                continue;
            }

            if (!isset($types[$type])) {
                $types[$type] = 0;
            }

            $types[$type]++;
        }

        ksort($types);

        return $types;
    }

    /**
     * Returns a type name close to the mysqli bind type used by SafeMysql.
     *
     * @param mixed $value
     */
    private static function getDiagnosticType($value): ?string
    {
        if (is_int($value) || is_bool($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            return 'string';
        }

        if ($value === null) {
            return 'null';
        }

        return null;
    }

    /**
     * @param string $sql
     * @param string $fragment
     * @param array<int, string> $placeholders
     * @param array<int, mixed> $values
     * @param array<string, int> $types
     */
    private static function sendMixedInParameterTypesMessage(
        string $sql,
        string $fragment,
        array $placeholders,
        array $values,
        array $types
    ): void {
        $messageData = [
            'types' => $types,
            'placeholders' => $placeholders,
            'values_preview' => self::previewDiagnosticValues($values),
            'in_fragment' => self::limitDiagnosticString($fragment, 1000),
            'sql' => self::limitDiagnosticString($sql, 3000),
            'call_site' => self::getDiagnosticCallSite(),
        ];

        $message = json_encode($messageData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($message)) {
            $message = '[SafeMysql] Failed to encode mixed IN parameter types diagnostic message.';
        }

        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        try {
            Messages::addDebugInfo(
                'SafeMysql mixed IN parameter types',
                $message,
                Messages::GROUP_WAREHOUSE,
                Messages::TEST_PARAMETERS,
                true
            );
        } catch (Throwable $e) {
            error_log('[SafeMysql] Mixed IN parameter types diagnostic failed: ' . $e->getMessage());
            error_log('[SafeMysql] Mixed IN parameter types: ' . $message);
        }
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    private static function previewDiagnosticValues(array $values): array
    {
        $preview = [];

        foreach (array_slice($values, 0, 20) as $value) {
            if (is_string($value)) {
                $preview[] = self::limitDiagnosticString($value, 200);
                continue;
            }

            if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $preview[] = $value;
                continue;
            }

            $preview[] = gettype($value);
        }

        return $preview;
    }

    private static function limitDiagnosticString(string $value, int $limit): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . '...';
    }

    private static function getDiagnosticCallSite(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? 0;

            if ($file === '') {
                continue;
            }

            if (in_array(basename($file), ['SafeMysqlDiagnostic.php', 'SafeMysql.php'], true)) {
                continue;
            }

            return sprintf('%s:%d', $file, $line);
        }

        return 'unknown';
    }
}
