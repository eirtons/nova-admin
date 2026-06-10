{{-- 包视图不在宿主项目 Tailwind 扫描范围内，样式用内联保证生效 --}}
<div>
    @if ($truncated)
        <p style="font-size: 0.75rem; opacity: 0.7; margin-bottom: 0.5rem;">
            文件较大，仅显示最后 {{ $tail_kb }} KB，完整内容请下载。
        </p>
    @endif

    <pre style="max-height: 60vh; overflow: auto; font-size: 0.75rem; line-height: 1.5; white-space: pre-wrap; word-break: break-all;">{{ $content }}</pre>
</div>
