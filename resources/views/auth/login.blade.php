@extends('layouts.app')

@section('content')
    <style>
        body {
            background: url('/images/register.jpg') no-repeat center center fixed;
            background-size: cover;
        }

        .login-container {
            /*background-color: rgba(255, 255, 255, 0.95);*/
            /*padding: 30px;*/
            /*border-radius: 20px;*/
            /*box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);*/
            /*max-width: 450px;*/
            /*margin: 80px auto;*/
            background-color: rgba(255, 255, 255, 0.5);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            max-width: 500px;
            margin: 60px auto;
        }

        .login-container h2 {
            /*text-align: center;*/
            /*margin-bottom: 25px;*/
            /*color: #2c3e50;*/
            /*font-weight: bold;*/
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
            font-weight: bold;
        }

        .form-control {
            /*background-color: rgba(255, 255, 255, 0.7);*/
            /*border: 1px solid #ccc;*/
            /*color: #2c3e50;*/
            background-color: rgba(255, 255, 255, 0.7);
            border: 1px solid #ccc;
            color: #2c3e50;
        }

        .form-control:focus {
            /*background-color: rgba(255, 255, 255, 0.85);*/
            /*border-color: #3498db;*/
            /*box-shadow: 0 0 6px rgba(52, 152, 219, 0.5);*/
            background-color: rgba(255, 255, 255, 0.85);
            box-shadow: 0 0 5px rgba(46, 204, 113, 0.7);
            border-color: #2ecc71;
        }

        .btn-primary {
            background-color: #2ecc71;
            border: none;
            width: 100%;
            padding: 10px;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .alert {
            text-align: center;
        }

        label {
            font-weight: bold;
            color: black;
        }
    </style>

    <div class="login-container">
        <h2>Вход</h2>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Parol</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">Kirish</button>
        </form>
    </div>
@endsection
