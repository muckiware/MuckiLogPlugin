<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;

final class SensitiveFieldMasker
{
    public const MASK = '***';

    private const FRAGMENTS = ['password', 'secret', 'token', 'accesskey', 'apikey', 'privatekey', 'hash'];

    public function isSensitive(string $fieldName, ?Field $definitionField = null): bool
    {
        if ($definitionField instanceof PasswordField) {
            return true;
        }

        $normalized = mb_strtolower(str_replace('_', '', $fieldName));
        foreach (self::FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
