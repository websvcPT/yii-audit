<?php

namespace websvc\audit\components;

use DateTimeInterface;
use Stringable;
use websvc\audit\Audit;

/**
 * Builds the per-attribute old and new values stored for one model change.
 */
final class ChangeBuilder
{
    public const MASK = '***';

    /**
     * @var array<string, true>
     */
    private array $ignore;

    /**
     * @var array<string, true>
     */
    private array $mask;

    /**
     * @param string[] $ignoreAttributes attributes left out of the result
     * @param string[] $maskAttributes attributes whose values are replaced by the mask
     * @param int $maxValueBytes longer values are cut and marked; 0 keeps every value whole
     */
    public function __construct(
        array $ignoreAttributes = [],
        array $maskAttributes = [],
        private int $maxValueBytes = 0
    ) {
        $this->ignore = array_fill_keys($ignoreAttributes, true);
        $this->mask = array_fill_keys($maskAttributes, true);
    }

    /**
     * Callers pass: insert `([], $attributes)`, update `($changedAttributes, $currentValues)`, delete
     * `($attributes, [])`.
     *
     * @return array<string, array{old: mixed, new: mixed}>|null null when nothing is left to record
     */
    public function build(string $action, array $oldValues, array $newValues): ?array
    {
        $names = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        $changes = [];

        foreach ($names as $name) {
            if (isset($this->ignore[$name])) {
                continue;
            }

            $old = $this->normalize($oldValues[$name] ?? null);
            $new = $this->normalize($newValues[$name] ?? null);

            if ($action === Audit::ACTION_INSERT) {
                if ($new === null) {
                    continue;
                }
                $old = null;
            } elseif ($action === Audit::ACTION_DELETE) {
                if ($old === null) {
                    continue;
                }
                $new = null;
            } elseif ($this->isEqual($old, $new)) {
                continue;
            }

            if (isset($this->mask[$name])) {
                $old = $old === null ? null : self::MASK;
                $new = $new === null ? null : self::MASK;
            }

            // Cut after comparing, so a change beyond the limit is still detected
            $changes[$name] = ['old' => $this->truncate($old), 'new' => $this->truncate($new)];
        }

        return $changes === [] ? null : $changes;
    }

    /**
     * Turns a value into something JSON can always encode.
     */
    private function normalize(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return is_finite($value) ? $value : (string) $value;
        }
        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8') ? $value : '[binary ' . strlen($value) . ' bytes]';
        }
        if (is_array($value)) {
            return array_map(fn(mixed $item): mixed => $this->normalize($item), $value);
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof Stringable) {
            return $this->normalize((string) $value);
        }

        return '[' . (is_object($value) ? 'object ' : '') . get_debug_type($value) . ']';
    }

    /**
     * Cuts strings, and arrays encoded as JSON, that are longer than the limit.
     */
    private function truncate(mixed $value): mixed
    {
        if ($this->maxValueBytes <= 0) {
            return $value;
        }
        if (is_array($value)) {
            $json = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($json) <= $this->maxValueBytes) {
                return $value;
            }
            $value = $json;
        }
        if (!is_string($value) || strlen($value) <= $this->maxValueBytes) {
            return $value;
        }

        return mb_strcut($value, 0, $this->maxValueBytes, 'UTF-8') . ' [truncated, ' . strlen($value) . ' bytes]';
    }

    /**
     * Values are equal when both are null, or both are strings with the same content, or, when at least one is not a
     * string, they have the same string form or are the same number. `1` equals `"1"`; `"0123"` does not equal
     * `"123"`; `null` does not equal `""`.
     */
    private function isEqual(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }
        if (is_string($a) && is_string($b)) {
            // Codes, postal codes and phone numbers look numeric but are compared as text
            return $a === $b;
        }

        $a = is_bool($a) ? (int) $a : $a;
        $b = is_bool($b) ? (int) $b : $b;

        if (is_numeric($a) && is_numeric($b)) {
            $intA = $this->integerString($a);
            $intB = $this->integerString($b);
            if ($intA !== null && $intB !== null) {
                // Compared as strings so large integers keep their precision
                return $intA === $intB;
            }

            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    private function integerString(int|float|string $value): ?string
    {
        if (!preg_match('/^([+-]?)0*(\d+)$/', (string) $value, $matches)) {
            return null;
        }

        $sign = $matches[1] === '-' && $matches[2] !== '0' ? '-' : '';

        return $sign . $matches[2];
    }
}
