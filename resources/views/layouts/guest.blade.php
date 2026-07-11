<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Invitation') }} · Pejota</title>
    @livewireStyles
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: #f3f4f6; color: #111827; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #fff; width: 100%; max-width: 26rem; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; }
        label { display: block; font-size: .875rem; margin: .75rem 0 .25rem; }
        input { width: 100%; padding: .5rem .75rem; border: 1px solid #d1d5db; border-radius: .5rem; font-size: 1rem; }
        .btn { display: inline-block; margin-top: 1.25rem; width: 100%; padding: .625rem 1rem; border: 0; border-radius: .5rem; background: #00BF63; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .muted { color: #6b7280; font-size: .875rem; }
        .error { color: #b91c1c; font-size: .8125rem; margin-top: .25rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            {{ $slot }}
        </div>
    </div>
    @livewireScripts
</body>
</html>
