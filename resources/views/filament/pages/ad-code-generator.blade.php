{{-- 包视图不在宿主项目 Tailwind 扫描范围内，样式用内联保证生效 --}}
<x-filament-panels::page>
    <form wire:submit="generate">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                生成代码
            </x-filament::button>
        </div>
    </form>

    @if ($generated)
        <x-filament::section>
            <x-slot name="heading">生成结果</x-slot>
            <x-slot name="description">将以下完整代码放置于网站全局 &lt;head&gt; 标签内（广告管理 → 全局 Head 的 Head 代码）</x-slot>

            <div x-data="{ copied: false }" style="position: relative;">
                <x-filament::button
                    size="sm"
                    icon="heroicon-m-clipboard-document"
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 1;"
                >
                    <span x-show="!copied">复制</span>
                    <span x-show="copied" x-cloak>已复制</span>
                </x-filament::button>

                <pre x-ref="code" style="margin: 0; padding: 1rem; background: #0d0f12; border-radius: 0.5rem; color: #d4d4d8; font-size: 0.75rem; line-height: 1.6; max-height: 60vh; overflow: auto; white-space: pre-wrap; word-break: break-all;">{{ $generated }}</pre>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
