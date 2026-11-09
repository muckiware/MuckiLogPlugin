<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;

final class SensitiveFieldMasker
{
    public const MASK = '***';

    private const FRAGMENTS = [
        'password', 'secret', 'token', 'accesskey', 'apikey', 'privatekey', 'hash',
        'credential', 'passphrase', 'hmac',
    ];

    /**
     * Sensitive: PasswordField, a known secret fragment, or a name ending in "key"
     * (portalKey, licenseKey, ...). Accepted false positives: e.g. "sortKey".
     */
    public function isSensitive(string $fieldName, ?Field $definitionField = null): bool
    {
        if ($definitionField instanceof PasswordField) {
            return true;
        }

        $normalized = mb_strtolower(str_replace('_', '', $fieldName));
        if (str_ends_with($normalized, 'key')) {
            return true;
        }
        foreach (self::FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replaces the values of sensitive keys at any depth with the mask; sets $masked when it did.
     */
    public function maskValue(mixed $value, bool &$masked): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (\is_string($key) && $this->isSensitive($key)) {
                $value[$key] = self::MASK;
                $masked = true;

                continue;
            }
            $value[$key] = $this->maskValue($item, $masked);
        }

        return $value;
    }
}
