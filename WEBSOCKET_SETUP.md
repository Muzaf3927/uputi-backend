# Настройка WebSockets с Laravel Reverb для Laravel Cloud

## Что было сделано

1. ✅ Установлен Laravel Reverb
2. ✅ Созданы события для трипов и бронирований:
   - `TripCreated` - при создании нового трипа
   - `TripUpdated` - при обновлении трипа
   - `BookingCreated` - при создании бронирования
   - `BookingUpdated` - при обновлении бронирования
3. ✅ Обновлены контроллеры для отправки событий
4. ✅ Настроены каналы broadcasting

## Настройка на Laravel Cloud

### 1. Включите WebSockets в Laravel Cloud

В панели управления Laravel Cloud:
1. Перейдите в настройки вашего приложения
2. Найдите раздел "WebSockets" 
3. Включите WebSockets (это займет ~10 секунд согласно документации)

### 2. Настройте переменные окружения

В Laravel Cloud добавьте следующие переменные в `.env`:

```env
# Broadcasting
BROADCAST_CONNECTION=reverb

# Reverb Configuration (значения будут предоставлены Laravel Cloud)
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=your-reverb-host.laravel.cloud
REVERB_PORT=443
REVERB_SCHEME=https
```

**Важно:** Laravel Cloud автоматически предоставит эти значения после включения WebSockets. Скопируйте их из панели управления.

### 3. Проверьте конфигурацию

Убедитесь, что в `config/broadcasting.php`:
- `default` установлен в `reverb` (или используйте переменную `BROADCAST_CONNECTION`)

## Каналы Broadcasting

### Публичные каналы (доступны всем)
- `trips` - все активные трипы
- `bookings` - все бронирования

### Приватные каналы (требуют авторизации)
- `trip.{tripId}` - канал для конкретного трипа (доступен владельцу и участникам)
- `user.{userId}` - канал для конкретного пользователя

## События

### TripCreated
- **Каналы:** `trips`
- **Событие:** `trip.created`
- **Данные:** Полный объект трипа с загруженными связями (`user.car`, `bookings.user`)

### TripUpdated
- **Каналы:** `trips`, `trip.{tripId}`
- **Событие:** `trip.updated`
- **Данные:** Обновленный объект трипа

### BookingCreated
- **Каналы:** `bookings`, `trip.{tripId}`, `user.{userId}` (для владельца трипа и создателя бронирования)
- **Событие:** `booking.created`
- **Данные:** Полный объект бронирования с загруженными связями

### BookingUpdated
- **Каналы:** `bookings`, `trip.{tripId}`, `user.{userId}`
- **Событие:** `booking.updated`
- **Данные:** Обновленный объект бронирования

## Подключение на фронтенде

### Установка Laravel Echo и Pusher JS

```bash
npm install --save-dev laravel-echo pusher-js
```

### Настройка Laravel Echo

```javascript
// resources/js/bootstrap.js или ваш главный JS файл
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${yourAuthToken}`, // Ваш токен авторизации
        },
    },
});
```

### Переменные окружения для фронтенда (.env)

```env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Пример использования на фронтенде

```javascript
// Подписка на публичный канал (все трипы)
Echo.channel('trips')
    .listen('.trip.created', (e) => {
        console.log('Новый трип создан:', e.trip);
        // Обновите список трипов
        updateTripsList(e.trip);
    })
    .listen('.trip.updated', (e) => {
        console.log('Трип обновлен:', e.trip);
        // Обновите конкретный трип в списке
        updateTripInList(e.trip);
    });

// Подписка на публичный канал (все бронирования)
Echo.channel('bookings')
    .listen('.booking.created', (e) => {
        console.log('Новое бронирование создано:', e.booking);
        // Обновите список бронирований
        updateBookingsList(e.booking);
    })
    .listen('.booking.updated', (e) => {
        console.log('Бронирование обновлено:', e.booking);
        // Обновите конкретное бронирование
        updateBookingInList(e.booking);
    });

// Подписка на приватный канал (конкретный трип)
Echo.private(`trip.${tripId}`)
    .listen('.trip.updated', (e) => {
        console.log('Ваш трип обновлен:', e.trip);
        // Обновите детали трипа
    })
    .listen('.booking.created', (e) => {
        console.log('Новое бронирование для вашего трипа:', e.booking);
    });

// Подписка на приватный канал (личные уведомления пользователя)
Echo.private(`user.${userId}`)
    .listen('.booking.created', (e) => {
        console.log('У вас новое бронирование:', e.booking);
        // Покажите уведомление пользователю
        showNotification('У вас новое бронирование!');
    });
```

### React пример

```jsx
import { useEffect, useState } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

function TripsList() {
    const [trips, setTrips] = useState([]);

    useEffect(() => {
        // Инициализация Echo
        const echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('token')}`,
                },
            },
        });

        // Подписка на канал трипов
        echo.channel('trips')
            .listen('.trip.created', (e) => {
                setTrips(prev => [e.trip, ...prev]);
            })
            .listen('.trip.updated', (e) => {
                setTrips(prev => 
                    prev.map(trip => trip.id === e.trip.id ? e.trip : trip)
                );
            });

        // Загрузка начальных данных
        fetch('/api/trips/active')
            .then(res => res.json())
            .then(data => setTrips(data));

        // Очистка при размонтировании
        return () => {
            echo.leave('trips');
            echo.disconnect();
        };
    }, []);

    return (
        <div>
            {trips.map(trip => (
                <div key={trip.id}>
                    {/* Отображение трипа */}
                </div>
            ))}
        </div>
    );
}
```

## Авторизация приватных каналов

Laravel автоматически обрабатывает авторизацию приватных каналов через маршрут `/broadcasting/auth`. Убедитесь, что:

1. В `routes/channels.php` правильно настроены правила доступа
2. Пользователь авторизован (Sanctum токен в заголовке `Authorization`)

## Тестирование

### Локальное тестирование

1. Запустите Reverb сервер:
```bash
php artisan reverb:start
```

2. Убедитесь, что Redis запущен (если используете scaling)

3. Проверьте подключение через браузерную консоль

### На Laravel Cloud

После настройки переменных окружения, WebSockets должны работать автоматически. Проверьте в консоли браузера, что подключение установлено.

## Полезные ссылки

- [Laravel Reverb Documentation](https://laravel.com/docs/reverb)
- [Laravel Broadcasting Documentation](https://laravel.com/docs/broadcasting)
- [Laravel Echo Documentation](https://laravel.com/docs/broadcasting#client-side-installation)
- [Laravel Cloud WebSockets](https://cloud.laravel.com/docs)

## Troubleshooting

### Проблема: События не отправляются
- Проверьте, что `BROADCAST_CONNECTION=reverb` в `.env`
- Убедитесь, что события реализуют `ShouldBroadcast`
- Проверьте логи Laravel Cloud

### Проблема: Фронтенд не подключается
- Проверьте переменные окружения на фронтенде
- Убедитесь, что WebSockets включены в Laravel Cloud
- Проверьте CORS настройки
- Проверьте токен авторизации

### Проблема: Приватные каналы не работают
- Проверьте маршрут `/broadcasting/auth`
- Убедитесь, что пользователь авторизован
- Проверьте правила в `routes/channels.php`

