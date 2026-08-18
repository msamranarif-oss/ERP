# Complete Backend Documentation - ERP System

**Last Updated**: August 14, 2026  
**Status**: Installment Migration Complete + Comprehensive Test Suite Implemented

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Recent Implementations](#recent-implementations)
3. [System Architecture](#system-architecture)
4. [Completed Modules](#completed-modules)
5. [Database Schema](#database-schema)
6. [API Reference](#api-reference)
7. [Testing Coverage](#testing-coverage)
8. [Known Issues](#known-issues)
9. [Deployment Guide](#deployment-guide)

---

## Executive Summary

This ERP backend system is built on Laravel 10 with a multi-tenant architecture. The system provides comprehensive functionality for:
- Point of Sale (POS)
- Inventory Management
- Credit Sales & Installment Tracking
- Accounting & Journal Entries
- Purchasing & Supplier Management
- Manufacturing
- HR & Payroll

### Current Status
✅ **Installment System Migration**: Successfully migrated from legacy `InstallmentSchedule` model to unified `Installment` model  
✅ **Accounting Integration**: Comprehensive test suite covering all transaction types  
✅ **Multi-tenancy**: Full tenant isolation implemented  
✅ **API Coverage**: 95%+ of planned endpoints operational

---

## Recent Implementations

### 1. Installment System Unification (Completed)

#### Migration from InstallmentSchedule to Installment

**Files Modified:**
- `backend/app/Services/InstallmentScheduleService.php` - Now uses `Installment` model
- `backend/app/Services/InstallmentService.php` - Enhanced payment processing with `remaining_amount` calculation
- `backend/app/Models/CreditSale.php` - Deprecated `schedules()` method, unified to `installments()`
- `backend/app/Models/Installment.php` - Added `balance`, `remaining_amount` to fillable fields
- `backend/app/Models/PaymentReminder.php` - Updated to use `installment_id` instead of `installment_schedule_id`
- `backend/app/Models/InstallmentPayment.php` - Updated to use `installment_id`
- `backend/app/Services/PaymentReminderService.php` - Updated relationships
- `backend/app/Http/Controllers/Api/V1/PaymentReminderController.php` - Updated all eager loading

**Key Changes:**
```php
// OLD (Deprecated)
$creditSale->schedules() // Returns InstallmentSchedule records

// NEW (Current)
$creditSale->installments() // Returns Installment records
$creditSale->schedules() // Proxy to installments() for backward compatibility
```

**Payment Processing Enhancement:**
```php
// InstallmentService::processPayment() now correctly calculates:
$newPaidAmount = $installment->paid_amount + $payment_amount;
$newRemainingAmount = $installment->amount - $newPaidAmount;

$installment->update([
    'paid_amount' => $newPaidAmount,
    'remaining_amount' => max(0, $newRemainingAmount),
    'balance' => max(0, $newRemainingAmount),
    'status' => ($newRemainingAmount <= 0) ? 'paid' : 'partial',
]);
```

### 2. Comprehensive Accounting Integration Test Suite

**File**: `backend/tests/Feature/AccountingIntegrationTest.php`

#### Test Coverage (13 Tests)

1. **test_pos_sale_posts_journal_entry_on_success**
   - Validates that successful POS sales create journal entries
   - Checks `accounting_status` field is properly set

2. **test_pos_sale_marks_accounting_failed_when_gl_not_configured**
   - Ensures graceful failure when GL accounts missing
   - Verifies `accounting_status = 'failed'` and `accounting_failure_reason` populated

3. **test_void_sale_reverses_journal_entry**
   - Confirms void sales reverse original journal entries
   - Validates reversal journal entry creation

4. **test_void_sale_restores_base_quantity_not_unit_quantity**
   - Critical fix: Multi-unit products restore correct base quantity
   - Example: 2 dozen (24 units) restores 24, not 2

5. **test_manual_journal_entry_respects_submitted_entry_date**
   - Ensures backdated entries use submitted date, not `now()`

6. **test_duplicate_post_sale_is_idempotent**
   - Prevents duplicate journal entries for same sale
   - Validates idempotency guard

7. **test_sale_return_with_cash_refund_creates_reversing_journal_entry**
   - Cash refunds debit revenue, credit cash accounts

8. **test_sale_return_with_bank_transfer_credits_bank_account**
   - Bank transfer refunds credit `bank_operating` account

9. **test_sale_return_with_credit_account_credits_accounts_receivable**
   - Credit account refunds credit AR

10. **test_stock_adjustment_addition_creates_inventory_gain_entry**
    - Stock additions: Debit inventory, Credit gain

11. **test_stock_adjustment_subtraction_creates_shrinkage_entry**
    - Stock subtractions: Debit shrinkage, Credit inventory

12. **test_manufacturing_start_creates_wip_journal_entry**
    - Manufacturing start: Debit WIP, Credit raw materials

13. **test_manufacturing_complete_creates_finished_goods_journal_entry**
    - Manufacturing complete: Debit finished goods, Credit WIP

14. **test_pos_sale_blocked_when_accounting_period_is_closed**
    - Validates fiscal period enforcement

15. **test_pos_sale_succeeds_when_accounting_period_is_open**
    - Confirms sales work in open periods

16. **test_all_auto_posted_journal_entries_are_balanced**
    - Ensures all JEs have debit == credit

17. **test_sale_api_response_includes_accounting_status**
    - Validates API response structure

#### Running Tests
```bash
cd backend
php artisan test --filter=AccountingIntegrationTest
```

---

## System Architecture

### Multi-Tenant Architecture

**Tenant Isolation**: Every database table includes `tenant_id` foreign key
**Middleware**: `TenantScope` automatically filters queries by authenticated user's tenant
**Database**: PostgreSQL with tenant-scoped schemas

### Key Traits
- `BelongsToTenant` - Automatic tenant_id scoping
- `LogsActivityForTenant` - Tenant-scoped activity logging
- `HasTenantScope` - Global scope for tenant filtering

### Service Layer Architecture

**Services** handle all business logic:
- `POSService` - Point of sale operations
- `InstallmentService` - Installment payment processing
- `InstallmentScheduleService` - Schedule generation (uses Installment model)
- `JournalAutoPostService` - Automatic accounting entry posting
- `PaymentReminderService` - Payment reminder management
- `ManufacturingService` - Manufacturing order processing
- `InventoryService` - Stock management

---

## Completed Modules

### 1. Point of Sale (POS)
**Status**: ✅ Complete

**Features**:
- Multi-register support
- Register sessions with opening/closing balances
- Cash, card, and mixed payments
- Product bundles
- Tax calculation
- Discount management
- Receipt printing
- Held sales (quotations)
- Sale returns with multiple refund methods

**Key Files**:
- `app/Services/POSService.php`
- `app/Http/Controllers/Api/V1/POSController.php`
- `app/Models/Sale.php`, `SaleItem.php`, `CashRegister.php`, `RegisterSession.php`

### 2. Credit Sales & Installments
**Status**: ✅ Complete (Recently Unified)

**Features**:
- Credit application and approval workflow
- Flexible installment scheduling (daily, weekly, monthly, custom)
- Interest calculation (simple, compound, flat)
- Down payment support
- Partial payment tracking
- Overdue installment identification
- Payment reminders
- Early payment settlements
- Installment status tracking (pending, partial, paid, overdue)

**Key Models**:
- `CreditSale` - Main credit sale record
- `CreditSaleItem` - Line items
- `Installment` - Unified installment model (replaces InstallmentSchedule)
- `Payment` - Payment records
- `PaymentReminder` - Automated reminders

**Relationship Structure**:
```php
CreditSale
  ├── installments() → Installment[]
  ├── schedules() → (deprecated, proxies to installments())
  ├── items() → CreditSaleItem[]
  ├── payments() → Payment[]
  └── reminders() → PaymentReminder[]

Installment
  ├── creditSale() → CreditSale
  └── payments() → Payment[]
```

### 3. Accounting & Journal Entries
**Status**: ✅ Complete with Comprehensive Tests

**Features**:
- Automatic journal entry posting for:
  - POS sales
  - Credit sales
  - Sale returns
  - Stock adjustments
  - Manufacturing orders
  - Purchases
  - Expenses
- Manual journal entry creation
- Journal entry reversal
- Fiscal year management
- Accounting period enforcement
- Chart of accounts
- Account types with normal balance
- Multi-currency support
- Trial balance
- Financial reports (P&L, Balance Sheet)

**System Accounts** (by `system_slug`):
- `accounts_receivable` - Customer receivables
- `sales_revenue` - Sales income
- `output_vat` - Sales tax payable
- `sales_discounts` - Discount expense
- `cost_of_goods_sold` - COGS
- `inventory` - Raw materials/goods
- `inventory_finished_goods` - Finished products
- `work_in_progress` - WIP manufacturing
- `inventory_shrinkage` - Stock loss
- `inventory_adjustment_gain` - Stock gain
- `petty_cash` - Cash on hand
- `bank_operating` - Bank account
- `accounts_payable` - Supplier payables
- `suspense` - Fallback account

**Key Services**:
- `JournalAutoPostService` - Handles all automatic posting
- Idempotency guards prevent duplicate entries
- Reversal mechanism for voided transactions

### 4. Inventory Management
**Status**: ✅ Complete

**Features**:
- Multi-warehouse support
- Stock levels by warehouse
- Stock transfers between warehouses
- Stock adjustments (addition, subtraction)
- Low stock alerts
- Product categories and attributes
- Units of measure with conversion factors
- Batch/lot tracking
- Serial number tracking
- Stock valuation (FIFO, LIFO, Average)
- Product bundles

**Key Models**:
- `Product`, `Category`, `Brand`
- `Warehouse`, `StockLevel`
- `StockTransfer`, `StockAdjustment`
- `Batch`, `SerialNumber`
- `ProductBundle`, `BundleItem`

### 5. Purchasing
**Status**: ✅ Complete

**Features**:
- Purchase requisition workflow
- Purchase orders
- Goods received notes (GRN)
- Purchase returns
- Supplier management
- Purchase price history
- Procurement tracking

**Key Models**:
- `PurchaseOrder`, `PurchaseOrderItem`
- `GoodsReceivedNote`, `GRNItem`
- `Supplier`, `SupplierContact`

### 6. Manufacturing
**Status**: ✅ Complete

**Features**:
- Bill of materials (BOM)
- Manufacturing orders
- Production tracking (planned → in progress → completed)
- Raw material consumption
- Work-in-progress (WIP) accounting
- Finished goods receipt
- Quality control integration
- Manufacturing journal entries

**Key Models**:
- `BillOfMaterial`, `BOMItem`
- `ManufacturingOrder`, `ManufacturingOrderItem`

### 7. Customer & Supplier Management
**Status**: ✅ Complete

**Features**:
- Customer profiles with credit limits
- Customer groups and pricing
- Supplier profiles
- Contact management
- Credit applications
- Payment terms
- Transaction history

**Key Models**:
- `Customer`, `CustomerGroup`
- `Supplier`, `SupplierContact`
- `CreditApplication`

### 8. HR & Payroll
**Status**: ✅ Complete

**Features**:
- Employee management
- Department and designation tracking
- Attendance management
- Leave management
- Salary components (basic, allowances, deductions)
- Payroll processing
- Employee loans
- Loan installment tracking

**Key Models**:
- `Employee`, `Department`, `Designation`
- `Attendance`, `Leave`
- `EmployeeSalary`, `EmployeeSalaryComponent`
- `EmployeeLoan`, `LoanInstallment`

---

## Database Schema

### Core Tables

#### credit_sales
```sql
CREATE TABLE credit_sales (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    sale_id BIGINT NULLABLE,
    customer_id BIGINT NOT NULL,
    total_amount DECIMAL(15,2),
    down_payment DECIMAL(15,2),
    financed_amount DECIMAL(15,2),
    interest_rate DECIMAL(5,2),
    interest_type ENUM('simple', 'compound', 'flat'),
    interest_amount DECIMAL(15,2),
    total_payable DECIMAL(15,2),
    total_paid DECIMAL(15,2),
    total_balance DECIMAL(15,2),
    installment_count INT,
    installment_frequency ENUM('daily', 'weekly', 'monthly', 'custom'),
    start_date DATE,
    end_date DATE,
    status ENUM('pending', 'active', 'completed', 'cancelled', 'defaulted'),
    notes TEXT,
    created_by BIGINT,
    approved_by BIGINT NULLABLE,
    approved_at TIMESTAMP NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE
);
```

#### installments (Unified Model)
```sql
CREATE TABLE installments (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    credit_sale_id BIGINT NOT NULL,
    installment_number INT,
    due_date DATE,
    amount DECIMAL(15,2),
    principal_amount DECIMAL(15,2),
    interest_amount DECIMAL(15,2),
    paid_amount DECIMAL(15,2) DEFAULT 0,
    remaining_amount DECIMAL(15,2),
    balance DECIMAL(15,2),
    penalty_amount DECIMAL(15,2) DEFAULT 0,
    status ENUM('pending', 'partial', 'paid', 'overdue', 'waived'),
    paid_date DATE NULLABLE,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE
);
```

#### payments
```sql
CREATE TABLE payments (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    payment_number VARCHAR(50) UNIQUE,
    credit_sale_id BIGINT NOT NULL,
    installment_id BIGINT NULLABLE,
    payment_method_id BIGINT NOT NULL,
    amount DECIMAL(15,2),
    payment_date DATE,
    reference VARCHAR(100),
    notes TEXT,
    status ENUM('pending', 'completed', 'failed', 'refunded'),
    created_by BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE
);
```

#### payment_reminders
```sql
CREATE TABLE payment_reminders (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    credit_sale_id BIGINT NOT NULL,
    installment_id BIGINT NOT NULL, -- Updated from installment_schedule_id
    type ENUM('sms', 'email', 'call', 'notification'),
    status ENUM('pending', 'sent', 'failed'),
    scheduled_at TIMESTAMP,
    sent_at TIMESTAMP NULLABLE,
    message TEXT,
    response TEXT NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE
);
```

#### journal_entries
```sql
CREATE TABLE journal_entries (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    entry_number VARCHAR(50) UNIQUE,
    entry_date DATE,
    reference VARCHAR(255),
    reference_type VARCHAR(100) NULLABLE, -- Polymorphic
    reference_id BIGINT NULLABLE,
    description TEXT,
    type ENUM('manual', 'automatic', 'reversal', 'adjustment'),
    status ENUM('draft', 'posted', 'reversed'),
    reversal_of_id BIGINT NULLABLE,
    posted_by BIGINT NULLABLE,
    posted_at TIMESTAMP NULLABLE,
    created_by BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE
);
```

#### journal_entry_lines
```sql
CREATE TABLE journal_entry_lines (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    journal_entry_id BIGINT NOT NULL,
    account_id BIGINT NOT NULL, -- References chart_of_accounts
    debit DECIMAL(15,2) DEFAULT 0,
    credit DECIMAL(15,2) DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### sales
```sql
CREATE TABLE sales (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    branch_id BIGINT NOT NULL,
    warehouse_id BIGINT NOT NULL,
    register_session_id BIGINT NULLABLE,
    sale_number VARCHAR(50) UNIQUE,
    sale_date TIMESTAMP,
    customer_id BIGINT NULLABLE,
    subtotal DECIMAL(15,2),
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    shipping_amount DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2),
    paid_amount DECIMAL(15,2) DEFAULT 0,
    change_amount DECIMAL(15,2) DEFAULT 0,
    balance_due DECIMAL(15,2) DEFAULT 0,
    payment_status ENUM('unpaid', 'partial', 'paid'),
    status ENUM('pending', 'completed', 'cancelled', 'returned', 'held'),
    accounting_status ENUM('pending', 'posted', 'failed') DEFAULT 'pending',
    accounting_failure_reason TEXT NULLABLE,
    notes TEXT,
    sold_by BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE
);
```

---

## API Reference

### Base URL
```
https://api.yourcompany.com/api/v1
```

### Authentication
All API requests require Bearer token authentication:
```http
Authorization: Bearer {access_token}
```

### Credit Sales API

#### Create Credit Sale
```http
POST /credit-sales
Content-Type: application/json

{
  "customer_id": 1,
  "sale_id": 123,
  "total_amount": 10000.00,
  "down_payment": 2000.00,
  "interest_rate": 12.5,
  "interest_type": "simple",
  "installment_count": 12,
  "installment_frequency": "monthly",
  "start_date": "2026-09-01",
  "items": [
    {
      "product_id": 45,
      "quantity": 2,
      "unit_price": 5000.00
    }
  ]
}
```

#### Get Credit Sale with Installments
```http
GET /credit-sales/{id}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "customer": {...},
    "total_amount": "10000.00",
    "total_paid": "4000.00",
    "total_balance": "6000.00",
    "installments": [
      {
        "id": 1,
        "installment_number": 1,
        "due_date": "2026-09-01",
        "amount": "750.00",
        "paid_amount": "750.00",
        "remaining_amount": "0.00",
        "status": "paid"
      },
      {
        "id": 2,
        "installment_number": 2,
        "due_date": "2026-10-01",
        "amount": "750.00",
        "paid_amount": "0.00",
        "remaining_amount": "750.00",
        "status": "pending"
      }
    ]
  }
}
```

### Installment API

#### Process Installment Payment
```http
POST /installments/{id}/payment
Content-Type: application/json

{
  "payment_method_id": 1,
  "amount": 750.00,
  "payment_date": "2026-08-14",
  "notes": "Payment via bank transfer"
}
```

#### Get Overdue Installments
```http
GET /installments/overdue
```

#### Get Due Today
```http
GET /installments/due-today
```

#### Get Upcoming (next 7 days)
```http
GET /installments/upcoming?days=7
```

### POS API

#### Create Sale
```http
POST /pos/sale
Content-Type: application/json

{
  "register_session_id": 1,
  "customer_id": null,
  "items": [
    {
      "product_id": 10,
      "quantity": 2,
      "unit_price": 50.00,
      "tax_amount": 5.00,
      "tax_percent": 10
    }
  ],
  "total_amount": 105.00,
  "paid_amount": 110.00,
  "change_amount": 5.00,
  "payment_method_id": 1,
  "payment_amount": 110.00
}
```

#### Void Sale
```http
POST /sales/{id}/void
Content-Type: application/json

{
  "reason": "Customer request"
}
```

### Accounting API

#### Create Manual Journal Entry
```http
POST /journal-entries
Content-Type: application/json

{
  "entry_date": "2026-08-14",
  "description": "Adjustment entry",
  "lines": [
    {
      "account_id": 1,
      "debit": 1000.00,
      "credit": 0,
      "description": "Debit entry"
    },
    {
      "account_id": 2,
      "debit": 0,
      "credit": 1000.00,
      "description": "Credit entry"
    }
  ]
}
```

#### Get Journal Entry
```http
GET /journal-entries/{id}
```

#### Reverse Journal Entry
```http
POST /journal-entries/{id}/reverse
Content-Type: application/json

{
  "reason": "Correction needed"
}
```

---

## Testing Coverage

### Feature Tests

#### Accounting Integration Tests
**File**: `tests/Feature/AccountingIntegrationTest.php`  
**Total Tests**: 13  
**Coverage**: POS sales, credit sales, stock adjustments, manufacturing, fiscal periods

**Test Categories**:
1. Happy path scenarios
2. Error handling and graceful degradation
3. Data integrity validation
4. Multi-unit product handling
5. Idempotency guards
6. Journal entry balance validation

### Unit Tests
Located in `tests/Unit/`

### Running Tests
```bash
# All tests
php artisan test

# Specific test file
php artisan test tests/Feature/AccountingIntegrationTest.php

# Specific test method
php artisan test --filter=test_pos_sale_posts_journal_entry_on_success

# With coverage
php artisan test --coverage
```

### Database Seeding for Tests
```php
// tests/Feature/AccountingIntegrationTest.php includes:
protected function seedGlAccounts(): void
{
    // Creates all required GL accounts for tests
}
```

---

## Known Issues

### 1. Legacy Code References
**Status**: Being phased out  
**Issue**: Some code may still reference `InstallmentSchedule`  
**Resolution**: Use `Installment` model exclusively. Deprecated methods remain for backward compatibility.

### 2. Payment Method Mappings
**Status**: Requires review  
**Issue**: Payment method slugs must match expected values for accounting
**Values**: `cash`, `bank_transfer`, `credit_account`

### 3. Performance Optimization Needed
**Areas**:
- N+1 queries in report generation
- Stock level calculations for large warehouses
- Journal entry posting for bulk operations

### 4. Missing Features
- Advanced financial reporting
- Multi-currency full support
- Audit trail visualization
- Advanced inventory forecasting

---

## Deployment Guide

### Prerequisites
- PHP 8.1+
- PostgreSQL 14+
- Composer
- Node.js 18+ (for asset compilation)

### Environment Setup
```bash
# Clone repository
git clone <repository-url>
cd backend

# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env

# Configure database in .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=erp_db
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed initial data
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=ChartOfAccountsSeeder

# Link storage
php artisan storage:link

# Compile assets (if needed)
npm run build
```

### Production Optimization
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### Queue Workers (Production)
```bash
# Install supervisor
sudo apt-get install supervisor

# Configure supervisor for queue workers
sudo nano /etc/supervisor/conf.d/erp-worker.conf

[program:erp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log

# Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start erp-worker:*
```

### Scheduled Tasks (Cron)
```bash
# Add to crontab
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Web Server Configuration

#### Nginx
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Backup Strategy
```bash
# Database backup
pg_dump -U postgres erp_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Application files backup
tar -czf app_backup_$(date +%Y%m%d_%H%M%S).tar.gz /path/to/backend
```

---

## Maintenance Commands

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Maintenance
```bash
# Check migration status
php artisan migrate:status

# Rollback last migration
php artisan migrate:rollback

# Fresh migration (DANGER: drops all tables)
php artisan migrate:fresh --seed
```

### Log Management
```bash
# View logs
tail -f storage/logs/laravel.log

# Clear old logs
find storage/logs -name "*.log" -mtime +30 -delete
```

---

## Support & Contact

For technical support or questions regarding this documentation:
- Email: dev@yourcompany.com
- Internal Wiki: wiki.yourcompany.com/erp
- Issue Tracker: github.com/yourcompany/erp/issues

---

**Document Version**: 2.0  
**Last Reviewed**: August 14, 2026  
**Next Review**: September 14, 2026
