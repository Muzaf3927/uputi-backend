@extends('layouts.app')

@section('content')
    <div class="text-center">
        <h1>Добро пожаловать в BlaBlaCar MVP</h1>
        @guest
            <a href="/login" class="btn btn-primary mt-3">Войти</a>
            <a href="/register" class="btn btn-outline-primary mt-3">Регистрация</a>
        @endguest
    </div>
@endsection
