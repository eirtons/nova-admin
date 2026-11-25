<?php

namespace Inova\NovaAdmin\Models;

use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Inova\NovaAdmin\Services\EdgeCacheService;
use Inova\NovaAdmin\Services\SiteConfigService;
use Inova\NovaAdmin\Services\SitemapService;

class StaticPage extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    protected $table = 'static_pages';

    protected $fillable = [
        'slug',
        'title',
        'meta_description',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // 编辑器所见即所得：正文首个 H1 即页面标题；meta 留空时从正文自动生成摘要。
        static::saving(function (StaticPage $page): void {
            $h1 = static::leadingH1Text((string) $page->content);
            if ($h1 !== '') {
                $page->title = $h1;
            }

            if (blank($page->meta_description)) {
                // 去标签后解码实体：存纯文本，输出时由 Blade 统一转义一次，否则 & 会变成 &amp;amp;
                $page->meta_description = Str::limit(
                    trim((string) preg_replace('/\s+/', ' ', static::plainText($page->body_html))),
                    155,
                    '',
                ) ?: null;
            }
        });

        // 页脚的静态页链接与静态页本身都在前台整页缓存与 sitemap 中
        static::saved(function (): void {
            app(SitemapService::class)->forget();
            app(EdgeCacheService::class)->purgeLater('static_pages');
        });
        static::deleted(function (): void {
            app(SitemapService::class)->forget();
            app(EdgeCacheService::class)->purgeLater('static_pages');
        });
    }

    /**
     * 正文占位符 => 当前值。content 里存占位符、输出时替换，后台改站点设置后静态页随之变化。
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        $config = app(SiteConfigService::class);

        return [
            '{{site_name}}'        => (string) ($config->get('site_name') ?: 'this website'),
            '{{site_description}}' => (string) config('nova-admin.static_pages.site_description', 'an online service'),
            '{{contact_email}}'    => (string) $config->get('contact_email', config('nova-admin.site_defaults.contact_email', '')),
        ];
    }

    /** 标题取自正文 H1，可能含占位符，读取时替换（纯文本，由 Blade 转义）。 */
    protected function title(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value === null ? null : strtr($value, static::placeholders()));
    }

    /**
     * 前台正文：content 剥掉开头与标题重复的 H1（前台模板自行渲染 <h1>{{ $page->title }}</h1>），并替换占位符。
     */
    public function getBodyHtmlAttribute(): string
    {
        $safeHtml = (string) $this->renderRichContent('content');
        $body = trim((string) preg_replace('/^\s*<h1[^>]*>.*?<\/h1>/is', '', $safeHtml, 1));

        return strtr($body, array_map(fn (string $value) => e($value), static::placeholders()));
    }

    protected static function leadingH1Text(string $content): string
    {
        if (preg_match('/^\s*<h1[^>]*>(.*?)<\/h1>/is', $content, $m)) {
            return trim(static::plainText($m[1]));
        }

        return '';
    }

    /** HTML 片段转纯文本（去标签 + 解码实体）。 */
    protected static function plainText(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function setUpRichContent(): void
    {
        // 富文本内插图存 public 磁盘，前台可经 /storage 直接访问（与站点设置的 Logo/Favicon 一致）
        $this->registerRichContent('content')
            ->fileAttachmentsDisk('public');
    }
}
