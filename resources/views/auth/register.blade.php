@extends('layouts.app')

@section('content')
    <h2>Регистрация</h2>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label>Имя</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Пароль</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Подтверждение пароля</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <button class="btn btn-success">Зарегистрироваться</button>
    </form>
@endsection
