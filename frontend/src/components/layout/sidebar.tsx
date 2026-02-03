import { Link, useLocation } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/store/auth'
import {
  LayoutDashboard,
  Package,
  ShoppingCart,
  CreditCard,
  BookOpen,
  BarChart3,
  Users,
  Settings,
  Building2,
  Boxes,
  Tags,
  Warehouse,
  Truck,
  FileText,
  Receipt,
  DollarSign,
  PiggyBank,
  ChevronDown,
  ChevronRight,
} from 'lucide-react'
import { useState } from 'react'

interface NavItem {
  title: string
  href?: string
  icon: React.ElementType
  permission?: string
  children?: NavItem[]
}

const navigation: NavItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
    icon: LayoutDashboard,
  },
  {
    title: 'Inventory',
    icon: Package,
    children: [
      { title: 'Products', href: '/inventory/products', icon: Boxes },
      { title: 'Categories', href: '/inventory/categories', icon: Tags },
      { title: 'Warehouses', href: '/inventory/warehouses', icon: Warehouse },
      { title: 'Suppliers', href: '/inventory/suppliers', icon: Truck },
      { title: 'Purchase Orders', href: '/inventory/purchase-orders', icon: FileText },
      { title: 'Stock Transfers', href: '/inventory/stock-transfers', icon: Package },
      { title: 'Stock Adjustments', href: '/inventory/stock-adjustments', icon: Package },
    ],
  },
  {
    title: 'Point of Sale',
    icon: ShoppingCart,
    children: [
      { title: 'POS Terminal', href: '/pos/terminal', icon: ShoppingCart },
      { title: 'Customers', href: '/pos/customers', icon: Users },
      { title: 'Sales History', href: '/pos/sales', icon: Receipt },
      { title: 'Returns', href: '/pos/returns', icon: FileText },
      { title: 'Cash Registers', href: '/pos/registers', icon: DollarSign },
    ],
  },
  {
    title: 'Installments',
    icon: CreditCard,
    children: [
      { title: 'Credit Sales', href: '/installments/credit-sales', icon: CreditCard },
      { title: 'Payments', href: '/installments/payments', icon: DollarSign },
      { title: 'Overdue', href: '/installments/overdue', icon: FileText },
      { title: 'Credit Customers', href: '/installments/customers', icon: Users },
    ],
  },
  {
    title: 'Accounting',
    icon: BookOpen,
    children: [
      { title: 'Chart of Accounts', href: '/accounting/accounts', icon: BookOpen },
      { title: 'Journal Entries', href: '/accounting/journal-entries', icon: FileText },
      { title: 'Bank Accounts', href: '/accounting/bank-accounts', icon: PiggyBank },
      { title: 'Reconciliation', href: '/accounting/reconciliation', icon: FileText },
      { title: 'Fiscal Years', href: '/accounting/fiscal-years', icon: FileText },
    ],
  },
  {
    title: 'Reports',
    icon: BarChart3,
    children: [
      { title: 'Sales Reports', href: '/reports/sales', icon: BarChart3 },
      { title: 'Inventory Reports', href: '/reports/inventory', icon: Package },
      { title: 'Financial Reports', href: '/reports/financial', icon: DollarSign },
      { title: 'Installment Reports', href: '/reports/installments', icon: CreditCard },
    ],
  },
  {
    title: 'Settings',
    icon: Settings,
    children: [
      { title: 'Users', href: '/settings/users', icon: Users },
      { title: 'Roles', href: '/settings/roles', icon: Users },
      { title: 'Branches', href: '/settings/branches', icon: Building2 },
      { title: 'General', href: '/settings/general', icon: Settings },
    ],
  },
]

interface SidebarProps {
  isCollapsed?: boolean
}

export function Sidebar({ isCollapsed = false }: SidebarProps) {
  const location = useLocation()
  const { user } = useAuthStore()
  const [expandedItems, setExpandedItems] = useState<string[]>(['Inventory', 'Point of Sale'])

  const toggleExpanded = (title: string) => {
    setExpandedItems((prev) =>
      prev.includes(title) ? prev.filter((t) => t !== title) : [...prev, title]
    )
  }

  const isActive = (href?: string) => {
    if (!href) return false
    return location.pathname === href || location.pathname.startsWith(href + '/')
  }

  const isParentActive = (item: NavItem) => {
    if (item.href) return isActive(item.href)
    return item.children?.some((child) => isActive(child.href))
  }

  return (
    <aside
      className={cn(
        'fixed left-0 top-0 z-40 h-screen border-r border-slate-200 bg-slate-900 text-slate-300 transition-all duration-300',
        isCollapsed ? 'w-16' : 'w-64'
      )}
    >
      {/* Logo Area - Professional Dark */}
      <div className="flex h-16 items-center border-b border-slate-800 px-4 bg-slate-900">
        <Link to="/dashboard" className="flex items-center gap-2">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm ring-4 ring-blue-600/10">
            <Building2 className="h-5 w-5" />
          </div>
          {!isCollapsed && (
            <span className="text-lg font-bold tracking-tight text-white">{user?.tenant?.name || 'ERP System'}</span>
          )}
        </Link>
      </div>

      {/* Navigation - Clean Vertical List */}
      <nav className="flex-1 space-y-1 overflow-y-auto p-3">
        {navigation.map((item) => (
          <div key={item.title}>
            {item.children ? (
              <>
                <button
                  onClick={() => toggleExpanded(item.title)}
                  className={cn(
                    'flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold transition-all group',
                    isParentActive(item)
                      ? 'text-white bg-slate-800/50'
                      : 'text-slate-400 hover:text-white hover:bg-slate-800/30'
                  )}
                >
                  <item.icon className={cn(
                    "h-5 w-5 shrink-0 transition-colors",
                    isParentActive(item) ? "text-blue-500" : "text-slate-500 group-hover:text-slate-300"
                  )} />
                  {!isCollapsed && (
                    <>
                      <span className="flex-1 text-left">{item.title}</span>
                      <ChevronDown
                        className={cn(
                          'h-4 w-4 transition-transform opacity-50',
                          expandedItems.includes(item.title) && 'rotate-180'
                        )}
                      />
                    </>
                  )}
                </button>
                {!isCollapsed && expandedItems.includes(item.title) && (
                  <div className="mt-1 space-y-1 ml-4 border-l border-slate-800 pl-4">
                    {item.children.map((child) => (
                      <Link
                        key={child.href}
                        to={child.href!}
                        className={cn(
                          'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-all',
                          isActive(child.href)
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'
                        )}
                      >
                        <child.icon className="h-4 w-4" />
                        <span>{child.title}</span>
                      </Link>
                    ))}
                  </div>
                )}
              </>
            ) : (
              <Link
                to={item.href!}
                className={cn(
                  'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold transition-all group',
                  isActive(item.href)
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/30'
                )}
              >
                <item.icon className={cn(
                  "h-5 w-5 shrink-0 transition-colors",
                  isActive(item.href) ? "text-white" : "text-slate-500 group-hover:text-slate-300"
                )} />
                {!isCollapsed && <span>{item.title}</span>}
              </Link>
            )}
          </div>
        ))}
      </nav>
    </aside>
  )
}
