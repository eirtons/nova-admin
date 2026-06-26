{{--
    暗色代码编辑器：透明 textarea 叠在 highlight.js 暗色高亮层之上。
    后台保持亮色，编辑器单独暗色。novaCodeEditor 组件由 NovaAdminPlugin 注册。
    与 Filament 表单 state 通过 Livewire entangle 双向绑定。
--}}
@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="novaCodeEditor()"
        x-modelable="code"
        wire:model="{{ $statePath }}"
        class="nova-ce"
        style="border: 1px solid rgb(209 213 219);"
    >
        <pre x-ref="highlight" aria-hidden="true"><code class="hljs language-xml"></code></pre>
        <textarea
            x-ref="input"
            x-model="code"
            x-on:scroll="onScroll()"
            @if ($isDisabled) disabled @endif
            spellcheck="false"
            autocomplete="off"
            autocapitalize="off"
        ></textarea>
    </div>
</x-dynamic-component>
