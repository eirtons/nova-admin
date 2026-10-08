<title>{{ $documentTitle }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
@if ($keywords)
    <meta name="keywords" content="{{ $keywords }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
@if ($robots)
    <meta name="robots" content="{{ $robots }}">
@endif
@if ($favicon)
    <link rel="icon" href="{{ $favicon }}">
@endif
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $documentTitle }}">
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:type" content="{{ $ogType }}">
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $documentTitle }}">
@if ($description)
    <meta name="twitter:description" content="{{ $description }}">
@endif
@if ($image)
    <meta name="twitter:image" content="{{ $image }}">
@endif
