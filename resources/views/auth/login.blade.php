@extends('layouts.app')

@section('content')
    <h2>Вход</h2>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Пароль</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <button class="btn btn-primary">Войти</button>
    </form>
@endsection
