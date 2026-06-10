<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        {{-- 包视图不在宿主项目 Tailwind 扫描范围内，间距用内联样式保证生效 --}}
        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
                保存
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
