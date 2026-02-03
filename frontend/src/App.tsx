import { Routes, Route, Navigate } from 'react-router-dom'
import { Toaster } from 'sonner'
import { ProtectedRoute } from '@/components/layout/protected-route'
import { AppLayout } from '@/components/layout/app-layout'
import { LoginPage } from '@/pages/auth/login'
import { DashboardPage } from '@/pages/dashboard'
import { InventoryIndexPage } from '@/pages/inventory/index'
import { InventoryLayout } from '@/pages/inventory/layout'
import { ProductsPage } from '@/pages/inventory/products'
import { CategoriesPage } from '@/pages/inventory/categories'
import { WarehousesPage } from '@/pages/inventory/warehouses'
import { SuppliersPage } from '@/pages/inventory/suppliers'
import { PurchaseOrdersPage } from '@/pages/inventory/purchase-orders'
import { StockTransfersPage } from '@/pages/inventory/stock-transfers'
import { StockAdjustmentsPage } from '@/pages/inventory/stock-adjustments'

// POS imports
import { POSTerminalPage } from '@/pages/pos/terminal'
import { CustomersPage } from '@/pages/pos/customers'
import { SalesPage } from '@/pages/pos/sales'
import { SaleReturnsPage } from '@/pages/pos/returns'
import { CashRegistersPage } from '@/pages/pos/registers'

// Installments imports
import { CreditCustomersPage } from '@/pages/installments/customers'
import { CreditSalesPage } from '@/pages/installments/credit-sales'
import { InstallmentPaymentsPage } from '@/pages/installments/payments'

// Accounting imports
import { ChartOfAccountsPage } from '@/pages/accounting/accounts'
import { JournalEntriesPage } from '@/pages/accounting/journal-entries'
import { BankAccountsPage } from '@/pages/accounting/bank-accounts'
import { BankReconciliationsPage } from '@/pages/accounting/reconciliation'
import { FiscalYearsPage } from '@/pages/accounting/fiscal-years'

// Settings imports
import { UsersPage } from '@/pages/settings/users'
import { RolesPage } from '@/pages/settings/roles'
import { BranchesPage } from '@/pages/settings/branches'

// Reports imports
import { ReportsIndexPage } from '@/pages/reports/index'
import { SalesReportsPage } from '@/pages/reports/sales'
import { InventoryReportsPage } from '@/pages/reports/inventory'

export default function App() {
  return (
    <>
      <Toaster />
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={<LoginPage />} />

        {/* Protected routes */}
        <Route element={<ProtectedRoute />}>
          <Route element={<AppLayout />}>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="/dashboard" element={<DashboardPage />} />

            {/* Inventory routes */}
            <Route path="/inventory" element={<InventoryLayout />}>
              <Route index element={<InventoryIndexPage />} />
              <Route path="products" element={<ProductsPage />} />
              <Route path="categories" element={<CategoriesPage />} />
              <Route path="warehouses" element={<WarehousesPage />} />
              <Route path="suppliers" element={<SuppliersPage />} />
              <Route path="purchase-orders" element={<PurchaseOrdersPage />} />
              <Route path="stock-transfers" element={<StockTransfersPage />} />
              <Route path="stock-adjustments" element={<StockAdjustmentsPage />} />
            </Route>

            {/* POS routes */}
            <Route path="/pos" element={<div className="p-8 text-center">POS module coming soon</div>} />
            <Route path="/pos/terminal" element={<POSTerminalPage />} />
            <Route path="/pos/customers" element={<CustomersPage />} />
            <Route path="/pos/sales" element={<SalesPage />} />
            <Route path="/pos/returns" element={<SaleReturnsPage />} />
            <Route path="/pos/registers" element={<CashRegistersPage />} />

            {/* Installments routes */}
            <Route path="/installments" element={<div className="p-8 text-center">Installments module coming soon</div>} />
            <Route path="/installments/customers" element={<CreditCustomersPage />} />
            <Route path="/installments/credit-sales" element={<CreditSalesPage />} />
            <Route path="/installments/payments" element={<InstallmentPaymentsPage />} />

            {/* Accounting routes */}
            <Route path="/accounting" element={<div className="p-8 text-center">Accounting module coming soon</div>} />
            <Route path="/accounting/accounts" element={<ChartOfAccountsPage />} />
            <Route path="/accounting/journal-entries" element={<JournalEntriesPage />} />
            <Route path="/accounting/bank-accounts" element={<BankAccountsPage />} />
            <Route path="/accounting/reconciliation" element={<BankReconciliationsPage />} />
            <Route path="/accounting/fiscal-years" element={<FiscalYearsPage />} />

            {/* Reports routes */}
            <Route path="/reports" element={<ReportsIndexPage />} />
            <Route path="/reports/sales" element={<SalesReportsPage />} />
            <Route path="/reports/inventory" element={<InventoryReportsPage />} />
            <Route path="/reports/financial" element={<div className="p-8 text-center">Financial Reports coming soon</div>} />
            <Route path="/reports/installments" element={<div className="p-8 text-center">Installment Reports coming soon</div>} />
            <Route path="/reports/activity" element={<div className="p-8 text-center">Activity Reports coming soon</div>} />
            <Route path="/reports/custom" element={<div className="p-8 text-center">Custom Reports coming soon</div>} />

            {/* Settings routes */}
            <Route path="/settings" element={<div className="p-8 text-center">Settings module coming soon</div>} />
            <Route path="/settings/users" element={<UsersPage />} />
            <Route path="/settings/roles" element={<RolesPage />} />
            <Route path="/settings/branches" element={<BranchesPage />} />
            <Route path="/settings/general" element={<div className="p-8 text-center">General Settings coming soon</div>} />
          </Route>
        </Route>

        {/* Catch all */}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </>
  )
}
