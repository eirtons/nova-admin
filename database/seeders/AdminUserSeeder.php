<?php

namespace Nbutl\NovaSiteCore\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * 默认管理员写入的唯一权威逻辑：字段检测、updateOrCreate、容错。
 * create-admin / install 命令均调用本 Seeder，不重复实现。
 */
class AdminUserSeeder extends Seeder
{
    /** 是否覆盖已存在账号的密码（由命令 --force 透传）。 */
    public bool $force = false;

    public function run(): void
    {
        $conf = config('nova-site-core.admin');

        $name     = $conf['default_name'];
        $email    = $conf['default_email'];
        $password = $conf['default_password'];

        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        // 只写入确认存在的列，避免业务必填字段冲突
        $attributes = [
            'name'     => $name,
            'password' => Hash::make($password),
        ];

        if (Schema::hasColumn('users', 'username')) {
            $attributes['username'] = $name;
        }
        if (Schema::hasColumn('users', 'is_admin')) {
            $attributes['is_admin'] = true;
        }
        if (Schema::hasColumn('users', 'email_verified_at')) {
            $attributes['email_verified_at'] = now();
        }

        try {
            $existing = $userModel::query()->where('email', $email)->first();

            if ($existing && ! $this->force) {
                $this->command?->warn("管理员 {$email} 已存在，未覆盖（使用 --force 可重置密码）。");

                return;
            }

            $userModel::query()->updateOrCreate(
                ['email' => $email],
                $attributes,
            );

            $this->command?->info("默认管理员已就绪：{$name} / {$password}（登录邮箱 {$email}）。");

            if (app()->environment('production')) {
                $this->command?->warn('当前为 production 环境，默认管理员为 nova/nova，请立即登录后台修改密码！');
            }
        } catch (\Illuminate\Database\QueryException $e) {
            $this->command?->error(
                "无法自动创建管理员：users 表可能存在必填业务字段。\n".
                "请手动创建管理员，或为相关字段设默认值后重试。\n".
                '原始错误：'.$e->getMessage()
            );
        }
    }
}
