<x-filament-panels::page>
    <form wire:submit="runSearch">
        {{ $this->form }}

        {{-- 包视图不在宿主项目 Tailwind 扫描范围内，间距用内联样式保证生效 --}}
        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                搜索
            </x-filament::button>
        </div>
    </form>

    @if ($hasSearched)
        <x-filament::section>
            <x-slot name="heading">
                搜索结果（{{ count($searchResults) }} 条{{ $searchTruncated ? '，已达上限，请细化关键字' : '' }}）
            </x-slot>

            @if (empty($searchResults))
                <p style="font-size: 0.875rem; opacity: 0.7;">没有匹配的日志行。</p>
            @else
                <div style="max-height: 50vh; overflow: auto;">
                    @foreach ($searchResults as $m)
                        <div style="font-size: 0.75rem; font-family: monospace; padding: 0.25rem 0; border-bottom: 1px solid rgba(128,128,128,0.15); word-break: break-all;">
                            <span style="opacity: 0.6;">{{ $m['file'] }}:{{ $m['line'] }}</span>
                            {{ $m['text'] }}
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
