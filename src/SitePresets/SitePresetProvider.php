<?php

namespace Inova\NovaAdmin\SitePresets;

/**
 * 一组建站预设（如垂直领域、主题）的候选清单，由宿主项目按自己的数据源实现。
 *
 * 清单只描述「部署时能选什么」，选中后怎么生效（seed 时激活主题、分发采集源等）仍由项目自己决定。
 */
interface SitePresetProvider
{
    /**
     * 全部可选项，键为部署时传入的 slug，值为部署系统下拉里显示的名称。
     *
     * @return array<string, string>
     */
    public function options(): array;

    /**
     * 最新一批（NEW）的 slug；部署系统把它们排在前面并加标记，不选时随机也优先从中挑。
     *
     * @return list<string>
     */
    public function newOptions(): array;
}
