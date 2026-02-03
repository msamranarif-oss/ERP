import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Package,
  ShoppingCart,
  CreditCard,
  TrendingUp,
  Users,
  DollarSign,
  PieChart,
  Activity,
  ArrowUpRight,
  ChevronRight
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { dashboardApi } from '@/lib/api'
import { Link } from 'react-router-dom'

export function DashboardPage() {
  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ['dashboardStats'],
    queryFn: () => dashboardApi.getStats(),
  })

  const stats = statsData?.data || {}

  const statCards = [
    {
      title: 'Total Revenue',
      value: `$${stats.total_revenue?.toLocaleString() || '0.00'}`,
      change: '+20.1%',
      trend: 'up',
      icon: DollarSign,
      type: 'primary',
    },
    {
      title: 'Sales',
      value: stats.total_sales?.toLocaleString() || '0',
      change: '+18.1%',
      trend: 'up',
      icon: ShoppingCart,
      type: 'success',
    },
    {
      title: 'Customers',
      value: stats.total_customers?.toLocaleString() || '0',
      change: '+19%',
      trend: 'up',
      icon: Users,
      type: 'purple',
    },
    {
      title: 'Products',
      value: stats.total_products?.toLocaleString() || '0',
      change: '+2%',
      trend: 'up',
      icon: Package,
      type: 'warning',
    },
  ]

  const activities = [
    { icon: ShoppingCart, text: 'New sale completed', time: '2 hours ago', type: 'primary' },
    { icon: Package, text: 'Product added to inventory', time: '5 hours ago', type: 'success' },
    { icon: CreditCard, text: 'Credit payment received', time: '1 day ago', type: 'purple' },
  ]

  const quickActions = [
    { icon: ShoppingCart, label: 'New Sale', href: '/pos/terminal' },
    { icon: Package, label: 'Add Product', href: '/inventory/products' },
    { icon: CreditCard, label: 'New Payment', href: '/installments/payments' },
    { icon: PieChart, label: 'Reports', href: '/reports' },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Dashboard Overview</h1>
          <p className="text-slate-500">Welcome back. Here is what has changed since your last visit.</p>
        </div>
        <div className="flex items-center gap-3">
          <span className="text-sm text-slate-500">Last updated: Just now</span>
          <button className="text-sm font-medium text-blue-600 hover:text-blue-700">Refresh data</button>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map((stat) => (
          <Card key={stat.title} className="border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <CardContent className="p-5">
              <div className="flex items-center justify-between">
                <div className={`stat-icon-bg-enterprise ${stat.type}`}>
                  <stat.icon className="h-5 w-5" />
                </div>
                <div className="flex items-center gap-1 text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">
                  <ArrowUpRight className="h-3 w-3" />
                  {stat.change}
                </div>
              </div>
              <div className="mt-4">
                <p className="text-sm font-medium text-slate-500">{stat.title}</p>
                <p className="text-2xl font-bold text-slate-900 mt-1">{stat.value}</p>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Activity */}
        <div className="lg:col-span-2">
          <Card className="border-slate-200 shadow-sm h-full">
            <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-4">
              <div className="flex items-center gap-2">
                <Activity className="h-4 w-4 text-slate-400" />
                <CardTitle className="text-base font-semibold">System Activity</CardTitle>
              </div>
              <Link to="/reports/activity" className="text-sm font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1">
                View History <ChevronRight className="h-4 w-4" />
              </Link>
            </CardHeader>
            <CardContent className="pt-6">
              <div className="space-y-0">
                {activities.map((activity, index) => (
                  <div key={index} className="activity-item-enterprise">
                    <div className="flex items-start justify-between">
                      <div>
                        <p className="text-sm font-medium text-slate-900">{activity.text}</p>
                        <p className="text-xs text-slate-500 mt-1">{activity.time}</p>
                      </div>
                      <button className="text-xs text-slate-400 hover:text-slate-600">Details</button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Quick Actions - Clean Version */}
        <div>
          <Card className="border-slate-200 shadow-sm h-full">
            <CardHeader className="border-b border-slate-100 pb-4">
              <div className="flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-slate-400" />
                <CardTitle className="text-base font-semibold">Quick Actions</CardTitle>
              </div>
            </CardHeader>
            <CardContent className="pt-6">
              <div className="grid grid-cols-1 gap-3">
                {quickActions.map((action) => (
                  <Link
                    key={action.label}
                    to={action.href}
                    className="flex items-center gap-4 p-3 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-blue-200 transition-all group"
                  >
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-50 text-slate-600 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors">
                      <action.icon className="h-5 w-5" />
                    </div>
                    <span className="text-sm font-semibold text-slate-700 group-hover:text-slate-900">{action.label}</span>
                    <ChevronRight className="h-4 w-4 ml-auto text-slate-300 group-hover:text-blue-400 group-hover:translate-x-1 transition-all" />
                  </Link>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}