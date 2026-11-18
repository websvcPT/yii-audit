<?php

namespace websvc\audit\components;

/**
 * Masks sensitive request parameters and encodes them within a size limit.
 */
final class ParamsMasker
{
    public const MASK = '***';

    /**
     * Deepest array nesting kept. MySQL JSON columns reject documents nested deeper than 100 levels.
     */
    public const MAX_DEPTH = 32;

    public const DEPTH_MARKER = '[depth limit]';

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /**
     * @var string[]
     */
    private array $maskKeys;

    /**
     * @param string[] $maskKeys keys whose values are masked (case-insensitive substring match)
     * @param int $maxBytes encoded payloads larger than this are replaced by a truncation marker
     */
    public function __construct(array $maskKeys, private int $maxBytes)
    {
        $this->maskKeys = array_values(array_filter(array_map('strtolower', $maskKeys), 'strlen'));
    }

    /**
     * Masks matching keys at any depth. A matching key masks its whole value. Arrays nested deeper than
     * MAX_DEPTH are replaced by DEPTH_MARKER.
     */
    public function mask(array $params): array
    {
        return $this->maskLevel($params, 1);
    }

    private function maskLevel(array $params, int $depth): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            if ($this->isMasked((string) $key)) {
                $result[$key] = self::MASK;
            } elseif (is_array($value)) {
                $result[$key] = $depth >= self::MAX_DEPTH ? self::DEPTH_MARKER : $this->maskLevel($value, $depth + 1);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Masks the values of `--name=value` and `-name=value` console options whose name matches.
     */
    public function maskArgs(array $args): array
    {
        $result = [];
        foreach ($args as $key => $arg) {
            if (is_string($arg) && preg_match('/^(--?)([^=]+)=/s', $arg, $matches) && $this->isMasked($matches[2])) {
                $arg = $matches[1] . $matches[2] . '=' . self::MASK;
            }
            $result[$key] = $arg;
        }

        return $result;
    }

    /**
     * Encodes the parameters as JSON, or a truncation marker when the JSON is larger than the limit.
     */
    public function encode(array $params): string
    {
        $json = json_encode($params, self::JSON_FLAGS);
        if ($json === false) {
            $json = '{}';
        }
        if (strlen($json) <= $this->maxBytes) {
            return $json;
        }

        $marker = json_encode(
            ['_truncated' => true, '_bytes' => strlen($json), '_keys' => array_slice(array_keys($params), 0, 100)],
            self::JSON_FLAGS
        );

        return $marker === false ? '{"_truncated":true}' : $marker;
    }

    /**
     * Describes uploaded files (`$_FILES` shape) by field, name, size and error code only.
     *
     * @return array<int, array{field: string, name: string, size: int, error: int}>
     */
    public static function describeFiles(array $files): array
    {
        $result = [];
        foreach ($files as $field => $file) {
            if (!is_array($file) || !isset($file['name'])) {
                continue;
            }
            self::collectFiles(
                (string) $field,
                $file['name'],
                $file['size'] ?? [],
                $file['error'] ?? [],
                $result
            );
        }

        return $result;
    }

    private static function collectFiles(string $field, mixed $name, mixed $size, mixed $error, array &$result): void
    {
        if (!is_array($name)) {
            $result[] = [
                'field' => $field,
                'name' => (string) $name,
                'size' => (int) (is_array($size) ? 0 : $size),
                'error' => (int) (is_array($error) ? 0 : $error),
            ];

            return;
        }

        foreach ($name as $key => $childName) {
            self::collectFiles(
                $field . '[' . $key . ']',
                $childName,
                is_array($size) ? ($size[$key] ?? 0) : 0,
                is_array($error) ? ($error[$key] ?? 0) : 0,
                $result
            );
        }
    }

    private function isMasked(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->maskKeys as $maskKey) {
            if (str_contains($key, $maskKey)) {
                return true;
            }
        }

        return false;
    }
}
