<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">


        <meta name="description" content="Private Star Citizen organization. Access restricted">
        <meta property="og:site_name" content="Horizon Interstellar">
        <meta property="og:title" content="Horizon Interstellar">
        <meta property="og:description" content="Private Star Citizen organization. Access restricted.">
        <meta property="og:image" content="{{ config('app.public_url') }}/images/PNG_Primaryly_Logo.png">
        <meta property="og:image:secure_url" content="{{ config('app.public_url') }}/images/PNG_Primaryly_Logo.png">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ config('app.public_url') }}/?v=2">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Horizon Interstellar">
        <meta property="twitter:domain" content="{{ parse_url(config('app.public_url'), PHP_URL_HOST) }}">
        <meta property="twitter:url" content="{{ config('app.public_url') }}/?v=2">
        <meta name="twitter:description" content="Private Star Citizen organization. Access restricted.">
        <meta name="twitter:image" content="{{ config('app.public_url') }}/images/PNG_Primaryly_Logo.png">
        <title inertia>{{ config('app.name', 'Horizon Interstellar') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/PNG_Symbolly_logo.png') }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
