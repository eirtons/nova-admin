<?php

namespace Inova\NovaAdmin\Concerns;

use Filament\Panel;

/**
 * 后台准入：只有 users.is_admin 为真的用户能进 nova-admin 的 Panel。
 * 列由包内迁移添加，默认管理员由 AdminUserSeeder 标记。
 * 用户模型 implements FilamentUser 并 use 本 trait 即可；规则需变更时改包，各站升级即得。
 */
trait HasNovaAdminAccess
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === config('nova-admin.panel.id', 'admin')
            && (bool) $this->is_admin;
    }
}
