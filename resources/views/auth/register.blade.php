@extends('layouts.app')

@section('content')
    <style>
        body {
            background: url('/images/register.jpg') no-repeat center center fixed;
            background-size: cover;
        }

        .register-container {
            background-color: rgba(255, 255, 255, 0.5);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            max-width: 500px;
            margin: 60px auto;
        }

        .register-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
            font-weight: bold;
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.7);
            border: 1px solid #ccc;
            color: #2c3e50;
        }

        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.85);
            box-shadow: 0 0 5px rgba(46, 204, 113, 0.7);
            border-color: #2ecc71;
        }

        .btn-success {
            background-color: #2980b9;
            border: none;
            width: 100%;
            padding: 10px;
            font-size: 16px;
            transition: all 0.3s ease-in-out;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        label {
            font-weight: bold;
            color: black;
        }
    </style>

    <div class="register-container">
        <h2>Регистрация</h2>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Ismingiz</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label" >Parol</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Parolni tasdiqlash</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success">Ro'yhatdan o'tish</button>
        </form>
    </div>
@endsection
