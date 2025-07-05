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
            border-top: 5px solid #4361ee;
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
            background: linear-gradient(90deg, #4361ee, #3a0ca3);
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
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: block;
        }

        .btn-auth {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
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
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
            background: linear-gradient(135deg, #3a56e8, #2f0b8a);
        }

        .alert-danger {
            background-color: #ff6b6b;
            color: white;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
            border-left: 4px solid #d00000;
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: #4361ee;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .auth-footer a:hover {
            color: #3a0ca3;
            text-decoration: underline;
        }
    </style>

    <div class="auth-container">
        <h2 class="auth-title">Profilga kirish</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Pochta</label>
                <input type="email" name="email" id="email" class="form-control" required placeholder="Pochtani kiriting">
                <i class="fas fa-envelope"></i>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Parol</label>
                <input type="password" name="password" id="password" class="form-control" required placeholder="Parolni kiriting">
                <i class="fas fa-lock"></i>
            </div>

            <button type="submit" class="btn btn-auth">
                <i class="fas fa-sign-in-alt me-2"></i> Kirish
            </button>

            <div class="auth-footer">
                Profilingiz yo'qmi? <a href="{{ route('register') }}">Ro'yxatdan o'ting</a>
            </div>
        </form>
    </div>
@endsection
