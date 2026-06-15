<?php

namespace Nbutl\NovaAdmin\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function loginField(): string
    {
        return config('nova-admin.admin.login_field', 'name');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make($this->loginField())
            ->label('账号')
            ->required()
            ->validationMessages([
                'required' => '请输入账号。',
            ])
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->validationMessages([
                'required' => '请输入密码。',
            ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $field = $this->loginField();

        return [
            $field     => $data[$field] ?? null,
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            "data.{$this->loginField()}" => '账号或密码错误。',
        ]);
    }
}
