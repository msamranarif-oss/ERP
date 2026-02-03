# ERP System

A comprehensive multi-tenant ERP system with inventory, POS, installments, and accounting modules.

## Tech Stack

- **Backend**: Laravel 12.x API
- **Frontend**: React.js with TypeScript and Vite
- **Database**: MySQL/SQLite
- **Styling**: Tailwind CSS with shadcn/ui components
- **State Management**: React Query + Zustand
- **Authentication**: Laravel Sanctum

## Features

- Multi-tenant architecture with shared tables
- Inventory management (products, categories, suppliers, warehouses)
- Point of Sale system
- Installment/Credit management
- Accounting module (chart of accounts, journal entries)
- User management and role-based permissions
- Responsive design for all devices

## Getting Started

### Backend Setup

1. Navigate to the backend directory:
```bash
cd backend
```

2. Install PHP dependencies:
```bash
composer install
```

3. Copy and configure environment file:
```bash
cp .env.example .env
# Edit .env file with your database configuration
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Run database migrations:
```bash
php artisan migrate
```

6. Start the development server:
```bash
php artisan serve
```

### Frontend Setup

1. Navigate to the frontend directory:
```bash
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Start development server:
```bash
npm run dev
```

4. Build for production:
```bash
npm run build
```

## Project Structure

```
erp-system/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/Api/V1/
│   │   ├── Models/
│   │   └── Traits/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/
│       └── api.php
└── frontend/               # React Application
    ├── src/
    │   ├── components/
    │   ├── lib/
    │   ├── pages/
    │   ├── store/
    │   └── App.tsx
    ├── public/
    └── tailwind.config.js
```

## Development

### Backend Development

- All API endpoints are versioned (v1)
- Uses Laravel Sanctum for authentication
- Implements multi-tenancy with global scopes
- Follows RESTful API conventions

### Frontend Development

- Built with React and TypeScript
- Uses Vite for fast development
- Implements shadcn/ui component library
- Responsive design with Tailwind CSS
- Client-side state management with Zustand
- Server state management with React Query

## License

MIT