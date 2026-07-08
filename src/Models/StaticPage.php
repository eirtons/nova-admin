<?php

namespace Inova\NovaAdmin\Models;

use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
                $page->meta_description = Str::limit(
                    trim((string) preg_replace('/\s+/', ' ', strip_tags($page->body_html))),
                    155,
                    '',
                ) ?: null;
            }
        });
    }

    /**
     * 前台正文：content 剥掉开头与标题重复的 H1（前台模板自行渲染 <h1>{{ $page->title }}</h1>）。
     */
    public function getBodyHtmlAttribute(): string
    {
        return trim((string) preg_replace('/^\s*<h1[^>]*>.*?<\/h1>/is', '', (string) $this->content, 1));
    }

    protected static function leadingH1Text(string $content): string
    {
        if (preg_match('/^\s*<h1[^>]*>(.*?)<\/h1>/is', $content, $m)) {
            return trim(strip_tags($m[1]));
        }

        return '';
    }

    protected function setUpRichContent(): void
    {
        // 富文本内插图存 public 磁盘，前台可经 /storage 直接访问（与站点设置的 Logo/Favicon 一致）
        $this->registerRichContent('content')
            ->fileAttachmentsDisk('public');
    }
}
