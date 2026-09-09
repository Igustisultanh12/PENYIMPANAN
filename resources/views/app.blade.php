<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'MyStorage') }} — Cloud Storage Mandiri Aman & Cepat</title>
    <meta name="description" content="Platform cloud storage modern mandiri dengan keamanan tinggi, upload chunk resumable, sharing fleksibel, dan kontrol akses granular.">

    <!-- Open Graph / Meta -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="MyStorage — Cloud Storage Mandiri">
    <meta property="og:description" content="Your files. Secure. Organized. Accessible anywhere.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Theme Initialization Script (Prevents FOUC) -->
    <script>
        (function() {
            try {
                const storedTheme = localStorage.getItem('theme');
                const isDark = storedTheme === 'dark' || (!storedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 font-sans antialiased selection:bg-blue-600 selection:text-white transition-colors duration-200">
    <div id="app" class="h-full flex flex-col"></div>
</body>
</html>
