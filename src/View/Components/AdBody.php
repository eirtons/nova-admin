<?php

namespace Inova\NovaAdmin\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Inova\NovaAdmin\Services\AdService;

class AdBody extends Component
{
    // 本请求是否已输出居中样式（容器实例随请求重建）
    private const STYLE_FLAG = 'nova-admin.ad-slot-style-emitted';

    public string $html;

    public function __construct(
        public string $position,
        AdService $ads,
        // anchor / interstitial 这类浮层广告自己定位，套居中容器会破坏其布局。
        public bool $wrapper = true,
    ) {
        $this->html = $ads->body($position);
    }

    public function shouldRender(): bool
    {
        return trim($this->html) !== '';
    }

    public function render(): HtmlString
    {
        if (! $this->wrapper) {
            return new HtmlString($this->html);
        }

        return new HtmlString(self::centeringStyle().view('nova-admin::components.ad-slot', [
            'html' => $this->html,
            'position' => $this->position,
        ])->render());
    }

    /**
     * 包装层的 text-align:center 只能居中行内内容。GPT 渲染时会在槽位 div 里插一层固定宽度的块级
     * `…__container__`（如 width:320px），广告商的固定尺寸代码也常是块级 div，二者都会贴左。
     * 这里只给块级子元素和 GPT 容器加 margin-inline:auto：固定宽度的居中，宽度 auto 的（AdSense 自适应 ins）
     * 照常撑满。不用 flex / justify-items 居中，那会让宽度 auto 的 ins 收缩为 0，AdSense 报 availableWidth=0。
     *
     * 放在组件而不是 ad-slot 视图里输出：项目 publish 覆盖了视图也照样生效；每个请求只输出一次。
     */
    private static function centeringStyle(): string
    {
        if (app()->bound(self::STYLE_FLAG)) {
            return '';
        }
        app()->instance(self::STYLE_FLAG, true);

        return '<style>.ad>div,.ad>ins,.ad [id$="__container__"]{margin-left:auto!important;margin-right:auto!important}</style>';
    }
}
