<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'BlablaCar MVP' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary-color: #4cc9f0;
            --text-dark: #2b2d42;
            --text-light: #8d99ae;
            --bg-light: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
        }

        .navbar-brand i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .navbar {
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            background: white !important;
        }

        .user-greeting {
            font-weight: 500;
            color: var(--text-dark);
            margin-right: 1rem;
        }

        .btn-logout {
            background: transparent;
            border: none;
            color: var(--text-light);
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
        }

        .btn-logout:hover {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }

        .btn-logout i {
            margin-right: 5px;
            font-size: 0.9rem;
        }

        .container {
            flex: 1;
        }

        footer {
            background: white;
            padding: 1.5rem 0;
            margin-top: 3rem;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.05);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-links a {
            color: var(--text-light);
            margin-right: 1.5rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .footer-copyright {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .footer-links {
                margin-bottom: 1rem;
            }

            .footer-links a {
                margin: 0 0.75rem;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand" href="/">
            <i class="fas fa-car-side"></i>BlaBlaCar MVP
        </a>
        @auth
            <div class="d-flex align-items-center">
                <span class="user-greeting">Привет, {{ auth()->user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>Выйти
                    </button>
                </form>
            </div>
        @endauth
    </div>
</nav>

<main class="container py-4">
    @yield('content')
</main>

<footer>
    <div class="container footer-content">
        <div class="footer-links">
            <a href="#"><i class="fas fa-info-circle"></i> О сервисе</a>
            <a href="#"><i class="fas fa-question-circle"></i> Помощь</a>
            <a href="#"><i class="fas fa-envelope"></i> Контакты</a>
        </div>
        <div class="footer-copyright">
            &copy; {{ date('Y') }} BlaBlaCar MVP. Все права защищены.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
