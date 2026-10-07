<?php

namespace App\Http\Requests\Auth;

class ResetPasswordRequest extends ForgotPasswordRequest
{
    use ValidatesNewPassword;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'token' => ['required', 'string', 'max:256'],
            'password' => $this->newPasswordRules(),
        ];
    }
}
