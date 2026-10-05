@php
    $ogTitle = $title ?? ($pageTitle ?? null);
    if (! $ogTitle || trim($ogTitle) === '') {
        $ogTitle = config('app.brand_name', 'PAIRfect Paws');
    }
    $ogDescription = $description ?? 'PAIRfect Paws - Shelter management and pet adoption matching system in partnership with Red Cubs Pet Patrol.';
    $ogImage = $image ?? url('images/og-image-1200x630.png');
@endphp
<!-- Open Graph / Facebook / Messenger / WhatsApp / LinkedIn -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:secure_url" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/png">
<meta property="og:image:alt" content="PAIRfect Paws">
<meta property="og:site_name" content="PAIRfect Paws">

<!-- Twitter / X -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ url()->current() }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">
