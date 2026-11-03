<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

final class ValueNormalizer
{
    public function normalize(mixed $value): mixed
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
            $value = $value === false ? null : $value;
        }

        return match (true) {
            $value === null, \is_bool($value), \is_int($value) => $value,
            \is_float($value) => is_finite($value) ? $value : (string) $value,
            \is_string($value) => $this->normalizeString($value),
            \is_array($value) => array_map($this->normalize(...), $value),
            $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
            $value instanceof \JsonSerializable => $this->normalize($value->jsonSerialize()),
            $value instanceof \Stringable => (string) $value,
            \is_object($value) => $value::class,
            default => null,
        };
    }

    private function normalizeString(string $value): mixed
    {
        if (!mb_check_encoding($value, 'UTF-8')
            || (\strlen($value) === 16 && preg_match('/[^\x20-\x7E]/', $value) === 1)) {
            return bin2hex($value);
        }

        if ($value === '' || ($value[0] !== '{' && $value[0] !== '[')) {
            return $value;
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }

        return \is_array($decoded) ? $this->normalize($decoded) : $value;
    }
}
