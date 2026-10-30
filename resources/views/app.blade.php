
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (($runtime ?? 'laravel') === 'laravel')
        <link rel="manifest" href="/sigei.webmanifest">
        <meta name="theme-color" content="#dc3545">
    @endif

    <title>SIGEI | Gestión de emergencias</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app" data-runtime="{{ $runtime ?? 'laravel' }}"></div>
</body>
</html>
