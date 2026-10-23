<?php

namespace App\Http\Controllers\Api;

use App\Domain\Biometrics\BiometricVerifier;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricController extends Controller
{
    public function verify(Request $request, BiometricVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'capture_token' => ['required', 'string', 'max:2048'],
            'terminal_identifier' => ['required', 'string', 'max:100'],
        ]);

        return response()->json($verifier->verify(
            $data['capture_token'],
            $data['terminal_identifier'],
        ));
    }
}
