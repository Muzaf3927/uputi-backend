@extends('layouts.app')

@section('content')
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/images/rul.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            color: #fff;
            position: relative;
            display: inline-block;
        }
        .page-title:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #3f51b5, #00bcd4);
            border-radius: 3px;
        }
        .trip-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #3f51b5;
        }
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.2);
        }
        .trip-card h4 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        .trip-card h4:before {
            content: '➤';
            margin-right: 10px;
            color: #3f51b5;
        }
        .trip-info {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .info-item {
            display: flex;
            align-items: center;
            font-size: 1rem;
        }
        .info-item strong {
            margin-right: 8px;
            color: #3f51b5;
            min-width: 120px;
        }
        .btn-details {
            background: linear-gradient(135deg, #3f51b5, #2196f3);
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.85rem;
            box-shadow: 0 4px 10px rgba(63, 81, 181, 0.3);
            transition: all 0.3s ease;
        }
        .btn-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(63, 81, 181, 0.4);
        }
        .no-trips {
            color: #fff;
            font-size: 1.2rem;
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            backdrop-filter: blur(5px);
        }
    </style>

    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="page-title">Faol safarlar</h1>
        </div>

        @if($trips->count() > 0)
            @foreach ($trips as $trip)
                <div class="trip-card">
                    <h4>{{ $trip->from_city }} → {{ $trip->to_city }}</h4>

                    <div class="trip-info">
                        <div class="info-item">
                            <strong>Kun va vaqti:</strong>
                            <span>{{ $trip->date }} в {{ $trip->time }}</span>
                        </div>
                        <div class="info-item">
                            <strong>Narx:</strong>
                            <span>{{ number_format($trip->price, 0, ',', ' ') }} сум</span>
                        </div>
                        <div class="info-item">
                            <strong>Bo'sh o'rinlar:</strong>
                            <span class="{{ $trip->seats < 3 ? 'text-danger' : 'text-success' }}">
                                {{ $trip->seats }}
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Haydovchi:</strong>
                            <span>{{ $trip->driver->name ?? 'Неизвестен' }}</span>
                        </div>
                    </div>

                    <a href="{{ route('trips.show', $trip) }}" class="btn btn-details text-white">
                        Safarlar haqida batafsil
                    </a>
                </div>
            @endforeach
        @else
            <div class="no-trips">
                <i class="fas fa-car-side fa-3x mb-3" style="opacity: 0.7;"></i>
                <p>Hozirgi vaqtda faol safarlar yuq</p>
            </div>
        @endif
    </div>
@endsection
