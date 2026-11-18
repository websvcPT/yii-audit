<?php

namespace websvc\audit\models;

/**
 * Decodes JSON column values, which arrive as arrays or strings depending on how the row was loaded.
 */
trait JsonValueTrait
{
    protected static function decodeJsonValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
