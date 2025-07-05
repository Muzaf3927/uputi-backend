@extends('layouts.app')

@section('content')
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/images/register.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .auth-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 2.5rem;
            max-width: 500px;
            margin: 2rem auto;
            transform: translateY(0);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            border-top: 5px solid #4cc9f0;
        }

        .auth-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }

        .auth-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #2b2d42;
            font-weight: 700;
            font-size: 2rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .auth-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #4cc9f0, #4895ef);
            border-radius: 2px;
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.9);
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            height: auto;
        }

        .form-control:focus {
            background-color: white;
            border-color: #4895ef;
            box-shadow: 0 0 0 3px rgba(72, 149, 239, 0.2);
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: block;
        }

        .btn-auth {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(76, 201, 240, 0.3);
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 201, 240, 0.4);
            background: linear-gradient(135deg, #3ab9e0, #3a85df);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group i {
            position: absolute;
            right: 15px;
            top: 42px;
            color: #6c757d;
        }

        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.4s ease, background 0.4s ease;
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: #4895ef;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .auth-footer a:hover {
            color: #3a6bc8;
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div class="auth-container">
        <h2 class="auth-title">Ro'yxatdan o'tish</h2>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="form-group">
                <label for="name" class="form-label">Ism</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="Ismingizni kiriting">
                <i class="fas fa-user"></i>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Pochta</label>
                <input type="email" name="email" id="email" class="form-control" required placeholder="Pochtangizni kiriting">
                <i class="fas fa-envelope"></i>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Parol</label>
                <input type="password" name="password" id="password" class="form-control" required
                       placeholder="Parol kiriting" oninput="checkPasswordStrength(this.value)">
                <i class="fas fa-lock"></i>
                <div class="password-strength">
                    <div class="password-strength-bar" id="password-strength-bar"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Parolni tasdiqlash</label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                       class="form-control" required placeholder="Parolni takrorlang">
                <i class="fas fa-lock"></i>
            </div>

            <button type="submit" class="btn btn-auth">
                <i class="fas fa-user-plus me-2"></i> Ro'yxatdan o'tish
            </button>

            <div class="auth-footer">
                Profilingiz bormi? <a href="{{ route('login') }}">Kirish</a>
            </div>
        </form>
    </div>

    <script>
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength-bar');
            let strength = 0;

            if (password.length > 0) strength += 30;
            if (password.length >= 8) strength += 30;
            if (/[A-Z]/.test(password)) strength += 40;
            if (/[0-9]/.test(password)) strength += 40;
            if (/[^A-Za-z0-9]/.test(password)) strength += 40;

            strengthBar.style.width = strength + '%';

            if (strength < 40) {
                strengthBar.style.background = '#ff6b6b';
            } else if (strength < 80) {
                strengthBar.style.background = '#faa307';
            } else {
                strengthBar.style.background = '#2ec4b6';
            }
        }
    </script>
@endsection
