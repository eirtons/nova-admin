{{-- 包视图不在宿主项目 Tailwind 扫描范围内，样式用内联保证生效 --}}
<x-filament-panels::page>
    {{-- 快速识别：粘贴现有 GPT 广告代码，自动解析参数填充下方表单（默认折叠） --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">快速识别</x-slot>
        <x-slot name="description">粘贴现有的 Google Ad Manager 广告代码，自动识别并填充下方表单</x-slot>

        <div x-data="novaAdIdentify()">
            <textarea
                x-ref="paste"
                x-model="pasted"
                rows="5"
                placeholder="在此粘贴广告代码（含 googletag.defineSlot / defineOutOfPageSlot 的脚本）…"
                style="width: 100%; padding: 0.75rem; border: 1px solid rgb(209 213 219); border-radius: 0.5rem; background: #0d1117; color: #e6edf3; font: 0.8rem/1.5 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; resize: vertical;"
            ></textarea>

            <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem; align-items: center;">
                <x-filament::button size="sm" icon="heroicon-m-sparkles" x-on:click="identify()">
                    自动识别
                </x-filament::button>
                <x-filament::button size="sm" color="gray" x-on:click="pasted = ''; message = null">
                    清空
                </x-filament::button>
                <span x-show="message" x-text="message" x-cloak
                      :style="ok ? 'color:#16a34a;font-size:0.8rem' : 'color:#d97706;font-size:0.8rem'"></span>
            </div>
        </div>
    </x-filament::section>

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

            <div x-data="{ copied: false }" x-init="$nextTick(() => window.novaHighlight && window.novaHighlight())" wire:key="generated-{{ md5($generated) }}" style="position: relative;">
                <x-filament::button
                    size="sm"
                    icon="heroicon-m-clipboard-document"
                    x-on:click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 1;"
                >
                    <span x-show="!copied">复制</span>
                    <span x-show="copied" x-cloak>已复制</span>
                </x-filament::button>

                <pre style="margin: 0; padding: 1rem; background: #0d0f12; border-radius: 0.5rem; font-size: 0.75rem; line-height: 1.6; max-height: 60vh; overflow: auto;"><code x-ref="code" class="nova-hl language-xml" style="background: transparent; white-space: pre-wrap; word-break: break-all;">{{ $generated }}</code></pre>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
