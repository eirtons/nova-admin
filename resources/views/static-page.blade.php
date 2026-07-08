{{-- 包内兜底静态页模板：无主题项目开箱可用；有主题的项目在 config
     nova-admin.static_pages.frontend.view 指向自己的 Blade 即可替换。
     模板契约：$page->title / $page->body_html / $page->meta_description --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }} - {{ site_config('site_name', config('app.name')) }}</title>
    @if($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <link rel="canonical" href="{{ url('/'.$page->slug) }}">
    {!! site_ad_head('global_head') !!}
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; line-height: 1.7; color: #1f2328; background: #fafafa; }
        .np-shell { max-width: 72ch; margin: 0 auto; padding: 24px 20px 64px; }
        .np-home { font-size: 0.85rem; color: #57606a; text-decoration: none; }
        .np-home:hover { color: #1f2328; }
        h1 { font-size: 1.8rem; line-height: 1.25; margin: 18px 0; }
        h2 { font-size: 1.3rem; margin: 28px 0 10px; }
        p { margin: 0 0 1.25em; }
        a { color: #0969da; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <div class="np-shell">
        <nav aria-label="breadcrumb"><a class="np-home" href="{{ url('/') }}">&larr; {{ site_config('site_name', config('app.name')) }}</a></nav>
        <article>
            <h1>{{ $page->title }}</h1>
            {!! $page->body_html !!}
        </article>
    </div>
</body>
</html>
