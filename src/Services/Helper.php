<?php

namespace MuckiLogPlugin\Services;

class Helper
{
    public function getHashData(array|string $data): string
    {
        if(is_array($data)) {
            return md5(serialize($data));
        }
        return md5($data);
    }

    public function isValidEmail(string $email): bool
    {
        // assume it's valid if we can't validate it
        if (!function_exists('filter_var')) {
            return true;
        }

        return false !== filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

