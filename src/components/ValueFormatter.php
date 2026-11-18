<?php

namespace websvc\audit\components;

/**
 * Turns recorded values into short text for the audit pages.
 */
final class ValueFormatter
{
    /**
     * @return string|null null for null, so views can show their own "not set" text
     */
    public static function format(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    /**
     * Flattens nested parameters into dotted keys (`post.Invoice.status`). Empty groups are left out.
     *
     * @return array<string, string|null>
     */
    public static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flatten($value, $name);
            } else {
                $result[$name] = self::format($value);
            }
        }

        return $result;
    }
}
