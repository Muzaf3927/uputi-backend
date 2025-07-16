# BlaBlaCar MVP API

Ride-sharing application API built with Laravel 12 and Laravel Sanctum.

## Features

- 🔐 **Authentication**: Registration, login, password reset
- 🚗 **Trips Management**: Create, update, delete trips
- 📅 **Booking System**: Request and manage bookings
- 💬 **Chat System**: Real-time messaging between users
- ⭐ **Rating System**: User ratings and reviews
- 💰 **Wallet System**: Balance management and transactions
- 🔔 **Notifications**: Real-time notifications
- ⚙️ **Settings**: User preferences management

## Tech Stack

- **Backend**: Laravel 12
- **Authentication**: Laravel Sanctum
- **Database**: MySQL/PostgreSQL
- **API**: RESTful API

## Installation

1. Clone the repository
```bash
git clone <repository-url>
cd blablacar-mvp
```

2. Install dependencies
```bash
composer install
npm install
```

3. Environment setup
```bash
cp .env.example .env
php artisan key:generate
```

4. Database setup
```bash
php artisan migrate
php artisan db:seed
```

5. Start development server
```bash
php artisan serve
npm run dev
```

## API Endpoints

### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/reset-password` - Password reset
- `POST /api/logout` - User logout

### User Management
- `GET /api/user` - Get current user
- `PATCH /api/user` - Update user profile
- `PATCH /api/user/password` - Change password

### Trips
- `POST /api/trip` - Create new trip
- `GET /api/trips` - Get all active trips
- `GET /api/my-trips` - Get user's trips
- `PATCH /api/trips/{trip}` - Update trip
- `DELETE /api/trips/{trip}` - Delete trip

### Bookings
- `POST /api/trips/{trip}/booking` - Request booking
- `GET /api/bookings` - Get user's bookings
- `GET /api/trips/{trip}/bookings` - Get trip bookings
- `PATCH /api/bookings/{booking}` - Update booking status
- `PATCH /api/bookings/{booking}/cancel` - Cancel booking

### Chat
- `POST /api/chats/{trip}/send` - Send message
- `GET /api/chats/{trip}/with/{receiver}` - Get chat messages
- `GET /api/chats` - Get user's chats
- `GET /api/chats/unread-count` - Get unread count

### Wallet
- `GET /api/wallet` - Get wallet balance
- `POST /api/wallet/deposit` - Deposit funds
- `GET /api/wallet/transactions` - Get transaction history

### Ratings
- `POST /api/ratings/{trip}/to/{toUser}` - Rate user
- `GET /api/ratings/user/{user}` - Get user ratings
- `GET /api/ratings/given` - Get given ratings

### Notifications
- `GET /api/notifications` - Get notifications
- `PATCH /api/notifications/{id}/read` - Mark as read
- `PATCH /api/notifications/read-all` - Mark all as read

### Settings
- `GET /api/settings` - Get settings
- `GET /api/settings/{key}` - Get specific setting
- `POST /api/settings` - Create/update setting
- `DELETE /api/settings/{key}` - Delete setting

## Authentication

All protected endpoints require Bearer token authentication:

```
Authorization: Bearer {your-token}
```

## Development

```bash
# Run tests
php artisan test

# Run with queue worker
php artisan queue:work

# Run with log monitoring
php artisan pail
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
