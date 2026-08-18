# ERP System — Backend API Documentation

> **Last updated:** August 2026  
> Reflects all implementation changes from the POS & Accounting integrity overhaul (Tasks 1–10):
> accounting_status tracking, void-sale JE fix, multi-unit stock restore fix, entry_date field fix,
> idempotency guard, sale-return / stock-adjustment / manufacturing journal entries,
> fiscal period enforcement, and the full AccountingIntegrationTest suite.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Authentication](#2-authentication)
3. [Response Format & HTTP Codes](#3-response-format--http-codes)
4. [Multi-Tenancy](#4-multi-tenancy)
5. [POS Module](#5-pos-module)
6. [Sales Module](#6-sales-module)
7. [Accounting Module](#7-accounting-module)
8. [Auto-Posting Accounting Flows](#8-auto-posting-accounting-flows)
9. [Inventory Module](#9-inventory-module)
10. [Purchasing Module](#10-purchasing-module)
11. [Manufacturing Module](#11-manufacturing-module)
12. [Credit & Installments Module](#12-credit--installments-module)
13. [HR & Payroll Module](#13-hr--payroll-module)
14. [Reporting Module](#14-reporting-module)
15. [Settings & Configuration](#15-settings--configuration)
16. [Restaurant Module](#16-restaurant-module)
17. [Error Codes](#17-error-codes)
18. [GL Account System Slugs Reference](#18-gl-account-system-slugs-reference)
19. [Running Tests](#19-running-tests)

---

## 1. Architecture Overview

| Item | Detail |
|---|---|
| Framework | Laravel 12 (PHP ^8.2) |
| Auth | Laravel Sanctum (Bearer tokens) |
| Multi-tenancy | `BelongsToTenant` global scope — every query is automatically scoped to `auth()->user()->tenant_id` |
| Soft deletes | All primary models use `SoftDeletes` |
| API versioning | All routes under `/api/v1/` |
| Pattern | Controller → Service → Model. Controllers handle HTTP; Services own business logic and transaction management |
| Accounting | `JournalAutoPostService` posts double-entry journal entries automatically on every financial transaction. All auto-posting is non-fatal (a GL misconfiguration will not block the originating transaction) but sets an `accounting_status` field so failures are visible and retryable |

### Service layer map

```
POSService              → creates sales, handles multi-unit, stock, loyalty, coupons
SaleService             → void sales, receipts
JournalAutoPostService  → all auto-posting (sale, return, GRN, bill, expense, payroll,
                           stock adjustment, manufacturing)
JournalEntryService     → manual journal entry CRUD, post, void/reverse
AccountBalanceService   → balance calculations, fiscal period resolution
ManufacturingService    → BOM-driven production orders
PaymentProcessingService→ split payment calculation helpers (no gateway)
```

---

## 2. Authentication

Base URL: `http://localhost:8000/api/v1`

All endpoints except `POST /auth/login` and `POST /auth/refresh` require:
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### POST `/auth/login`
Rate limited: 6 requests per minute.

**Request:**
```json
{ "email": "user@example.com", "password": "password123" }
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 1, "name": "John Doe", "email": "user@example.com",
      "tenant_id": 1, "branch_id": 2,
      "roles": ["admin"],
      "permissions": ["view-dashboard", "manage-users"]
    }
  }
}
```

### POST `/auth/refresh`
Exchange an expiring/expired token for a new one. No auth middleware required.

### POST `/auth/logout`
Revokes the current token. Requires auth.

### GET `/auth/user`
Returns the authenticated user with tenant, roles, and permissions.

### PUT `/auth/profile`
```json
{ "name": "Jane Doe", "phone": "+1234567890" }
```

### PUT `/auth/password`
```json
{
  "current_password": "old",
  "password": "new123",
  "password_confirmation": "new123"
}
```

### POST `/auth/pin-login` *(no auth required)*
```json
{ "pin": "1234", "cash_register_id": 5 }
```

### POST `/auth/set-pin` | `POST /auth/change-pin`
Set or rotate a 4-digit cashier PIN.

### GET `/audit-logs/logins`
Returns paginated login audit log for the tenant.

---

## 3. Response Format & HTTP Codes

### Success
```json
{ "success": true, "message": "...", "data": { ... } }
```

### Paginated list
```json
{
  "success": true,
  "data": {
    "data": [ ... ],
    "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 56 },
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
  }
}
```

### Error
```json
{ "success": false, "message": "Validation failed.", "errors": { "field": ["..."] } }
```

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 401 | Unauthenticated |
| 403 | Forbidden (policy check failed) |
| 404 | Not found |
| 422 | Validation / business rule failure |
| 500 | Server error |

---

## 4. Multi-Tenancy

- Every model using `BelongsToTenant` automatically filters by the authenticated user's `tenant_id`.
- Controllers never manually filter by tenant — the global scope handles it.
- The `tenant` middleware (applied to all protected routes) verifies the user belongs to an active tenant.
- Cross-tenant data access is architecturally impossible via normal query paths.

---

## 5. POS Module

### Register Sessions

#### GET `/register-sessions/current`
Returns the currently open session for the authenticated user. Returns 404 if none.

**Response `data`:** `{ id, cash_register_id, opening_balance, cash_sales, status, opened_at, cashRegister, openedBy }`

#### GET `/register-sessions`
List all register sessions. Filters: `cash_register_id`, `status`, `search`, `per_page`.

#### POST `/register-sessions`
Open a new register session.
```json
{ "cash_register_id": 3, "opening_cash": 200.00, "notes": "Morning shift" }
```
Returns 422 if the register already has an open session.

#### POST `/register-sessions/{id}/close`
```json
{
  "closing_cash": 415.50,
  "expected_cash": 410.00,
  "difference": 5.50,
  "notes": "Small overage"
}
```

#### POST `/register-sessions/{id}/cash-in`
```json
{ "amount": 50.00, "reason": "Change fund top-up" }
```

#### POST `/register-sessions/{id}/cash-out`
```json
{ "amount": 20.00, "reason": "Petty cash withdrawal" }
```
Returns 422 if the drawer balance is insufficient.

#### GET `/register-sessions/{id}/z-report`
End-of-day summary: total transactions, gross/net sales, discounts, tax, payment method breakdown, and refunds.

---

### Product Lookup

#### GET `/pos/products`
Paginated product list for the POS grid.

| Query param | Type | Description |
|---|---|---|
| `search` | string | Matches name, SKU, barcode |
| `category_id` | integer | Filter by category |
| `ids` | string | Comma-separated product IDs |
| `per_page` | integer | Default 15 |

**Response includes:** `category`, `baseUnit`, `productUnits.unit`, `stockLevels`, `batches` (active, with stock), `serialNumbers` (in stock).

#### GET `/pos/product/{barcode}`
Find a product by barcode. Supports:
- **Standard barcodes** — exact match on `products.barcode` or `product_units.barcode`.
- **Dynamic/scale barcodes** (13-digit, prefix 20–29) — decodes embedded weight or price. Returns `dynamic_quantity` and `scanned_barcode` on the product object.
- Returns 404 if not found.

---

### Create POS Sale

#### POST `/pos/sale`

**Request body:**
```json
{
  "register_session_id": 5,
  "customer_id": 12,
  "type": "walk-in",
  "order_type": "takeaway",
  "restaurant_table_id": null,

  "items": [
    {
      "product_id": 3,
      "quantity": 2,
      "unit_id": 7,
      "unit_price": 45.00,
      "discount_percent": 5,
      "discount_amount": 0,
      "tax_percent": 10,
      "tax_amount": 0,
      "batch_id": null,
      "serial_number_id": null,
      "variant_id": null
    }
  ],

  "total_amount": 94.50,
  "paid_amount": 100.00,
  "change_amount": 5.50,
  "tax_amount": 8.59,
  "discount_amount": 4.50,
  "shipping_cost": 0,

  "payments": [
    { "payment_method_id": 1, "amount": 100.00, "reference": null }
  ],

  "coupon_code": "SAVE10",
  "points_to_redeem": 50,
  "manager_override_by": null,

  "type": "walk-in",
  "notes": "Gift wrap please"
}
```

**Key validation rules (server-side):**
- `total_amount` is re-calculated from items. A >1% discrepancy (min $1.00) rejects the request with a mismatch error.
- `unit_price` per item must be ≥ 80% of the resolved selling price, or a `manager_override_by` user with `admin/manager/super-admin` role must be supplied.
- Stock is checked if `track_inventory = true` and `allow_negative_stock = false`.
- If a fiscal year + accounting period covering today is configured and the period is **closed**, the sale is rejected with a `422` period-closed message.

**Response 201:**
```json
{
  "success": true,
  "data": {
    "id": 101,
    "sale_number": "SL-20260801-0042",
    "status": "completed",
    "accounting_status": "posted",
    "accounting_failure_reason": null,
    "total_amount": 94.50,
    "paid_amount": 100.00,
    "change_amount": 5.50,
    "payment_status": "paid",
    "items": [ ... ],
    "payments": [ ... ]
  }
}
```

**`accounting_status` values:**

| Value | Meaning |
|---|---|
| `pending` | Default — journal posting has not been attempted yet |
| `posted` | Journal entry was created and posted successfully |
| `failed` | Journal posting failed (GL accounts not configured, period closed, etc.). Sale is still completed. `accounting_failure_reason` contains the error message. |

The cashier flow is **never blocked** by accounting failures. A `failed` status means the entry needs manual review or the GL setup needs correction.

---

### Loyalty Points

#### GET `/pos/customers/{customer}/loyalty-points`
Returns the customer's current balance and its monetary redemption value.

```json
{ "points": 250, "monetary_value": 12.50, "tenant_id": 1 }
```

---

## 6. Sales Module

#### GET `/sales`
Paginated list. Filters: `search` (sale_number, customer name), `status`, `customer_id`, `date_from`, `date_to`, `per_page`.

**Response `data` item includes:** `id`, `sale_number`, `sale_date`, `type`, `total_amount`, `paid_amount`, `balance_due`, `payment_status`, `status`, `accounting_status`, `accounting_failure_reason`, `customer`, `items`, `payments`, `branch`, `register_session`, `sold_by`, `voided_at`, `void_reason`.

#### GET `/sales/{id}`
Full sale detail with all relations loaded.

#### POST `/sales/{id}/void`
Void a completed sale. Restores stock (using `base_quantity` for multi-unit items), voids payments, cancels associated credit sale, and posts a reversal journal entry.

```json
{ "reason": "Customer returned all items" }
```

**Response:** Updated sale with `status: voided`.

> **Fix (Task 2 & 3):** The journal entry lookup was fixed to use `reference_type + reference_id` (not the old `SALE-{id}` string). Stock restoration now uses `base_quantity` (physical units), not the selling-unit quantity — so voiding "2 dozen eggs" correctly restores 24 units.

#### GET `/sales/{id}/receipt`
Returns a structured receipt payload (no PDF — use `/sales/{id}/invoice/download` for PDF).

#### GET `/sales/{id}/invoice/download`
Downloads the sale invoice as a PDF.

#### GET `/sales/{id}/invoice/preview`
Streams the invoice PDF inline.

#### GET `/sales/{saleId}/payments`
Lists all payment records against a sale.

#### POST `/sales/{saleId}/payments`
Record a partial/follow-up payment on a sale.

---

### Sale Returns

#### POST `/sale-returns`
Process a product return against a completed sale.

**Request:**
```json
{
  "sale_id": 101,
  "reason": "Defective product",
  "refund_method": "cash",
  "notes": "Customer reported fault within 7 days",
  "items": [
    {
      "sale_item_id": 55,
      "product_id": 3,
      "quantity": 1,
      "unit_price": 45.00,
      "condition": "defective",
      "return_to_stock": false,
      "notes": "Screen cracked on arrival"
    }
  ]
}
```

`refund_method`: `cash` | `bank_transfer` | `credit_account`

**On success:**
- Creates `SaleReturn` + `SaleReturnItem` records.
- If `return_to_stock: true`, restores stock by `base_quantity` with a `SaleReturn` stock movement.
- If batch-managed, restores `quantity_remaining` on the batch.
- Reduces `balance_due` on the original sale.
- If the sale has an active credit sale, reduces its outstanding amount.
- **Posts a reversing journal entry** (Task 6):
  - Debit `sales_revenue` (revenue reversed)
  - Debit `output_vat` (if tax > 0)
  - Credit `petty_cash` / `bank_operating` / `accounts_receivable` depending on `refund_method`

**Response 201:** `{ "success": true, "data": { SaleReturn with items } }`

#### GET `/sale-returns`
Filters: `search`, `status`, `sale_id`, `per_page`.

#### GET `/sale-returns/{id}`
#### PUT `/sale-returns/{id}` *(pending status only)*
#### DELETE `/sale-returns/{id}` *(pending status only)*

---

### Held Sales

#### GET `/held-sales` | `POST /held-sales`
Park and retrieve in-progress orders. A held sale is a draft cart not yet committed as a sale.

#### POST `/held-sales/{id}/retrieve`
Restore a held sale to the POS cart.

---

### Coupons

#### GET `/coupons` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`

#### POST `/coupons/validate`
```json
{ "code": "SAVE10", "total_amount": 200.00, "items": [ ... ] }
```
Returns the coupon details and calculated discount or a validation error.

---

## 7. Accounting Module

### Chart of Accounts

#### GET `/accounts`
Filters: `search` (name, code, description), `type`, `category`, `account_type_id`, `per_page`.

**Response item:** `id`, `code`, `system_slug`, `name`, `description`, `account_type_id`, `parent_id`, `level`, `is_active`, `is_system`, `allow_direct_posting`, `opening_balance`, `current_balance`, `accountType`, `parent`, `children`.

#### GET `/accounts-tree`
Returns accounts as a nested tree (root nodes with `children` loaded recursively).

#### POST `/accounts`
```json
{
  "name": "Trade Receivables",
  "code": "1110",
  "account_type_id": 3,
  "parent_id": 10,
  "description": "Amounts owed by customers",
  "opening_balance": 0,
  "is_active": true,
  "allow_direct_posting": true
}
```
`code` must be unique per tenant.

#### GET `/accounts/{id}` | `PUT /accounts/{id}` | `DELETE /accounts/{id}`
Deletion is blocked if the account has any journal entry lines.

---

### Journal Entries

#### GET `/journal-entries`
Filters: `search` (reference, entry_number, description), `status`, `type`, `date_from`, `date_to`, `fiscal_year_id`, `accounting_period_id`, `per_page`.

**Response item:** `id`, `entry_number`, `entry_date`, `reference`, `reference_type`, `reference_id`, `type`, `description`, `total_debit`, `total_credit`, `status`, `is_auto_generated`, `is_adjusting`, `created_by`, `posted_by`, `voided_by`, `reversal_of_id`, `lines`.

**Status values:** `draft` | `posted` | `reversed` | `voided`

**Type values:** `manual` | `reversal` | `payroll` | `grn` | `sale` | `expense` | `adjustment`

#### POST `/journal-entries`
Create a manual journal entry in `draft` status.

> **Fix (Task 4):** The field is `entry_date` (was previously incorrectly validated as `date`).

```json
{
  "entry_date": "2026-03-15",
  "description": "Reclassify prepaid insurance",
  "reference": "ADJ-2026-001",
  "lines": [
    { "account_id": 15, "debit": 500.00, "credit": 0, "description": "Prepaid expense" },
    { "account_id": 22, "debit": 0, "credit": 500.00, "description": "Insurance expense" }
  ]
}
```

Rules:
- Minimum 2 lines.
- `SUM(debit)` must equal `SUM(credit)` (4 decimal precision).
- `account_id` must exist in `chart_of_accounts`.
- Optional per-line: `branch_id`, `department_id`, `tax_rate_id`, `taxable_amount`, `tax_amount`.

#### GET `/journal-entries/{id}`
Returns entry with `lines.account`, `createdBy`, `postedBy`.

#### PUT `/journal-entries/{id}`
Update is only allowed when `status = draft`. Replaces lines entirely when `lines` is provided.

#### DELETE `/journal-entries/{id}`
Only allowed when `status = draft`.

#### POST `/journal-entries/{id}/post`
Transitions a `draft` entry to `posted`. Checks:
- Entry must be balanced.
- The accounting period for `entry_date` must not be closed.
- If `total_debit > approval_threshold` (default $10,000), `approved_by` and `approved_at` must be set.

#### POST `/journal-entries/{id}/void`
Creates a **reversing** journal entry (swaps all debits/credits) and marks the original as `reversed`.
```json
{ "reason": "Posted to wrong period" }
```
Only entries with `status = posted` can be voided. Reversal entries themselves cannot be voided.

**Idempotency (Task 1):** Calling `postSale()` (or any auto-post method) twice for the same source document returns the existing journal entry without creating a duplicate. The guard checks `reference_type + reference_id` for an existing `posted` or `reversed` entry.

---

### Fiscal Years

#### GET `/fiscal-years` | `POST` | `GET /{id}` | `PUT /{id}`

#### POST `/fiscal-years`
```json
{
  "name": "FY 2026/27",
  "start_date": "2026-07-01",
  "end_date": "2027-06-30",
  "is_active": true
}
```

#### POST `/fiscal-years/{id}/close`
Closes the fiscal year and all its accounting periods.

---

### Bank Accounts

#### GET `/bank-accounts` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`

#### GET `/bank-accounts/{id}/transactions`
Returns all bank transactions linked to this account.

---

### Bank Reconciliation

#### GET `/bank-reconciliations` | `POST` | `GET /{id}` | `PUT /{id}`

#### POST `/bank-reconciliations/{id}/complete`
Marks the reconciliation as completed. Requires all items to be matched.

---

### Budgets

#### GET `/budgets` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`

#### GET `/budgets/fiscal-year/{fiscalYearId}`
All budgets for a fiscal year.

#### GET `/budgets/{id}/vs-actual`
Budget vs actual comparison for each budget line (queries posted journal entry lines).

---

### Tax Rates

#### GET `/tax-rates` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`

---

## 8. Auto-Posting Accounting Flows

Every financial transaction automatically attempts to create a balanced double-entry journal entry. All auto-posting is **non-fatal**: if GL accounts are missing or misconfigured, the originating transaction still succeeds but `accounting_status` is set to `failed` on the Sale (or logged as a warning for other document types).

If a required `system_slug` account is missing, `JournalAutoPostService::findAccount()` falls back to the `suspense` account. If even `suspense` is missing, the post fails and is logged.

### Flow Map

| Trigger | Debit | Credit | Reference prefix |
|---|---|---|---|
| **POS Sale** | `accounts_receivable` (invoice total) | `sales_revenue` (subtotal) + `output_vat` (tax) | `SALE-{sale_number}` |
| **POS Sale with discount** | also `sales_discounts` (discount amount) | — | same |
| **POS Sale COGS** | `cost_of_goods_sold` | `inventory` or `inventory_finished_goods` | same |
| **POS Sale — payment settlement** | `petty_cash` / `bank_operating` / `cash_in_transit` (by payment method slug) | `accounts_receivable` | `RCT-{sale_number}` |
| **Sale Void** | *(reversal of above — debits/credits swapped)* | | auto-created by void flow |
| **Sale Return — cash refund** | `sales_revenue`, `output_vat` | `petty_cash` | `RET-{return_number}` |
| **Sale Return — bank transfer** | `sales_revenue`, `output_vat` | `bank_operating` | `RET-{return_number}` |
| **Sale Return — credit account** | `sales_revenue`, `output_vat` | `accounts_receivable` | `RET-{return_number}` |
| **GRN (goods received)** | `goods_in_transit` | `grni_clearing` | `GRN-{grn_number}` |
| **Purchase Bill** | `grni_clearing`, `input_vat`, `freight_in`, ±`purchase_price_variance` | `accounts_payable` | `BILL-{bill_number}` |
| **Purchase Bill Payment** | `accounts_payable` | `bank_operating` | `PAY-BILL-{bill_number}` |
| **Expense Approval** | expense account (from category or account_id) | `accounts_payable` / `bank_operating` / `petty_cash` | `EXP-{id}` |
| **Payroll Approval** | `salary_expense` (gross) | `salary_payable` (net) + `withholding_tax` | `PAY-{period_id}` |
| **Stock Adjustment — addition** | `inventory` | `inventory_adjustment_gain` | `ADJ-{adjustment_number}` |
| **Stock Adjustment — subtraction** | `inventory_shrinkage` | `inventory` | `ADJ-{adjustment_number}` |
| **Manufacturing Start** | `work_in_progress` (raw material cost) | `inventory` (raw materials) | `WIP-START-{order_number}` |
| **Manufacturing Complete** | `inventory_finished_goods` (WIP cost) | `work_in_progress` | `WIP-COMPLETE-{order_number}` |

### Idempotency

Every auto-post method checks for an existing `posted` or `reversed` journal entry with the same `reference_type + reference_id` before creating a new one. A retry (e.g. after a timeout) returns the existing entry rather than creating a duplicate.

### Fiscal Period Enforcement

If a fiscal year and accounting period covering today are configured for the tenant and that period is **closed**, `POST /pos/sale` returns HTTP 422 with a period-closed message. This check is skipped when no fiscal year is configured (safe default for tenants that haven't set one up yet).

---

## 9. Inventory Module

### Products

#### GET `/products`
Filters: `search`, `category_id`, `brand_id`, `is_active`, `is_sellable`, `per_page`.

#### POST `/products`
Creates a product with base unit, pricing, and optional inventory tracking settings.

#### GET `/products/{id}/stock`
Returns stock levels across all warehouses for this product.

#### POST `/products/{id}/variants`
Add a product variant (size, colour, etc.).

#### PUT `/products/{id}/variants/{variant}` | `DELETE /products/{id}/variants/{variant}`

#### POST `/products/{id}/clone`
Duplicates a product (useful for creating similar SKUs).

#### POST `/products/{id}/generate-variants`
Auto-generates variant combinations from attribute groups.

#### POST `/products/{id}/images`
Upload a product image (multipart/form-data).

#### DELETE `/products/{id}/images/{image}` | `PATCH /products/{id}/images/{image}/primary`

---

### Categories, Brands, Units, Unit Categories

Standard CRUD:
- `GET/POST /categories` | `GET/PUT/DELETE /categories/{id}`
- `GET/POST /brands` | `GET/PUT/DELETE /brands/{id}`
- `GET/POST /units` | `GET/PUT/DELETE /units/{id}`
- `GET/POST /unit-categories` | `GET/PUT/DELETE /unit-categories/{id}`

### Attribute Groups & Values

- `GET/POST /attribute-groups` | `GET/PUT/DELETE /attribute-groups/{id}`
- `GET /attribute-groups/{id}/values`
- `POST /attribute-groups/{id}/values`
- `DELETE /attribute-values/{value}`

---

### Warehouses

#### GET `/warehouses` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`

---

### Stock Adjustments

#### POST `/stock-adjustments`
Create a pending adjustment.
```json
{
  "warehouse_id": 2,
  "adjustment_type": "addition",
  "date": "2026-08-01",
  "reason": "Physical count variance",
  "items": [
    { "product_id": 5, "quantity": 10, "unit_cost": 5.00, "reason": "Surplus found" }
  ]
}
```

#### POST `/stock-adjustments/{id}/approve`
Applies stock level changes, creates stock movement records, and **posts a journal entry** (Task 7):
- Addition: Debit `inventory`, Credit `inventory_adjustment_gain`
- Subtraction: Debit `inventory_shrinkage`, Credit `inventory`
- Total value = `SUM(quantity × unit_cost)`. If zero, no entry is posted.

#### POST `/stock-adjustments/{id}/reject`

---

### Stock Transfers

#### GET `/stock-transfers` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`
#### POST `/stock-transfers/{id}/approve` | `POST /{id}/complete`

---

### Batches

- `GET/POST /batches`
- `GET/PUT /batches/{id}`
- `GET /batches/alerts/expiring` — batches nearing expiry
- `POST /batches/merge` — merge two batches
- `GET /batches/product/{productId}`
- `POST /batches/{id}/transfer` | `/recall` | `/split`

### Serial Numbers

- `GET/POST /serial-numbers`
- `POST /serial-numbers/bulk`
- `GET /serial-numbers/search` | `/product/{productId}`
- `GET/PUT /serial-numbers/{id}`
- `POST /serial-numbers/{id}/mark-defective`

### Lots

- `GET/POST /lots` | `GET/PUT/DELETE /lots/{id}`
- `POST /lots/{id}/approve-qc` | `/reject-qc`
- `GET /lots/{id}/trace`

### Opening Stock

- `GET/POST /opening-stock`
- `GET /opening-stock/{id}`
- `POST /opening-stock/{id}/approve`
- `DELETE /opening-stock/{id}`

---

## 10. Purchasing Module

### Suppliers

#### GET `/suppliers` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`

---

### Purchase Orders

#### GET `/purchase-orders` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`
#### POST `/purchase-orders/{id}/submit`
#### POST `/purchase-orders/{id}/receive`
Receives stock into a warehouse and triggers GRN creation.
#### POST `/purchase-orders/{id}/cancel`

#### GET `/purchase-orders/{id}/download`
Download PO as PDF.

---

### Goods Received Notes (GRN)

#### GET `/goods-received-notes` | `POST` | `GET /{id}`
On creation, automatically posts a journal entry:
- Debit `goods_in_transit`, Credit `grni_clearing`

---

### Purchase Bills

#### GET `/purchase-bills` | `POST` | `GET /{id}`
On creation, automatically posts a journal entry clearing GRNI and recording AP.

#### POST `/purchase-bills/{id}/pay`
Records payment and posts: Debit `accounts_payable`, Credit `bank_operating`.

#### GET `/purchase-bills/{id}/download`
Download bill PDF.

#### GET `/purchase-bills/{billId}/payments` | `POST`
List or record bill payments.

---

### Purchase Returns

#### GET `/purchase-returns` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`
#### POST `/purchase-returns/{id}/approve`

---

## 11. Manufacturing Module

Manufacturing orders drive production from a Bill of Materials (BOM). A BOM is defined as a `ProductBundle` with component items.

### Lifecycle

```
planned → in_progress → completed
                    ↘ cancelled
```

### Endpoints

#### GET `/manufacturing/orders`
Filters: `status`, `product_id`, `warehouse_id`, `per_page`.

#### POST `/manufacturing/orders`
Create from BOM:
```json
{
  "product_id": 10,
  "quantity": 50,
  "warehouse_id": 2,
  "branch_id": 1
}
```
Requires a `ProductBundle` (BOM) to exist for `product_id`. Creates `ManufacturingOrderItem` rows from bundle components.

#### GET `/manufacturing/orders/{id}` | `PUT /{id}` | `DELETE /{id}`

#### POST `/manufacturing/orders/{id}/start`
Transitions `planned → in_progress`. Deducts raw material stock from the warehouse. Sets `quantity_consumed` on each item.

**Accounting (Task 8):** Posts a WIP journal entry:
- Debit `work_in_progress` (total raw material cost)
- Credit `inventory` (raw materials consumed)

#### POST `/manufacturing/orders/{id}/complete`
```json
{ "quantity_produced": 48 }
```
Transitions `in_progress → completed`. Adds finished goods to stock.

**Accounting (Task 8):** Posts a finished-goods journal entry:
- Debit `inventory_finished_goods`
- Credit `work_in_progress`

Both journal entries are **non-fatal** — production proceeds even if GL accounts are not configured.

#### POST `/manufacturing/orders/{id}/cancel`

---

### Product Bundles (BOM)

- `GET /products/{product}/bundle`
- `POST /products/{product}/bundle`
- `PUT /products/{product}/bundle`
- `DELETE /products/{product}/bundle`
- `POST /products/{product}/bundle/preview` — preview BOM explosion cost

---

## 12. Credit & Installments Module

### Credit Customers

#### GET `/credit-customers` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`
#### POST `/credit-customers/{id}/verify`
Verify a customer's creditworthiness.

### Credit Applications

#### GET `/credit-applications` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`
#### POST `/credit-applications/{id}/approve` | `/reject`

---

### Credit Sales

#### GET `/credit-sales`
Filters: `search`, `status` (`active`, `completed`, `cancelled`, `defaulted`), `customer_id`, `per_page`.

#### POST `/credit-sales`
```json
{
  "customer_id": 1,
  "down_payment": 200.00,
  "financed_amount": 800.00,
  "interest_rate": 5.0,
  "installment_count": 12,
  "installment_frequency": "monthly",
  "start_date": "2026-09-01",
  "notes": "Standard terms",
  "items": [
    { "product_id": 1, "quantity": 2, "unit_price": 500.00, "discount_percent": 0, "tax_percent": 0 }
  ]
}
```

#### GET `/credit-sales/{id}` | `PUT /{id}` | `DELETE /{id}`

#### POST `/credit-sales/{id}/payment`
```json
{
  "installment_id": 5,
  "payment_method_id": 1,
  "amount": 100.00,
  "reference": "PAY-001"
}
```

#### GET `/credit-sales/{id}/schedule`
Returns the full installment schedule with due dates, amounts, and payment status.

---

### Installments

#### GET `/installments/overdue` | `/due-today` | `/upcoming`
#### GET `/installments` | `GET /{id}` | `PUT /{id}`
#### POST `/installments/{id}/pay`
```json
{ "payment_method_id": 1, "amount": 100.00, "reference": "CHQ-0042" }
```

---

### Payment Reminders

#### GET `/payment-reminders` | `POST` | `GET /{id}` | `PUT /{id}` | `DELETE /{id}`
#### POST `/payment-reminders/{id}/send`
#### GET `/payment-reminders/pending` | `/overdue`

---

## 13. HR & Payroll Module

### Departments & Positions

- `GET/POST /departments` | `GET/PUT/DELETE /departments/{id}`
- `GET/POST /positions` | `GET/PUT/DELETE /positions/{id}`

---

### Employees

- `GET/POST /employees`
- `GET/PUT/DELETE /employees/{id}`
- `POST /employees/{id}/photo`
- `GET /employees/{id}/payslips`
- `GET /employees/{id}/leave-balance`

---

### Salary Components & Structures

- `GET/POST /salary-components` | `PUT/DELETE /salary-components/{id}`
- `GET /employees/{id}/salary`
- `PUT /employees/{id}/salary`

---

### Attendance

- `GET/POST /attendance`
- `POST /attendance/bulk`
- `GET /attendance/summary`
- `PUT/DELETE /attendance/{id}`

---

### Leave

- `GET/POST /leave-types` | `PUT/DELETE /leave-types/{id}`
- `GET/POST /leave-requests`
- `PUT /leave-requests/{id}/approve` | `/reject`

---

### Employee Loans

- `GET/POST /employee-loans` | `GET/PUT /employee-loans/{id}`

---

### Payroll

#### GET `/payroll-periods` | `POST` | `GET /{id}`
#### GET `/payroll-periods/{id}/summary`
#### POST `/payroll-periods/{id}/run`
Calculates gross earnings, deductions, and net pay for all active employees.
#### POST `/payroll-periods/{id}/approve`
#### POST `/payroll-periods/{id}/post-accounting`
Manually triggers journal posting for an approved period:
- Debit `salary_expense` (gross)
- Credit `salary_payable` (net) + `withholding_tax`

#### GET `/payroll-runs/{id}/payslip`

---

## 14. Reporting Module

All report endpoints require authentication and are scoped to the authenticated tenant.  
Common filters: `start_date`, `end_date` (YYYY-MM-DD), `per_page`.

### Sales Reports

| Endpoint | Description | Extra filters |
|---|---|---|
| `GET /reports/sales/summary` | Totals: count, revenue, discount, tax, paid, due | `start_date`, `end_date` |
| `GET /reports/sales/by-product` | Revenue and qty per product | `limit` (default 50) |
| `GET /reports/sales/by-customer` | Revenue per customer | — |
| `GET /reports/sales/by-branch` | Revenue per branch | — |
| `GET /reports/sales/by-cashier` | Revenue per cashier | — |
| `GET /reports/sales/by-payment-method` | Revenue per payment method | — |
| `GET /reports/sales/by-salesperson` | Commission and revenue per salesperson | — |

### Inventory Reports

| Endpoint | Description |
|---|---|
| `GET /reports/inventory/stock-levels` | Current stock by product/warehouse |
| `GET /reports/inventory/stock-movements` | Movement history with filters |
| `GET /reports/inventory/low-stock` | Products at or below reorder point |
| `GET /reports/inventory/valuation` | Inventory asset value by warehouse |

### Financial Reports

| Endpoint | Description | Key fields returned |
|---|---|---|
| `GET /reports/accounting/trial-balance` | Debits/credits per account for a period | `account_code`, `account_name`, `debit_total`, `credit_total`, `net_balance` |
| `GET /reports/accounting/profit-loss` | Revenue vs expenses for a period | `revenue`, `cogs`, `gross_profit`, `expenses`, `net_profit` |
| `GET /reports/accounting/balance-sheet` | Assets, liabilities, equity at a date | grouped by account type |
| `GET /reports/accounting/cash-flow` | Cash inflows/outflows | `operating`, `investing`, `financing` |
| `GET /reports/accounting/ledger` | General ledger with running balance | `account_id` filter |

### Customer Reports

| Endpoint | Description |
|---|---|
| `GET /reports/customers/balances` | Outstanding balance per customer |
| `GET /reports/customers/ar-aging` | AR aging buckets (0-30, 31-60, 61-90, 90+) |
| `GET /reports/customers/top-customers` | Top customers by revenue |
| `GET /reports/customers/loyalty` | Loyalty points balances |
| `GET /reports/customers/{id}/statement` | Full transaction statement for one customer |

### Purchase Reports

| Endpoint | Description |
|---|---|
| `GET /reports/purchases/summary` | Total purchase spend |
| `GET /reports/purchases/by-supplier` | Spend per supplier |
| `GET /reports/purchases/by-product` | Spend per product |
| `GET /reports/purchases/ap-aging` | AP aging buckets |
| `GET /reports/purchases/supplier-ledger` | Supplier transaction ledger |
| `GET /reports/purchases/price-history` | Purchase price history per product |

### Product Reports

| Endpoint | Description |
|---|---|
| `GET /reports/products/{id}/ledger` | Stock movement ledger for one product |
| `GET /reports/products/margin` | Gross margin per product |
| `GET /reports/products/slow-moving` | Products with low turnover |
| `GET /reports/products/dead-stock` | Zero-movement products |
| `GET /reports/products/stock-aging` | Days of stock on hand |
| `GET /reports/products/expiring-soon` | Batches near expiry |
| `GET /reports/products/expired` | Expired batches still in stock |
| `GET /reports/products/variant-sales` | Sales breakdown by variant |

### Installment Reports

| Endpoint | Description |
|---|---|
| `GET /reports/installments/summary` | Collection totals |
| `GET /reports/installments/overdue` | Overdue amounts with aging |
| `GET /reports/installments/collections` | Payments collected by period |
| `GET /reports/installments/aging` | Installment aging buckets |

### Tax Reports

| Endpoint | Description |
|---|---|
| `GET /reports/tax/collected` | Output VAT collected |
| `GET /reports/tax/paid` | Input VAT paid |
| `GET /reports/tax/summary` | Net VAT position |
| `GET /reports/finance/expenses-summary` | Expenses by category |

### Export

#### GET `/reports/export`
Export any report as CSV or Excel. Params: `report_type`, `start_date`, `end_date`, `format` (`csv`|`xlsx`).

---

## 15. Settings & Configuration

### Tenant Settings

- `GET/POST /tenant-settings` | `GET/PUT/DELETE /tenant-settings/{id}`
- `GET /tenant-settings/group/{group}` — all settings for a group key
- `GET /tenant-settings/key/{key}` — single setting by key
- `PUT /tenant-settings/batch-update` — update multiple settings in one request

### Branch Settings

- `GET /branches/{id}/settings`
- `PUT /branches/{id}/settings`

### Communication Settings

- `GET/PUT /settings/sms` | `POST /settings/sms/test`
- `GET/PUT /settings/email` | `POST /settings/email/test`

### Label Templates

- `GET/POST /settings/label-templates`
- `PUT/DELETE /settings/label-templates/{id}`

### Installment Plan Templates

- `GET/POST /settings/installment-plan-templates`
- `PUT/DELETE /settings/installment-plan-templates/{id}`

---

## 16. Restaurant Module

### Tables

- `GET /restaurant/tables` — all tables
- `POST /restaurant/tables` | `PUT /restaurant/tables/{id}` | `DELETE /restaurant/tables/{id}`
- `GET /restaurant/tables/available`
- `GET /restaurant/tables/area`
- `PUT /restaurant/tables/{id}/status`
- `POST /restaurant/tables/{id}/open` | `/close` | `/transfer`

### Kitchen Operations

- `POST /restaurant/held-sales/{id}/kot` — generate Kitchen Order Ticket
- `GET /restaurant/orders/pending` — pending orders for the kitchen display
- `GET /restaurant/stats/turnover` — table turnover statistics

### Recipes & Toppings

- `GET/POST /recipes` | `GET/PUT/DELETE /recipes/{id}`
- `GET /recipes/{id}/nutrition-info`
- `GET/POST /toppings` | `GET/PUT/DELETE /toppings/{id}`

---

## 17. Error Codes

| Code | Description |
|---|---|
| `VALIDATION_ERROR` | Request validation failed |
| `UNAUTHORIZED` | Authentication required |
| `FORBIDDEN` | Policy check denied access |
| `NOT_FOUND` | Resource not found |
| `SERVER_ERROR` | Internal server error |
| `VOIDED_SALE_RETURN` | Cannot return a voided sale |
| `INSUFFICIENT_STOCK` | Not enough stock to fulfil the request |
| `REGISTER_SESSION_CLOSED` | POS operation requires an open register session |
| `PERIOD_CLOSED` | Sale blocked — the accounting period is closed |
| `TOTAL_MISMATCH` | Submitted total does not match server-calculated total |
| `PRICE_BELOW_FLOOR` | Item price more than 20% below selling price without manager override |
| `INVALID_MANAGER_OVERRIDE` | Override user does not have required role |
| `CREDIT_SALE_HAS_PENDING_INSTALLMENTS` | Cannot delete with pending installments |
| `INSTALLMENT_ALREADY_PAID` | Installment is already fully paid |

---

## 18. GL Account System Slugs Reference

`JournalAutoPostService` resolves accounts by `system_slug`. If a slug is missing, the entry routes to `suspense`. If `suspense` is also missing, the post fails and `accounting_status` is set to `failed` on the Sale.

All slugs must be set on `chart_of_accounts.system_slug` with `is_system = true`.

| Slug | Normal balance | Used for |
|---|---|---|
| `accounts_receivable` | Debit | AR on sales |
| `sales_revenue` | Credit | Revenue recognition |
| `output_vat` | Credit | VAT collected |
| `sales_discounts` | Debit | Discounts given |
| `cost_of_goods_sold` | Debit | COGS on sales; fallback for shrinkage/PPV |
| `inventory` | Debit | Inventory asset; raw materials |
| `inventory_finished_goods` | Debit | Finished goods from manufacturing |
| `petty_cash` | Debit | Cash payment settlements |
| `bank_operating` | Debit | Bank payment settlements, bill payments |
| `cash_in_transit` | Debit | Card/mobile payment settlements |
| `accounts_payable` | Credit | Supplier AP |
| `input_vat` | Debit | VAT paid on purchases |
| `grni_clearing` | Credit | GRN clearing liability |
| `goods_in_transit` | Debit | Goods received awaiting invoice |
| `freight_in` | Debit | Capitalised freight on purchases |
| `purchase_price_variance` | Debit | PPV on bill vs PO cost |
| `salary_expense` | Debit | Gross payroll |
| `salary_payable` | Credit | Net salary owed to employees |
| `withholding_tax` | Credit | PAYE / WHT withheld |
| `work_in_progress` | Debit | WIP during manufacturing |
| `inventory_shrinkage` | Debit | Stock write-down on subtraction adjustments |
| `inventory_adjustment_gain` | Credit | Stock gain on addition adjustments |
| `other_operating_income` | Credit | Fallback for gain and shipping income |
| `suspense` | Debit | Catch-all when a slug is missing |

---

## 19. Running Tests

```bash
# Run all tests
php artisan test

# Run only the accounting integration suite (13 test cases)
php artisan test --filter AccountingIntegrationTest

# Run with coverage (requires Xdebug or PCOV)
php artisan test --coverage
```

### AccountingIntegrationTest coverage

| Test | What it verifies |
|---|---|
| `test_pos_sale_posts_journal_entry_on_success` | Happy-path sale creates a posted JE |
| `test_pos_sale_marks_accounting_failed_when_gl_not_configured` | Sale succeeds (201), `accounting_status=failed` when GL missing |
| `test_void_sale_reverses_journal_entry` | Void finds JE by `reference_type+reference_id`, creates reversal |
| `test_void_sale_restores_base_quantity_not_unit_quantity` | Multi-unit void restores 24 units, not 2 |
| `test_manual_journal_entry_respects_submitted_entry_date` | `entry_date` field is stored correctly (not defaulting to today) |
| `test_duplicate_post_sale_is_idempotent` | Two calls to `postSale()` produce exactly one JE |
| `test_sale_return_with_cash_refund_creates_reversing_journal_entry` | Cash return: debit revenue, credit petty_cash |
| `test_sale_return_with_bank_transfer_credits_bank_account` | Bank return: credit bank_operating |
| `test_sale_return_with_credit_account_credits_accounts_receivable` | Credit return: credit AR |
| `test_stock_adjustment_addition_creates_inventory_gain_entry` | 10 units @ $5 → debit inventory $50, credit gain $50 |
| `test_stock_adjustment_subtraction_creates_shrinkage_entry` | Debit shrinkage, credit inventory |
| `test_manufacturing_start_creates_wip_journal_entry` | WIP debit, inventory credit on production start |
| `test_manufacturing_complete_creates_finished_goods_journal_entry` | Finished-goods debit, WIP credit on completion |
| `test_pos_sale_blocked_when_accounting_period_is_closed` | Closed period → 422 |
| `test_pos_sale_succeeds_when_accounting_period_is_open` | Open period → 201 |
| `test_all_auto_posted_journal_entries_are_balanced` | Every JE has debit == credit |
| `test_sale_api_response_includes_accounting_status` | `accounting_status` present in GET /sales/{id} response |

---

## Rate Limiting

- Login: 6 requests per minute per IP.
- All other authenticated endpoints: standard Laravel rate limiting (configurable in `config/sanctum.php`).

## CORS

Allowed origins (configurable in `config/cors.php`):
- `http://localhost:3000`
- `http://localhost:5173`
- Production domains via `APP_URL`

## Support

- Swagger UI: `GET /api/documentation`
- Redoc: `GET /api-documentation.html`
