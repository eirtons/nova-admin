{{-- 只读代码高亮块：highlight.js（由 NovaAdminPlugin 注入）渲染。$getState() 为代码字符串。 --}}
@php($code = $getState())

@if (blank($code))
    <p style="font-size: 0.875rem; opacity: 0.7;">（未配置）</p>
@else
    <div x-data x-init="$nextTick(() => window.novaHighlight && window.novaHighlight())">
        <pre style="margin: 0; padding: 1rem; background: #0d0f12; border-radius: 0.5rem; font-size: 0.75rem; line-height: 1.6; max-height: 50vh; overflow: auto;"><code class="nova-hl language-xml" style="background: transparent; white-space: pre-wrap; word-break: break-all;">{{ $code }}</code></pre>
    </div>
@endif
