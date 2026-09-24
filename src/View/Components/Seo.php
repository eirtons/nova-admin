<?php

namespace Inova\NovaAdmin\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * 前台 <head> 的 SEO 与站点标识，数据来自后台「站点设置」：
 * <title>（按 meta_title_template 拼装）、description、keywords、canonical、favicon、OG / Twitter 卡片。
 *
 * 布局里放 <x-nova-seo /> 即可；页面用 @section('title' | 'description' | 'canonical' | 'og_image')
 * 覆盖，也可直接传同名属性。title 只写页面自身标题，站点名由模板拼上。
 */
class Seo extends Component
{
    public string $documentTitle;

    public string $siteName;

    public ?string $description;

    public ?string $keywords;

    public string $canonical;

    public ?string $image;

    public ?string $favicon;

    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $canonical = null,
        ?string $image = null,
    ) {
        $this->siteName = (string) site_setting('site_name');

        $title ??= $this->section('title');
        $this->documentTitle = $title === null
            ? $this->homeTitle()
            : strtr((string) (site_setting('meta_title_template') ?: '{title} | {site_name}'), [
                '{title}' => $title,
                '%s' => $title,
                '{site_name}' => $this->siteName,
            ]);

        $this->description = $description ?? $this->section('description') ?? (site_setting('meta_description') ?: null);
        $this->keywords = site_setting('meta_keywords') ?: null;
        $this->canonical = $canonical ?? $this->section('canonical') ?? url()->current();
        $this->image = $image ?? $this->section('og_image');
        $this->favicon = site_media_url('favicon_path');
    }

    /** 未指定标题的页面（通常是首页）：站点名 + 副标题。 */
    protected function homeTitle(): string
    {
        $subtitle = site_setting('subtitle');

        return filled($subtitle) ? $this->siteName.' - '.$subtitle : $this->siteName;
    }

    /** @section('x', $value) 存进去的是转义后的内容，这里还原，输出时再统一转义。 */
    protected function section(string $name): ?string
    {
        $content = trim((string) app('view')->yieldContent($name));

        return $content === '' ? null : html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
    }

    public function render(): View
    {
        return view('nova-admin::components.seo');
    }
}
