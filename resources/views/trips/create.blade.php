@extends('layouts.app')

@section('content')
    <h2 class="mb-4">Создать поездку</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('trips.store') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label>Откуда</label>
            <input type="text" name="from_city" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Куда</label>
            <input type="text" name="to_city" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Дата</label>
            <input type="date" name="date" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Время</label>
            <input type="time" name="time" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Доступные места</label>
            <input type="number" name="seats" min="1" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Цена за место (₽)</label>
            <input type="number" name="price" min="0" step="0.01" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Комментарий (необязательно)</label>
            <textarea name="note" class="form-control" rows="3"></textarea>
        </div>

        <button class="btn btn-success">Создать поездку</button>
    </form>
@endsection
