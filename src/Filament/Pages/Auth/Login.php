<?php

namespace Nbutl\NovaSiteCore\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * 账号 + 密码登录。登录字段由 config('nova-site-core.admin.login_field') 决定
 * （name / username / email）。
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        $field = config('nova-site-core.admin.login_field', 'name');

        return TextInput::make($field)
            ->label('账号')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $field = config('nova-site-core.admin.login_field', 'name');

        return [
            $field      => $data[$field] ?? null,
            'password'  => $data['password'],
        ];
    }
}
