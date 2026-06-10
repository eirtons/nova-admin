{{-- 包视图不在宿主项目 Tailwind 扫描范围内，样式用内联保证生效 --}}
<x-filament-panels::page>
    <form wire:submit="loadEntries">
        {{ $this->form }}

        <div style="margin-top: 1rem;">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                查询
            </x-filament::button>
        </div>
    </form>

    <x-filament::section>
        <x-slot name="heading">
            日志条目（{{ count($entries) }} 条，最新在前{{ count($entries) >= $entriesLimit ? '，已达上限，请用文件 / 关键字 / 级别缩小范围' : '' }}）
        </x-slot>

        @if (empty($entries))
            <p style="font-size: 0.875rem; opacity: 0.7;">没有匹配的日志条目。</p>
        @else
            <div style="max-height: 65vh; overflow: auto; background: #16181d; border-radius: 0.5rem; padding: 0.375rem;">
                @foreach ($entries as $entry)
                    @php
                        $badge = match ($entry['level']) {
                            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => '#ef4444',
                            'WARNING', 'NOTICE'                       => '#f59e0b',
                            'INFO'                                    => '#3b82f6',
                            default                                   => '#6b7280',
                        };
                        $hasDetail = str_contains($entry['detail'], "\n");
                    @endphp
                    <details style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                        <summary style="display: flex; gap: 0.625rem; align-items: baseline; padding: 0.375rem 0.5rem; cursor: {{ $hasDetail ? 'pointer' : 'default' }}; font-size: 0.75rem; font-family: monospace; list-style: {{ $hasDetail ? 'revert' : 'none' }};">
                            <span style="flex: none; min-width: 4.5rem; text-align: center; padding: 0 0.375rem; border-radius: 0.25rem; background: {{ $badge }}; color: #fff; font-weight: 600;">{{ $entry['level'] }}</span>
                            <span style="flex: none; color: #9ca3af;">{{ $entry['time'] }}</span>
                            <span style="color: #e5e7eb; word-break: break-all;">{{ $entry['message'] }}</span>
                            <span style="flex: none; margin-left: auto; color: #6b7280;">{{ $entry['file'] }}</span>
                        </summary>
                        @if ($hasDetail)
                            <pre style="margin: 0 0.5rem 0.5rem; padding: 0.625rem; background: #0d0f12; border-radius: 0.375rem; color: #d4d4d8; font-size: 0.7rem; line-height: 1.55; max-height: 45vh; overflow: auto; white-space: pre-wrap; word-break: break-all;">{{ $entry['detail'] }}</pre>
                        @endif
                    </details>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
