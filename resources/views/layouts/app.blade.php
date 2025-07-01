<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'BlablaCar MVP' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">BlaBlaCar MVP</a>
        @auth
            <div class="ms-auto">
                Привет, {{ auth()->user()->name }} |
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-link btn-sm">Выйти</button>
                </form>
            </div>
        @endauth
    </div>
</nav>

<div class="container">
    @yield('content')
</div>
</body>
</html>
