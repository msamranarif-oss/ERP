# Environment Variables Validation
This file validates that all required environment variables are present.

## Required Variables

### Backend (.env)
- `APP_KEY` - Laravel application key (required for production)
- `DB_CONNECTION` - Database connection type
- `DB_HOST` - Database host
- `DB_PORT` - Database port
- `DB_DATABASE` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password

### Frontend (.env)
- `VITE_API_URL` - Backend API URL
- `VITE_APP_NAME` - Application name

## Optional Variables

### Backend
- `REDIS_HOST` - Redis host (for caching/queue)
- `MAIL_HOST` - Mail server host
- `MAIL_PORT` - Mail server port
- `PUSHER_APP_ID` - Pusher app ID (for real-time features)

### Frontend
- `VITE_APP_VERSION` - Application version
- `VITE_SENTRY_DSN` - Sentry DSN (for error tracking)

## Validation Script

```bash
# Backend validation
php artisan env:validate

# Frontend validation
npm run validate-env
```