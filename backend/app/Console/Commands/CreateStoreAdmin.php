<?php

namespace App\Console\Commands;

use App\Models\User;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateStoreAdmin extends Command
{
    protected $signature = 'store:create-admin';

    protected $description = 'Create an administrator with a password supplied privately at the prompt';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Administrator name'),
            'email' => mb_strtolower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password (at least 14 characters)'),
        ];
        $validation = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => [
                'required', 'string', Password::min(14)->mixedCase()->numbers()->symbols(),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('The password may not exceed 72 bytes.');
                    }
                },
            ],
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        (new User)->forceFill($data + ['role' => 'admin', 'email_verified_at' => now()])->save();
        $this->info('Administrator created. Sign in at /admin.');

        return self::SUCCESS;
    }
}
