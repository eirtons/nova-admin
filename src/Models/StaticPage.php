<?php

namespace Nbutl\NovaAdmin\Models;

use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;

class StaticPage extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    protected $table = 'static_pages';

    protected $fillable = [
        'slug',
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected function setUpRichContent(): void
    {
        // 富文本内插图存 public 磁盘，前台可经 /storage 直接访问（与站点设置的 Logo/Favicon 一致）
        $this->registerRichContent('content')
            ->fileAttachmentsDisk('public');
    }
}
