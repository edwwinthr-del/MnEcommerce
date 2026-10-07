<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Validation\Rules\Password;

trait ValidatesNewPassword
{
    protected function newPasswordRules(): array
    {
        return [
            'required', 'string', 'confirmed', Password::min(12),
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('The password may not exceed 72 bytes.');
                }
            },
        ];
    }
}
