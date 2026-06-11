<?php

namespace Nbutl\NovaAdmin\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class RobotsTxtPage extends AdsTxtPage
{
    protected static ?string $title = 'Robots.txt';

    protected static ?string $navigationLabel = 'Robots.txt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected string $configType = 'robots_txt';

    protected string $fieldLabel = 'Robots.txt 内容';

    protected string $placeholder = "User-agent: *\nAllow: /\nDisallow: /admin\n\nSitemap: {url}/sitemap.xml";

    protected function helperText(): string
    {
        return '保存后写入 public/robots.txt 并同步数据库，前台 GET /robots.txt 直接访问；'
            .'支持 {url} 占位符（输出时替换为当前域名）；未保存过时已预填默认模板。';
    }
}
