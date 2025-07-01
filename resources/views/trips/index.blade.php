@extends('layouts.app')

@section('content')
    <h2 class="mb-4">Все доступные поездки</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @elseif(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <a href="{{ route('trips.create') }}" class="btn btn-primary mb-4">Создать поездку</a>

    @forelse($trips as $trip)
        <div class="card mb-3">
            <div class="card-body">
                <h5>{{ $trip->from_city }} → {{ $trip->to_city }}</h5>
                <p>
                    <strong>Дата:</strong> {{ $trip->date }} {{ $trip->time }}<br>
                    <strong>Мест:</strong> {{ $trip->seats }}<br>
                    <strong>Цена:</strong> {{ $trip->price }} ₽<br>
                    <strong>Водитель:</strong> {{ $trip->driver->name }}
                </p>

                @if($trip->user_id !== auth()->id())
                    <form action="{{ route('bookings.store', $trip) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-auto">
                            <label for="seats_{{ $trip->id }}" class="form-label">Мест:</label>
                            <input type="number" name="seats" min="1" max="{{ $trip->seats }}" value="1"
                                   class="form-control form-control-sm" id="seats_{{ $trip->id }}" required>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-success">Забронировать</button>
                        </div>
                    </form>
                @else
                    <p class="text-muted">Это ваша поездка</p>
                @endif
            </div>
        </div>
    @empty
        <p>Пока поездок нет.</p>
    @endforelse
@endsection
