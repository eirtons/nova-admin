<?php

namespace Nbutl\NovaAdmin\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

/**
 * 账号 + 密码登录。登录字段由 config('nova-admin.admin.login_field') 决定
 * （name / username / email）。
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        $field = config('nova-admin.admin.login_field', 'name');

        return TextInput::make($field)
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
        $field = config('nova-admin.admin.login_field', 'name');

        return [
            $field      => $data[$field] ?? null,
            'password'  => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        $field = config('nova-admin.admin.login_field', 'name');

        throw ValidationException::withMessages([
            "data.{$field}" => '账号或密码错误。',
        ]);
    }
}
