<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2.9 前 seed 时占位符已替换成当时的值写进正文，改站点设置不会跟着变。
 * 这里把正文中等于当前站名 / 站点描述 / 联系邮箱的文字换回占位符；输出时会替换回同样的值，页面当下不变。
 * seed 之后改过设置的站点，正文里的旧值匹配不到，需在后台手工改。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('static_pages') || ! Schema::hasTable('site_configs')) {
            return;
        }

        // 直接查表，不走 SiteConfigService：它会把本进程读到的值缓存下来，迁移后同进程（测试、migrate --seed）再写设置会读到旧值
        $settings = DB::table('site_configs')->whereIn('key', ['site_name', 'contact_email'])->pluck('value', 'key');
        $values = [
            '{{contact_email}}'    => (string) ($settings['contact_email'] ?? config('nova-admin.site_defaults.contact_email', '')),
            '{{site_description}}' => (string) config('nova-admin.static_pages.site_description', 'an online service'),
            '{{site_name}}'        => (string) ($settings['site_name'] ?? ''),
        ];

        // 先换邮箱（可能含站名），站名放最后
        $patterns = [];
        foreach (['{{contact_email}}', '{{site_description}}', '{{site_name}}'] as $placeholder) {
            $value = trim($values[$placeholder]);
            if ($value === '') {
                continue;
            }
            // 整词匹配，且不在域名、邮箱、路径里（如站名 filmhub 不动 filmhub.com）
            $patterns['/(?<![\w.@\/-])'.preg_quote($value, '/').'(?![\w@\/-]|\.\w)/u'] = $placeholder;
        }

        if ($patterns === []) {
            return;
        }

        DB::table('static_pages')->orderBy('id')->get(['id', 'content'])->each(function ($page) use ($patterns): void {
            $content = (string) $page->content;
            $restored = (string) preg_replace(array_keys($patterns), array_values($patterns), $content);

            if ($restored !== $content) {
                // 只改正文，不动 updated_at / title / meta，页面输出不变
                DB::table('static_pages')->where('id', $page->id)->update(['content' => $restored]);
            }
        });
    }
};
