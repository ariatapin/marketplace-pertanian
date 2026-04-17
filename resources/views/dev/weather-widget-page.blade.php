<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weather Widget</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="bg-slate-100 p-6 text-slate-900">
    <h1 class="mb-4 text-xl font-semibold">TEST WIDGET CUACA</h1>

    <x-weather-widget />

    @livewireScripts
</body>
</html>

