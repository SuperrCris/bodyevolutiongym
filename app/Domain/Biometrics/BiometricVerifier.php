<?php

namespace App\Domain\Biometrics;

interface BiometricVerifier
{
    /**
     * @return array{matched: bool, customer_id?: int, external_id?: string}
     */
    public function verify(string $captureToken, string $terminalIdentifier): array;
}
