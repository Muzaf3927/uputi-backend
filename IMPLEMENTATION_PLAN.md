# 📋 Пошаговый план добавления функциональности "Заявки пассажиров"

## 🎯 Цель
Добавить возможность пассажирам создавать заявки на поездку, которые водители смогут видеть на карте и откликаться на них.

---

## 📝 Шаг 1: Создать миграцию для таблицы `passenger_requests`

**Файл:** `database/migrations/YYYY_MM_DD_HHMMSS_create_passenger_requests_table.php`

**Поля таблицы:**
```php
- id (bigint, primary key)
- user_id (foreign key → users.id, каскадное удаление) - пассажир
- from_city (string) - город отправления
- to_city (string) - город назначения
- from_lat (decimal 10,8) - широта отправления (для карты)
- from_lng (decimal 11,8) - долгота отправления
- to_lat (decimal 10,8) - широта назначения
- to_lng (decimal 11,8) - долгота назначения
- date (date) - дата поездки
- time (time) - время поездки
- seats (tinyInteger, default 1) - сколько мест нужно
- max_price (unsignedInteger, nullable) - максимальная цена, которую готов заплатить
- comment (text, nullable) - комментарий пассажира
- status (enum: 'looking_for_driver', 'driver_selected', 'confirmed', 'cancelled', 'expired') - статус заявки
- driver_id (foreign key → users.id, nullable, onDelete set null) - выбранный водитель
- expires_at (timestamp, nullable) - когда заявка автоматически закроется
- timestamps (created_at, updated_at)
```

**Индексы:**
- `user_id` - для быстрого поиска заявок пользователя
- `status` - для фильтрации активных заявок
- `from_lat, from_lng` - для поиска по координатам
- `date, status` - для фильтрации по дате
- `expires_at` - для автоматического закрытия

---

## 📝 Шаг 2: Создать миграцию для таблицы `driver_offers`

**Файл:** `database/migrations/YYYY_MM_DD_HHMMSS_create_driver_offers_table.php`

**Поля таблицы:**
```php
- id (bigint, primary key)
- passenger_request_id (foreign key → passenger_requests.id, каскадное удаление)
- driver_id (foreign key → users.id, каскадное удаление) - водитель
- offered_price (unsignedInteger, nullable) - цена, которую предлагает водитель
- comment (text, nullable) - комментарий водителя
- status (enum: 'pending', 'accepted', 'declined', 'cancelled') - статус отклика
- timestamps (created_at, updated_at)
```

**Индексы:**
- `passenger_request_id` - для поиска откликов на заявку
- `driver_id` - для поиска откликов водителя
- `status` - для фильтрации

---

## 📝 Шаг 3: Создать модель `PassengerRequest`

**Файл:** `app/Models/PassengerRequest.php`

**Отношения:**
```php
- belongsTo(User::class, 'user_id') - пассажир
- belongsTo(User::class, 'driver_id') - выбранный водитель (nullable)
- hasMany(DriverOffer::class) - отклики водителей
```

**Accessors/Mutators:**
- Преобразование координат
- Форматирование даты/времени если нужно

**Scopes:**
- `active()` - только со статусом 'looking_for_driver'
- `expired()` - просроченные
- `forMap()` - для отображения на карте

---

## 📝 Шаг 4: Создать модель `DriverOffer`

**Файл:** `app/Models/DriverOffer.php`

**Отношения:**
```php
- belongsTo(PassengerRequest::class)
- belongsTo(User::class, 'driver_id') - водитель
```

---

## 📝 Шаг 5: Обновить модель `User`

**Файл:** `app/Models/User.php`

**Добавить отношения:**
```php
// Заявки пассажира
public function passengerRequests()
{
    return $this->hasMany(PassengerRequest::class, 'user_id');
}

// Заявки, где этот пользователь выбран водителем
public function driverPassengerRequests()
{
    return $this->hasMany(PassengerRequest::class, 'driver_id');
}

// Отклики водителя
public function driverOffers()
{
    return $this->hasMany(DriverOffer::class, 'driver_id');
}
```

---

## 📝 Шаг 6: Создать контроллер `PassengerRequestController`

**Файл:** `app/Http/Controllers/PassengerRequestController.php`

**Методы для пассажира:**
1. `store()` - создать заявку
   - Валидация всех полей
   - **Координаты обязательны** (from_lat, from_lng, to_lat, to_lng)
   - Проверка что пассажир не откликается на свою заявку
   - Установка expires_at = date + time
   - Статус: 'looking_for_driver'
   - **Примечание:** Координаты приходят с фронтенда (пользователь выбирает точки на карте)

2. `myRequests()` - мои заявки (GET)
   - Показать все заявки текущего пользователя
   - С откликами водителей

3. `show($id)` - детали заявки (GET)
   - С откликами водителей
   - Проверка доступа (только владелец)

4. `getOffers($id)` - получить отклики на мою заявку (GET)
   - С информацией о водителях (имя, рейтинг, телефон)

5. `acceptOffer($requestId, $offerId)` - принять отклик водителя (POST)
   - Проверка что заявка принадлежит пользователю
   - Обновление статуса отклика на 'accepted'
   - Остальные отклики → 'declined'
   - Обновление заявки: driver_id, status = 'driver_selected'
   - Уведомление водителю

6. `confirm($id)` - подтвердить выбор водителя (POST)
   - Статус заявки → 'confirmed'
   - Уведомление водителю

7. `cancel($id)` - отменить заявку (POST/DELETE)
   - Статус → 'cancelled'
   - Все отклики → 'cancelled'
   - Уведомления откликнувшимся водителям

8. `update($id)` - обновить заявку (PUT/PATCH)
   - Только если статус 'looking_for_driver'
   - Валидация

**Методы для водителя:**
9. `getForMap()` - получить заявки для карты (GET)
   - Фильтры: from_lat, from_lng, radius, date, from_city
   - Только статус 'looking_for_driver'
   - Только не истёкшие (expires_at > now())
   - Расчет расстояния от точки водителя
   - Сортировка по расстоянию/дате

---

## 📝 Шаг 7: Создать контроллер `DriverOfferController`

**Файл:** `app/Http/Controllers/DriverOfferController.php`

**Методы:**
1. `store($requestId)` - откликнуться на заявку (POST)
   - Проверка что водитель не откликается на свою заявку
   - Проверка что заявка в статусе 'looking_for_driver'
   - Проверка что ещё нет отклика от этого водителя (или предыдущий cancelled)
   - Создание отклика со статусом 'pending'
   - Уведомление пассажиру

2. `cancel($offerId)` - отменить свой отклик (POST/DELETE)
   - Статус → 'cancelled'
   - Уведомление пассажиру

3. `myOffers()` - мои отклики (GET)
   - Все отклики текущего водителя
   - С информацией о заявках

---

## 📝 Шаг 8: Добавить API endpoints в `routes/api.php`

**В секции `Route::middleware('auth:sanctum')->group()`:**

```php
// Passenger Requests - Заявки пассажиров
Route::post('/passenger-requests', [PassengerRequestController::class, 'store']);
Route::get('/passenger-requests/my', [PassengerRequestController::class, 'myRequests']);
Route::get('/passenger-requests/{passengerRequest}', [PassengerRequestController::class, 'show']);
Route::get('/passenger-requests/{passengerRequest}/offers', [PassengerRequestController::class, 'getOffers']);
Route::post('/passenger-requests/{passengerRequest}/accept-offer/{driverOffer}', [PassengerRequestController::class, 'acceptOffer']);
Route::post('/passenger-requests/{passengerRequest}/confirm', [PassengerRequestController::class, 'confirm']);
Route::post('/passenger-requests/{passengerRequest}/cancel', [PassengerRequestController::class, 'cancel']);
Route::patch('/passenger-requests/{passengerRequest}', [PassengerRequestController::class, 'update']);

// Driver Offers - Отклики водителей
Route::post('/passenger-requests/{passengerRequest}/offer', [DriverOfferController::class, 'store']);
Route::post('/driver-offers/{driverOffer}/cancel', [DriverOfferController::class, 'cancel']);
Route::get('/driver-offers/my', [DriverOfferController::class, 'myOffers']);

// Map - Для карты (доступно и водителям и пассажирам, но разные данные)
Route::get('/passenger-requests/map', [PassengerRequestController::class, 'getForMap']);

// Водитель видит свои взятые заявки
Route::get('/driver/passenger-requests', [PassengerRequestController::class, 'driverTakenRequests']);
```

---

## 📝 Шаг 9: Реализовать уведомления

**Добавить новые типы уведомлений в NotificationController:**

1. **'passenger_request_offer'** - водитель откликнулся
   - Получатель: пассажир
   - Данные: passenger_request_id, driver_offer_id, driver_name

2. **'passenger_request_accepted'** - пассажир принял отклик
   - Получатель: водитель
   - Данные: passenger_request_id, passenger_name

3. **'passenger_request_confirmed'** - пассажир подтвердил
   - Получатель: водитель
   - Данные: passenger_request_id

4. **'passenger_request_cancelled'** - заявка отменена
   - Получатель: откликнувшиеся водители
   - Данные: passenger_request_id

5. **'driver_offer_cancelled'** - водитель отменил отклик
   - Получатель: пассажир
   - Данные: driver_offer_id

**Интеграция с Telegram:**
- Использовать существующий `SendTelegramNotificationJob`
- Добавить уведомления в Telegram при всех событиях

---

## 📝 Шаг 10: Добавить метод для водителя "Мои взятые заявки"

**В `PassengerRequestController`:**
```php
public function driverTakenRequests()
{
    // Заявки где driver_id = текущий водитель
    // И статус 'driver_selected' или 'confirmed'
    // С информацией о пассажире
}
```

---

## 📝 Шаг 11: Реализовать автоматическое закрытие просроченных заявок

**Вариант 1: Cron Job (рекомендуется)**

**Создать команду:**
```bash
php artisan make:command CloseExpiredPassengerRequests
```

**Файл:** `app/Console/Commands/CloseExpiredPassengerRequests.php`

**Логика:**
- Найти заявки где `expires_at < now()` и `status = 'looking_for_driver'`
- Изменить статус на 'expired'
- Уведомить водителей, которые откликнулись (если нужно)

**Зарегистрировать в `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('passenger-requests:close-expired')
        ->everyFiveMinutes(); // или каждый час
}
```

**Вариант 2: При запросе заявок для карты**
- Фильтровать просроченные заявки в запросе
- Опционально: обновлять статус на лету

---

## 📝 Шаг 12: Интеграция Yandex Maps и фронтенд для создания заявки

### 12.1: Подключение Yandex Maps на фронтенде

**В HTML:**
```html
<script src="https://api-maps.yandex.ru/2.1/?apikey=YOUR_API_KEY&lang=ru_UZ"></script>
```

**Получить API ключ:**
- Регистрация на https://developer.tech.yandex.ru/
- JavaScript API и HTTP Geocoder API (для обратного геокодинга)
- Бесплатно до 25,000 запросов/день

### 12.2: Фронтенд - Компонент карты для создания заявки

**Файл:** `resources/js/components/CreateRequestMap.js`

**Функционал:**
1. Инициализация карты Yandex Maps
2. Получение текущей геолокации пользователя (через WebView API)
3. Выбор точки отправления (клик на карте → маркер)
4. Выбор точки назначения (клик на карте → маркер)
5. Обратный геокодинг (координаты → адрес) для отображения
6. Автоматическое определение города из адреса
7. Отправка координат на бэкенд

**Пример реализации:**
```javascript
class CreateRequestMap {
    constructor(containerId) {
        this.map = null;
        this.fromMarker = null;
        this.toMarker = null;
        this.fromCoords = null;
        this.toCoords = null;
        this.initMap();
    }
    
    initMap() {
        ymaps.ready(() => {
            this.map = new ymaps.Map(containerId, {
                center: [41.3111, 69.2797], // Ташкент по умолчанию
                zoom: 12,
                controls: ['zoomControl', 'fullscreenControl']
            });
            
            // При клике на карту - ставим маркер
            this.map.events.add('click', (e) => {
                const coords = e.get('coords');
                
                if (!this.fromCoords) {
                    this.setFromPoint(coords);
                } else if (!this.toCoords) {
                    this.setToPoint(coords);
                }
            });
            
            // Получение текущей позиции
            this.getUserLocation();
        });
    }
    
    setFromPoint(coords) {
        this.fromCoords = coords;
        
        // Удаляем старый маркер
        if (this.fromMarker) {
            this.map.geoObjects.remove(this.fromMarker);
        }
        
        // Создаем маркер
        this.fromMarker = new ymaps.Placemark(coords, {
            balloonContent: 'Точка отправления'
        }, {
            preset: 'islands#blueCircleDotIcon'
        });
        
        this.map.geoObjects.add(this.fromMarker);
        
        // Обратный геокодинг для получения адреса
        this.reverseGeocode(coords, 'from');
    }
    
    setToPoint(coords) {
        this.toCoords = coords;
        
        if (this.toMarker) {
            this.map.geoObjects.remove(this.toMarker);
        }
        
        this.toMarker = new ymaps.Placemark(coords, {
            balloonContent: 'Точка назначения'
        }, {
            preset: 'islands#redCircleDotIcon'
        });
        
        this.map.geoObjects.add(this.toMarker);
        this.reverseGeocode(coords, 'to');
        
        // Построение маршрута между точками (опционально)
        this.buildRoute();
    }
    
    async reverseGeocode(coords, type) {
        ymaps.geocode(coords).then((res) => {
            const firstGeoObject = res.geoObjects.get(0);
            const address = firstGeoObject.getAddressLine();
            const city = firstGeoObject.getLocalities()[0] || '';
            
            // Обновляем скрытые поля формы
            if (type === 'from') {
                document.getElementById('from_address').value = address;
                document.getElementById('from_city').value = city;
                document.getElementById('from_lat').value = coords[0];
                document.getElementById('from_lng').value = coords[1];
            } else {
                document.getElementById('to_address').value = address;
                document.getElementById('to_city').value = city;
                document.getElementById('to_lat').value = coords[0];
                document.getElementById('to_lng').value = coords[1];
            }
        });
    }
    
    getUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    this.map.setCenter([latitude, longitude]);
                    this.map.setZoom(14);
                },
                (error) => {
                    console.error('Геолокация недоступна', error);
                }
            );
        }
    }
    
    buildRoute() {
        // Опционально: показать маршрут между точками
        if (this.fromCoords && this.toCoords) {
            // Использовать Yandex Router API или просто линию
        }
    }
}
```

### 12.3: HTML форма создания заявки

**Файл:** `resources/views/passenger-requests/create.blade.php` (или аналогичный)

```html
<form id="request-form">
    <!-- Карта для выбора точек -->
    <div id="map-container" style="width: 100%; height: 400px; margin-bottom: 20px;"></div>
    
    <!-- Скрытые поля с координатами (заполняются автоматически) -->
    <input type="hidden" id="from_lat" name="from_lat" required>
    <input type="hidden" id="from_lng" name="from_lng" required>
    <input type="hidden" id="to_lat" name="to_lat" required>
    <input type="hidden" id="to_lng" name="to_lng" required>
    
    <!-- Отображение адресов (только для информации, readonly) -->
    <div class="form-group">
        <label>Откуда:</label>
        <input type="text" id="from_address" readonly>
        <input type="text" id="from_city" name="from_city" readonly>
    </div>
    
    <div class="form-group">
        <label>Куда:</label>
        <input type="text" id="to_address" readonly>
        <input type="text" id="to_city" name="to_city" readonly>
    </div>
    
    <!-- Остальные поля -->
    <div class="form-group">
        <label>Дата поездки:</label>
        <input type="date" name="date" required>
    </div>
    
    <div class="form-group">
        <label>Время:</label>
        <input type="time" name="time" required>
    </div>
    
    <div class="form-group">
        <label>Количество мест:</label>
        <input type="number" name="seats" min="1" value="1" required>
    </div>
    
    <div class="form-group">
        <label>Максимальная цена (сум):</label>
        <input type="number" name="max_price" min="0">
    </div>
    
    <div class="form-group">
        <label>Комментарий:</label>
        <textarea name="comment"></textarea>
    </div>
    
    <button type="submit">Создать заявку</button>
</form>

<script>
    // Инициализация карты после загрузки страницы
    document.addEventListener('DOMContentLoaded', () => {
        const map = new CreateRequestMap('map-container');
        
        // Валидация перед отправкой формы
        document.getElementById('request-form').addEventListener('submit', (e) => {
            if (!map.fromCoords || !map.toCoords) {
                e.preventDefault();
                alert('Выберите точки отправления и назначения на карте');
                return false;
            }
        });
    });
</script>
```

### 12.4: Валидация на бэкенде

**В `PassengerRequestController@store()`:**

```php
$request->validate([
    'from_lat' => 'required|numeric|between:-90,90',
    'from_lng' => 'required|numeric|between:-180,180',
    'to_lat' => 'required|numeric|between:-90,90',
    'to_lng' => 'required|numeric|between:-180,180',
    'from_city' => 'required|string|max:255',
    'to_city' => 'required|string|max:255',
    'date' => 'required|date|after_or_equal:today',
    'time' => 'required',
    'seats' => 'required|integer|min:1',
    'max_price' => 'nullable|integer|min:0',
    'comment' => 'nullable|string|max:1000',
], [
    'from_lat.required' => 'Выберите точку отправления на карте',
    'to_lat.required' => 'Выберите точку назначения на карте',
]);

// Установка expires_at
$expiresAt = Carbon::parse($request->date . ' ' . $request->time);

// Создание заявки
$passengerRequest = PassengerRequest::create([
    'user_id' => Auth::id(),
    'from_lat' => $request->from_lat,
    'from_lng' => $request->from_lng,
    'to_lat' => $request->to_lat,
    'to_lng' => $request->to_lng,
    'from_city' => $request->from_city,
    'to_city' => $request->to_city,
    'date' => $request->date,
    'time' => $request->time,
    'seats' => $request->seats,
    'max_price' => $request->max_price,
    'comment' => $request->comment,
    'status' => 'looking_for_driver',
    'expires_at' => $expiresAt,
]);
```

**Важно:**
- Координаты приходят с фронтенда (пользователь выбрал на карте)
- Города определяются автоматически через обратный геокодинг на фронтенде
- Бэкенд только валидирует и сохраняет данные
- Обратный геокодинг на фронтенде - быстрее и удобнее для пользователя

---

## 📝 Шаг 13: Геолокация и поиск по радиусу на карте водителя

### 13.1: Фронтенд - Карта для водителя (просмотр заявок)

**Файл:** `resources/js/components/DriverRequestsMap.js`

**Функционал:**
1. Инициализация карты
2. Получение текущей позиции водителя
3. Загрузка заявок через API
4. Отображение маркеров на карте
5. Фильтры (дата, радиус)
6. Клик по маркеру → детали заявки

**Пример:**
```javascript
class DriverRequestsMap {
    constructor(containerId) {
        this.map = null;
        this.markers = [];
        this.currentPosition = null;
        this.initMap();
    }
    
    async initMap() {
        ymaps.ready(async () => {
            this.map = new ymaps.Map(containerId, {
                center: [41.3111, 69.2797],
                zoom: 10,
                controls: ['zoomControl', 'fullscreenControl']
            });
            
            // Получаем текущую позицию
            await this.getUserLocation();
            
            // Загружаем заявки
            await this.loadRequests();
        });
    }
    
    async getUserLocation() {
        return new Promise((resolve, reject) => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const { latitude, longitude } = position.coords;
                        this.currentPosition = { lat: latitude, lng: longitude };
                        this.map.setCenter([latitude, longitude]);
                        this.map.setZoom(12);
                        
                        // Отмечаем позицию водителя на карте
                        const driverMarker = new ymaps.Placemark(
                            [latitude, longitude],
                            { balloonContent: 'Ваше местоположение' },
                            { preset: 'islands#greenDotIcon' }
                        );
                        this.map.geoObjects.add(driverMarker);
                        
                        resolve();
                    },
                    (error) => {
                        console.error('Геолокация недоступна', error);
                        resolve(); // Продолжаем без геолокации
                    }
                );
            } else {
                resolve();
            }
        });
    }
    
    async loadRequests(filters = {}) {
        const params = new URLSearchParams();
        
        if (this.currentPosition) {
            params.append('from_lat', this.currentPosition.lat);
            params.append('from_lng', this.currentPosition.lng);
            params.append('radius', filters.radius || 10); // по умолчанию 10 км
        }
        
        if (filters.date) {
            params.append('date', filters.date);
        }
        
        if (filters.from_city) {
            params.append('from_city', filters.from_city);
        }
        
        const response = await fetch(`/api/passenger-requests/map?${params}`, {
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`
            }
        });
        
        const data = await response.json();
        
        // Очищаем старые маркеры
        this.clearMarkers();
        
        // Добавляем новые маркеры
        data.requests.forEach(request => {
            this.addMarker(request);
        });
    }
    
    addMarker(request) {
        const marker = new ymaps.Placemark(
            [request.from_lat, request.from_lng],
            {
                balloonContent: `
                    <div>
                        <h3>${request.from_city} → ${request.to_city}</h3>
                        <p>Дата: ${request.date} в ${request.time}</p>
                        <p>Мест: ${request.seats}</p>
                        <p>Макс. цена: ${request.max_price ? request.max_price + ' сум' : 'не указана'}</p>
                        ${request.distance ? `<p>Расстояние: ${request.distance.toFixed(1)} км</p>` : ''}
                        <button onclick="takeRequest(${request.id})" class="btn-take">Взять заказ</button>
                    </div>
                `
            },
            {
                preset: 'islands#blueDotIcon'
            }
        );
        
        marker.events.add('click', () => {
            this.showRequestDetails(request);
        });
        
        this.map.geoObjects.add(marker);
        this.markers.push({ marker, request });
    }
    
    clearMarkers() {
        this.markers.forEach(({ marker }) => {
            this.map.geoObjects.remove(marker);
        });
        this.markers = [];
    }
}
```

### 13.2: Бэкенд - Поиск по радиусу

**В методе `getForMap()` контроллера:**

```php
public function getForMap(Request $request)
{
    $fromLat = $request->input('from_lat');
    $fromLng = $request->input('from_lng');
    $radius = $request->input('radius', 10); // по умолчанию 10 км
    $date = $request->input('date');
    $fromCity = $request->input('from_city');
    
    $query = PassengerRequest::where('status', 'looking_for_driver')
        ->where('expires_at', '>', now());
    
    // Фильтр по дате
    if ($date) {
        $query->where('date', $date);
    }
    
    // Фильтр по городу
    if ($fromCity) {
        $query->where('from_city', 'like', '%' . $fromCity . '%');
    }
    
    // Фильтр по радиусу (Haversine формула)
    if ($fromLat && $fromLng) {
        $query->selectRaw("
            *,
            (6371 * acos(
                cos(radians(?)) * cos(radians(from_lat)) *
                cos(radians(from_lng) - radians(?)) +
                sin(radians(?)) * sin(radians(from_lat))
            )) AS distance
        ", [$fromLat, $fromLng, $fromLat])
        ->having('distance', '<', $radius)
        ->orderBy('distance');
    } else {
        $query->orderBy('date')->orderBy('time');
    }
    
    $requests = $query->with('user:id,name,rating')
        ->get()
        ->map(function($request) {
            return [
                'id' => $request->id,
                'from_city' => $request->from_city,
                'to_city' => $request->to_city,
                'from_lat' => (float) $request->from_lat,
                'from_lng' => (float) $request->from_lng,
                'to_lat' => (float) $request->to_lat,
                'to_lng' => (float) $request->to_lng,
                'date' => $request->date,
                'time' => $request->time,
                'seats' => $request->seats,
                'max_price' => $request->max_price,
                'distance' => isset($request->distance) ? round($request->distance, 2) : null,
                'passenger' => [
                    'name' => $request->user->name,
                    'rating' => $request->user->rating
                ]
            ];
        });
    
    return response()->json(['requests' => $requests]);
}
```

**Функция расчета расстояния (Helper):**

```php
// app/Helpers/GeoHelper.php
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // радиус Земли в км
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}
```

---

## 📝 Шаг 14: Интеграция с чатом (опционально)

**Вопрос:** Нужен ли чат между пассажиром и водителем ДО того как заявка подтверждена?

**Если да:**
- Расширить `ChatMessage` или создать отдельную таблицу для чата по заявкам
- Или использовать существующий чат, но привязать к `passenger_request_id` вместо `trip_id`

**Если нет:**
- Чат создаётся только после подтверждения
- Можно создать виртуальную Trip из PassengerRequest для совместимости с существующим чатом

---

## 📝 Шаг 15: Тестирование

**Что проверить:**

1. ✅ Создание заявки пассажиром
2. ✅ Отображение на карте с фильтрами
3. ✅ Отклик водителя
4. ✅ Несколько откликов → пассажир выбирает одного
5. ✅ Остальные отклики автоматически declined
6. ✅ Подтверждение пассажиром
7. ✅ Заявка исчезает с карты после выбора водителя
8. ✅ Уведомления работают (в приложении и Telegram)
9. ✅ Отмена заявки (пассажиром и водителем)
10. ✅ Автоматическое закрытие просроченных
11. ✅ Поиск по радиусу на карте
12. ✅ Существующая логика Trip/Booking не сломалась

---

## 📝 Шаг 16: Обновить документацию API

- Добавить новые endpoints в README
- Описать формат запросов/ответов
- Примеры использования

---

## ⚠️ Важные моменты, чтобы не сломать существующую логику:

1. **Не изменять существующие таблицы** - только добавлять новые
2. **Не трогать существующие контроллеры** - создать новые
3. **Существующие routes остаются без изменений**
4. **Trip и Booking работают как раньше**
5. **Чат работает только с Trip** (если не решим расширить)
6. **Уведомления - добавить новые типы, не менять старые**

---

## 📦 Порядок реализации (пошагово):

1. **Шаг 1-2:** Создать миграции → `php artisan migrate`
2. **Шаг 3-5:** Создать модели и добавить отношения
3. **Шаг 6-7:** Создать контроллеры (базовые методы)
4. **Шаг 8:** Добавить routes
5. **Шаг 9:** Реализовать уведомления
6. **Шаг 10:** Метод для водителя
7. **Шаг 11:** Автоматическое закрытие
8. **Шаг 12:** Интеграция Yandex Maps и фронтенд компоненты
   - Подключить Yandex Maps API
   - Создать компонент для создания заявки (выбор точек на карте)
   - Создать компонент для водителя (просмотр заявок на карте)
9. **Шаг 13:** Геолокация и поиск по радиусу
10. **Шаг 14:** Интеграция с чатом (если нужно)
11. **Шаг 15:** Тестирование
12. **Шаг 16:** Документация

---

## 🗺️ Важные детали по картам:

### Yandex Maps для Узбекистана:
- **Почему Yandex Maps:** Лучшая работа в Узбекистане, поддержка кириллицы/латиницы, бесплатный план до 25к запросов/день
- **WebView совместимость:** Yandex Maps отлично работает в WebView на iOS и Android
- **API ключи нужны:**
  - JavaScript API (для карты на фронтенде)
  - HTTP Geocoder API (опционально, для обратного геокодинга на бэкенде)
- **Регистрация:** https://developer.tech.yandex.ru/

### WebView настройки для мобильных приложений:

**iOS (WKWebView):**
```swift
// Нужно разрешить доступ к геолокации
webView.configuration.preferences.javaScriptEnabled = true

// Запрос разрешения на геолокацию
locationManager.requestWhenInUseAuthorization()
```

**Android (WebView):**
```xml
<!-- AndroidManifest.xml -->
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

**JavaScript для проверки геолокации в WebView:**
```javascript
// Проверка доступности геолокации
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        (position) => {
            // Геолокация работает
        },
        (error) => {
            // Обработка ошибки
            // В WebView может потребоваться явное разрешение
        }
    );
} else {
    console.error('Геолокация не поддерживается');
}
```

### Важно для WebView:
- Yandex Maps работает в WebView без дополнительных настроек
- Геолокация требует разрешений в нативном приложении (iOS/Android)
- HTTPS обязателен для геолокации (в production)
- Можно использовать HTTP в development для тестирования

### Работа с координатами:
- **Создание заявки:** Пользователь выбирает точки на карте → координаты автоматически определяются
- **Хранение:** Координаты сохраняются в БД (lat/lng)
- **Города:** Определяются через обратный геокодинг (координаты → адрес → город)
- **Поиск:** Водитель видит заявки на карте, фильтрует по радиусу и дате

---

## ❓ Вопросы для уточнения:

1. **Чат:** Нужен ли чат до подтверждения заявки, или только после?
2. **Рейтинги:** Как оставлять рейтинг после завершения поездки из заявки пассажира? (создавать виртуальную Trip или отдельная логика?)
3. **Завершение:** Когда водитель завершает поездку - завершается ли автоматически PassengerRequest или отдельная логика?

---

**Готов начать реализацию! С какого шага начнём?** 🚀

