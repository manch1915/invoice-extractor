<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Invoice and receipt extraction with a review queue and exports to Excel, 1C, Google Sheets and Xero.">
    <meta name="theme-color" content="#f6f5f1" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#111214" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script>
        (() => {
            const saved = localStorage.getItem('theme');
            const dark = saved ? saved === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    <x-inertia::head>
        <title>{{ config('app.name') }}</title>
    </x-inertia::head>
</head>
<body>
    <x-inertia::app />
</body>
</html>
