<?php

namespace App\Support;

class SalesmanOtpGenerator
{
    public function generate(string $type, string $accessKey, ?string $customerCode = null): string
    {
        $passkey = preg_replace('/\D+/', '', $accessKey) ?? '';

        if (in_array($type, ['5', '6'], true)) {
            $passkey = $this->decodeFinanceKey($passkey);
        }

        return $this->toDialPadDigits($this->toAlphabetKey($passkey));
    }

    private function decodeFinanceKey(string $value): string
    {
        $decoded = '';

        foreach (str_split($value, 2) as $pair) {
            if ($pair === '') {
                continue;
            }

            $decoded .= (string) (100 - (int) $pair);
        }

        return strrev($decoded);
    }

    private function toAlphabetKey(string $value): string
    {
        $value = trim($value);
        $numericString = $value === '' ? '0' : $value;

        if (strlen($numericString) < 8) {
            $numericString .= '123';
        }

        $numeric = (int) $numericString;

        if ($numeric <= 25) {
            return chr($numeric + 97);
        }

        $dividend = $numeric + 1;
        $result = '';

        while ($dividend > 0) {
            $modulo = fmod($dividend - 1, 26);
            $result = chr(((int) $modulo) + 97) . $result;
            $dividend = floor(($dividend - $modulo) / 26);
        }

        return $result;
    }

    private function toDialPadDigits(string $value): string
    {
        return strtr(strtolower($value), [
            'a' => '2', 'b' => '2', 'c' => '2',
            'd' => '3', 'e' => '3', 'f' => '3',
            'g' => '4', 'h' => '4', 'i' => '4',
            'j' => '5', 'k' => '5', 'l' => '5',
            'm' => '6', 'n' => '6', 'o' => '6',
            'p' => '7', 'q' => '7', 'r' => '7', 's' => '7',
            't' => '8', 'u' => '8', 'v' => '8',
            'w' => '9', 'x' => '9', 'y' => '9', 'z' => '9',
        ]);
    }
}
