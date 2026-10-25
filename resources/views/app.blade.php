<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
        @php($sessionDeadline = \App\Http\Middleware\SessionDeadline::of(request()))
        @if ($sessionDeadline !== null)
            {{-- AU-08's absolute deadline outside the page data, for the first load (UXH-11; TASK-016). --}}
            <meta name="session-deadline" content="{{ $sessionDeadline }}">
        @endif
        <title>{{ config('app.name') }}</title>
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
