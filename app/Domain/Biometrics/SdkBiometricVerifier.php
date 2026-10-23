<?php

namespace App\Domain\Biometrics;

use RuntimeException;

class SdkBiometricVerifier implements BiometricVerifier
{
    public function verify(string $captureToken, string $terminalIdentifier): array
    {
        throw new RuntimeException(
            'El adaptador DigitalPersona todavía no está configurado. Conecte aquí el servicio local del lector.',
        );
    }
}
