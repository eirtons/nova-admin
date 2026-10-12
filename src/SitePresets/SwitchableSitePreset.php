<?php

namespace Inova\NovaAdmin\SitePresets;

/**
 * 可在建好的站点上读取与切换的建站预设（webdeploy「站点配置」经 nova-admin:site-ops 调用）。
 *
 * 与 SitePresetProvider 分开：只想在建站时预选、不想被远程切换的项目不必实现。
 */
interface SwitchableSitePreset
{
    /** 站点当前生效的 slug；尚未初始化返回 null */
    public function current(): ?string;

    /** 切换到 $slug（已由调用方校验在清单内）；破坏性操作（如清空内容）由实现自行负责 */
    public function apply(string $slug): void;
}
