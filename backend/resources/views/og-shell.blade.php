<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Horizon Interstellar</title>
    <meta name="description" content="Private Star Citizen organization systems. Access restricted.">

    <meta name="redirect-url" content="{{ url('/auth/discord') }}">

    <link rel="canonical" href="{{ url('/') }}">

    <meta property="og:site_name" content="Horizon Interstellar">
    <meta property="og:title" content="Horizon Interstellar">
    <meta property="og:description" content="Private Star Citizen organization systems. Access restricted.">
    <meta property="og:image" content="{{ asset('images/PNG_Primaryly_Logo.png.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('images/PNG_Primaryly_Logo.png') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:type" content="website">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Horizon Interstellar">
    <meta name="twitter:description" content="Private Star Citizen organization systems. Access restricted.">
    <meta name="twitter:image" content="{{ asset('images/PNG_Primaryly_Logo.png') }}">

    <script>
        const redirectUrl = document.querySelector('meta[name="redirect-url"]')?.getAttribute('content');
        if (redirectUrl) window.location.replace(redirectUrl);
    </script>

    <noscript>
        <meta http-equiv="refresh" content="0;url={{ url('/auth/discord') }}">
    </noscript>
</head>
<body></body>
</html>
