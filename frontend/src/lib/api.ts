import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    
    // Handle network errors
    if (!error.response) {
      throw new Error('Network error - please check your connection')
    }
    
    // Handle server errors
    if (error.response.status >= 500) {
      throw new Error('Server error - please try again later')
    }
    
    return Promise.reject(error)
  }
)

export default api

// Auth API
export const authApi = {
  login: (data: { email: string; password: string }) => api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
  getUser: () => api.get('/auth/user'),
  updateProfile: (data: FormData) => api.put('/auth/profile', data),
  changePassword: (data: { current_password: string; password: string; password_confirmation: string }) =>
    api.put('/auth/password', data),
}

// Dashboard API
export const dashboardApi = {
  getStats: () => api.get('/dashboard'),
  getSalesChart: (params?: { period?: string }) => api.get('/dashboard/sales-chart', { params }),
  getTopProducts: (params?: { limit?: number }) => api.get('/dashboard/top-products', { params }),
}

// Products API
export const productsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/products', { params }),
  getOne: (id: number) => api.get(`/products/${id}`),
  create: (data: Record<string, unknown>) => api.post('/products', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/products/${id}`, data),
  delete: (id: number) => api.delete(`/products/${id}`),
  getStock: (id: number) => api.get(`/products/${id}/stock`),
}

// Units API
export const unitsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/units', { params }),
  getOne: (id: number) => api.get(`/units/${id}`),
  create: (data: Record<string, unknown>) => api.post('/units', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/units/${id}`, data),
  delete: (id: number) => api.delete(`/units/${id}`),
}

// Categories API
export const categoriesApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/categories', { params }),
  getOne: (id: number) => api.get(`/categories/${id}`),
  create: (data: Record<string, unknown>) => api.post('/categories', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/categories/${id}`, data),
  delete: (id: number) => api.delete(`/categories/${id}`),
}

// Customers API
// export const customersApi = {
//   getAll: (params?: Record<string, unknown>) => api.get('/customers', { params }),
//   getOne: (id: number) => api.get(`/customers/${id}`),
//   create: (data: Record<string, unknown>) => api.post('/customers', data),
//   update: (id: number, data: Record<string, unknown>) => api.put(`/customers/${id}`, data),
//   delete: (id: number) => api.delete(`/customers/${id}`),
//   getTransactions: (id: number) => api.get(`/customers/${id}/transactions`),
// }

// Sales API
// export const salesApi = {
//   getAll: (params?: Record<string, unknown>) => api.get('/sales', { params }),
//   getOne: (id: number) => api.get(`/sales/${id}`),
//   void: (id: number, reason: string) => api.post(`/sales/${id}/void`, { reason }),
//   getReceipt: (id: number) => api.get(`/sales/${id}/receipt`),
// }

// POS API
// export const posApi = {
//   createSale: (data: Record<string, unknown>) => api.post('/pos/sale', data),
//   getProducts: (params?: Record<string, unknown>) => api.get('/pos/products', { params }),
//   findByBarcode: (barcode: string) => api.get(`/pos/product/${barcode}`),
// }

// Credit Sales API
// export const creditSalesApi = {
//   getAll: (params?: Record<string, unknown>) => api.get('/credit-sales', { params }),
//   getOne: (id: number) => api.get(`/credit-sales/${id}`),
//   create: (data: Record<string, unknown>) => api.post('/credit-sales', data),
//   recordPayment: (id: number, data: Record<string, unknown>) => api.post(`/credit-sales/${id}/payment`, data),
//   getSchedule: (id: number) => api.get(`/credit-sales/${id}/schedule`),
// }

// Installments API
// export const installmentsApi = {
//   getOverdue: () => api.get('/installments/overdue'),
//   getDueToday: () => api.get('/installments/due-today'),
//   getUpcoming: () => api.get('/installments/upcoming'),
// }

// Accounts API
export const accountsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/accounts', { params }),
  getTree: () => api.get('/accounts-tree'),
  getOne: (id: number) => api.get(`/accounts/${id}`),
  create: (data: Record<string, unknown>) => api.post('/accounts', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/accounts/${id}`, data),
  delete: (id: number) => api.delete(`/accounts/${id}`),
}

// Journal Entries API
export const journalEntriesApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/journal-entries', { params }),
  getOne: (id: number) => api.get(`/journal-entries/${id}`),
  create: (data: Record<string, unknown>) => api.post('/journal-entries', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/journal-entries/${id}`, data),
  delete: (id: number) => api.delete(`/journal-entries/${id}`),
  post: (id: number) => api.post(`/journal-entries/${id}/post`),
  void: (id: number, reason: string) => api.post(`/journal-entries/${id}/void`, { reason }),
}

// Purchase Orders API
export const purchaseOrdersApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/purchase-orders', { params }),
  getOne: (id: number) => api.get(`/purchase-orders/${id}`),
  create: (data: Record<string, unknown>) => api.post('/purchase-orders', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/purchase-orders/${id}`, data),
  delete: (id: number) => api.delete(`/purchase-orders/${id}`),
  submit: (id: number) => api.post(`/purchase-orders/${id}/submit`),
  receive: (id: number, data: Record<string, unknown>) => api.post(`/purchase-orders/${id}/receive`, data),
  cancel: (id: number) => api.post(`/purchase-orders/${id}/cancel`),
}

// Stock Transfers API
export const stockTransfersApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/stock-transfers', { params }),
  getOne: (id: number) => api.get(`/stock-transfers/${id}`),
  create: (data: Record<string, unknown>) => api.post('/stock-transfers', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/stock-transfers/${id}`, data),
  delete: (id: number) => api.delete(`/stock-transfers/${id}`),
  approve: (id: number) => api.post(`/stock-transfers/${id}/approve`),
  complete: (id: number) => api.post(`/stock-transfers/${id}/complete`),
}

// Stock Adjustments API
export const stockAdjustmentsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/stock-adjustments', { params }),
  getOne: (id: number) => api.get(`/stock-adjustments/${id}`),
  create: (data: Record<string, unknown>) => api.post('/stock-adjustments', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/stock-adjustments/${id}`, data),
  delete: (id: number) => api.delete(`/stock-adjustments/${id}`),
  approve: (id: number) => api.post(`/stock-adjustments/${id}/approve`),
  reject: (id: number, data?: Record<string, unknown>) => api.post(`/stock-adjustments/${id}/reject`, data),
}

// Suppliers API
export const suppliersApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/suppliers', { params }),
  getOne: (id: number) => api.get(`/suppliers/${id}`),
  create: (data: Record<string, unknown>) => api.post('/suppliers', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/suppliers/${id}`, data),
  delete: (id: number) => api.delete(`/suppliers/${id}`),
}

// Warehouses API
export const warehousesApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/warehouses', { params }),
  getOne: (id: number) => api.get(`/warehouses/${id}`),
  create: (data: Record<string, unknown>) => api.post('/warehouses', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/warehouses/${id}`, data),
  delete: (id: number) => api.delete(`/warehouses/${id}`),
}

// Customers API
export const customersApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/customers', { params }),
  getOne: (id: number) => api.get(`/customers/${id}`),
  create: (data: Record<string, unknown>) => api.post('/customers', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/customers/${id}`, data),
  delete: (id: number) => api.delete(`/customers/${id}`),
  getTransactions: (id: number) => api.get(`/customers/${id}/transactions`),
  getCreditHistory: (id: number) => api.get(`/customers/${id}/credit-history`),
}

// Payment Methods API
export const paymentMethodsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/payment-methods', { params }),
  getOne: (id: number) => api.get(`/payment-methods/${id}`),
  create: (data: Record<string, unknown>) => api.post('/payment-methods', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/payment-methods/${id}`, data),
  delete: (id: number) => api.delete(`/payment-methods/${id}`),
}

// Cash Registers API
export const cashRegistersApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/cash-registers', { params }),
  getOne: (id: number) => api.get(`/cash-registers/${id}`),
  create: (data: Record<string, unknown>) => api.post('/cash-registers', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/cash-registers/${id}`, data),
  delete: (id: number) => api.delete(`/cash-registers/${id}`),
}

// Branches API
export const branchesApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/branches', { params }),
  getOne: (id: number) => api.get(`/branches/${id}`),
}

// Register Sessions API
export const registerSessionsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/register-sessions', { params }),
  getOne: (id: number) => api.get(`/register-sessions/${id}`),
  create: (data: Record<string, unknown>) => api.post('/register-sessions', data),
  close: (id: number, data: Record<string, unknown>) => api.post(`/register-sessions/${id}/close`, data),
  current: () => api.get('/register-sessions/current'),
}

// POS API
export const posApi = {
  createSale: (data: Record<string, unknown>) => api.post('/pos/sale', data),
  getProducts: (params?: Record<string, unknown>) => api.get('/pos/products', { params }),
  findByBarcode: (barcode: string) => api.get(`/pos/product/${barcode}`),
}

// Sales API
export const salesApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/sales', { params }),
  getOne: (id: number) => api.get(`/sales/${id}`),
  void: (id: number, reason: string) => api.post(`/sales/${id}/void`, { reason }),
  getReceipt: (id: number) => api.get(`/sales/${id}/receipt`),
}

// Sale Returns API
export const saleReturnsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/sale-returns', { params }),
  getOne: (id: number) => api.get(`/sale-returns/${id}`),
  create: (data: Record<string, unknown>) => api.post('/sale-returns', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/sale-returns/${id}`, data),
  delete: (id: number) => api.delete(`/sale-returns/${id}`),
}

// Held Sales API
export const heldSalesApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/held-sales', { params }),
  getOne: (id: number) => api.get(`/held-sales/${id}`),
  create: (data: Record<string, unknown>) => api.post('/held-sales', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/held-sales/${id}`, data),
  delete: (id: number) => api.delete(`/held-sales/${id}`),
  retrieve: (id: number) => api.post(`/held-sales/${id}/retrieve`),
}

// Credit Customers API
export const creditCustomersApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/credit-customers', { params }),
  getOne: (id: number) => api.get(`/credit-customers/${id}`),
  create: (data: Record<string, unknown>) => api.post('/credit-customers', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/credit-customers/${id}`, data),
  delete: (id: number) => api.delete(`/credit-customers/${id}`),
  verify: (id: number, data: Record<string, unknown>) => api.post(`/credit-customers/${id}/verify`, data),
}

// Credit Sales API
export const creditSalesApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/credit-sales', { params }),
  getOne: (id: number) => api.get(`/credit-sales/${id}`),
  create: (data: Record<string, unknown>) => api.post('/credit-sales', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/credit-sales/${id}`, data),
  delete: (id: number) => api.delete(`/credit-sales/${id}`),
  recordPayment: (id: number, data: Record<string, unknown>) => api.post(`/credit-sales/${id}/payment`, data),
  getSchedule: (id: number) => api.get(`/credit-sales/${id}/schedule`),
}

// Installments API
export const installmentsApi = {
  getOverdue: () => api.get('/installments/overdue'),
  getDueToday: () => api.get('/installments/due-today'),
  getUpcoming: () => api.get('/installments/upcoming'),
  getAll: (params?: Record<string, unknown>) => api.get('/installments', { params }),
  getOne: (id: number) => api.get(`/installments/${id}`),
  update: (id: number, data: Record<string, unknown>) => api.put(`/installments/${id}`, data),
  pay: (id: number, data: Record<string, unknown>) => api.post(`/installments/${id}/pay`, data),
}

// Reports API
export const reportsApi = {
  salesSummary: (params?: Record<string, unknown>) => api.get('/reports/sales/summary', { params }),
  salesByProduct: (params?: Record<string, unknown>) => api.get('/reports/sales/by-product', { params }),
  salesByCustomer: (params?: Record<string, unknown>) => api.get('/reports/sales/by-customer', { params }),
  salesByBranch: (params?: Record<string, unknown>) => api.get('/reports/sales/by-branch', { params }),
  salesByCashier: (params?: Record<string, unknown>) => api.get('/reports/sales/by-cashier', { params }),
  stockLevels: (params?: Record<string, unknown>) => api.get('/reports/inventory/stock-levels', { params }),
  stockMovements: (params?: Record<string, unknown>) => api.get('/reports/inventory/stock-movements', { params }),
  lowStock: () => api.get('/reports/inventory/low-stock'),
  stockValuation: (params?: Record<string, unknown>) => api.get('/reports/inventory/valuation', { params }),
  trialBalance: (params?: Record<string, unknown>) => api.get('/reports/accounting/trial-balance', { params }),
  profitLoss: (params?: Record<string, unknown>) => api.get('/reports/accounting/profit-loss', { params }),
  balanceSheet: (params?: Record<string, unknown>) => api.get('/reports/accounting/balance-sheet', { params }),
  cashFlow: (params?: Record<string, unknown>) => api.get('/reports/accounting/cash-flow', { params }),
  generalLedger: (params?: Record<string, unknown>) => api.get('/reports/accounting/ledger', { params }),
  installmentSummary: (params?: Record<string, unknown>) => api.get('/reports/installments/summary', { params }),
  overdueInstallments: () => api.get('/reports/installments/overdue'),
  collections: (params?: Record<string, unknown>) => api.get('/reports/installments/collections', { params }),
  installmentAging: (params?: Record<string, unknown>) => api.get('/reports/installments/aging', { params }),
}

// Fiscal Years API
export const fiscalYearsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/fiscal-years', { params }),
  getOne: (id: number) => api.get(`/fiscal-years/${id}`),
  create: (data: Record<string, unknown>) => api.post('/fiscal-years', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/fiscal-years/${id}`, data),
  delete: (id: number) => api.delete(`/fiscal-years/${id}`),
  close: (id: number) => api.post(`/fiscal-years/${id}/close`),
}

// Bank Accounts API
export const bankAccountsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/bank-accounts', { params }),
  getOne: (id: number) => api.get(`/bank-accounts/${id}`),
  create: (data: Record<string, unknown>) => api.post('/bank-accounts', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/bank-accounts/${id}`, data),
  delete: (id: number) => api.delete(`/bank-accounts/${id}`),
  getTransactions: (id: number) => api.get(`/bank-accounts/${id}/transactions`),
}

// Bank Reconciliations API
export const bankReconciliationsApi = {
  getAll: (params?: Record<string, unknown>) => api.get('/bank-reconciliations', { params }),
  getOne: (id: number) => api.get(`/bank-reconciliations/${id}`),
  create: (data: Record<string, unknown>) => api.post('/bank-reconciliations', data),
  complete: (id: number) => api.post(`/bank-reconciliations/${id}/complete`),
}
