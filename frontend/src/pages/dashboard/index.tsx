import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/store/auth'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { TrendingUp, Package, ShoppingCart, CreditCard, BookOpen, Users, DollarSign, Activity } from 'lucide-react'
import { cn } from '@/lib/utils'

export function DashboardPage() {
  const { user } = useAuthStore()
  const navigate = useNavigate()

  const quickActions = [
    { title: 'New Sale', icon: ShoppingCart, href: '/pos/terminal', color: 'bg-gradient-to-br from-blue-500 to-blue-600', hover: 'hover:from-blue-600 hover:to-blue-700' },
    { title: 'Add Product', icon: Package, href: '/inventory/products/new', color: 'bg-gradient-to-br from-emerald-500 to-emerald-600', hover: 'hover:from-emerald-600 hover:to-emerald-700' },
    { title: 'New Purchase', icon: Package, href: '/inventory/purchase-orders/new', color: 'bg-gradient-to-br from-purple-500 to-purple-600', hover: 'hover:from-purple-600 hover:to-purple-700' },
    { title: 'New Credit Sale', icon: CreditCard, href: '/installments/credit-sales/new', color: 'bg-gradient-to-br from-orange-500 to-orange-600', hover: 'hover:from-orange-600 hover:to-orange-700' },
    { title: 'Record Payment', icon: CreditCard, href: '/installments/payments', color: 'bg-gradient-to-br from-teal-500 to-teal-600', hover: 'hover:from-teal-600 hover:to-teal-700' },
    { title: 'Journal Entry', icon: BookOpen, href: '/accounting/journal-entries/new', color: 'bg-gradient-to-br from-indigo-500 to-indigo-600', hover: 'hover:from-indigo-600 hover:to-indigo-700' },
  ]

  const stats = [
    { 
      title: 'Today\'s Sales', 
      value: '$12,450.00', 
      change: '+12.5%', 
      icon: DollarSign, 
      color: 'text-emerald-600',
      bg: 'bg-emerald-50'
    },
    { 
      title: 'Pending Orders', 
      value: '24', 
      change: '-3', 
      icon: Package, 
      color: 'text-amber-600',
      bg: 'bg-amber-50'
    },
    { 
      title: 'Overdue Payments', 
      value: '8', 
      change: '+2', 
      icon: CreditCard, 
      color: 'text-rose-600',
      bg: 'bg-rose-50'
    },
    { 
      title: 'Active Customers', 
      value: '1,245', 
      change: '+15', 
      icon: Users, 
      color: 'text-blue-600',
      bg: 'bg-blue-50'
    },
  ]

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="space-y-2">
        <h1 className="text-3xl font-bold text-foreground">Dashboard</h1>
        <p className="text-muted-foreground text-lg">
          Welcome back, {user?.name}. Here's what's happening with your business today.
        </p>
      </div>

      {/* Stats Cards */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, index) => (
          <Card 
            key={stat.title} 
            className="hover:shadow-lg transition-all duration-300 border-0 shadow-sm hover:-translate-y-1"
          >
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
              <CardTitle className="text-base font-semibold text-muted-foreground">{stat.title}</CardTitle>
              <div className={cn('p-3 rounded-lg', stat.bg)}>
                <stat.icon className={cn('h-5 w-5', stat.color)} />
              </div>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-foreground mb-1">{stat.value}</div>
              <p className={cn('text-sm font-medium', stat.change.startsWith('+') ? 'text-emerald-600' : 'text-rose-600')}>
                {stat.change} from yesterday
              </p>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Quick Actions */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-bold text-foreground">Quick Actions</h2>
          <p className="text-muted-foreground">Common tasks and operations</p>
        </div>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {quickActions.map((action, index) => (
            <Card
              key={action.title}
              className="group cursor-pointer transition-all duration-300 hover:shadow-xl border-0 shadow-sm hover:-translate-y-1 overflow-hidden"
              onClick={() => navigate(action.href)}
            >
              <CardContent className="p-0">
                <div className={cn(
                  'p-6 flex items-center gap-4 transition-all duration-300',
                  action.color,
                  action.hover,
                  'group-hover:brightness-110'
                )}>
                  <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                    <action.icon className="h-7 w-7 text-white" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-white text-lg">{action.title}</h3>
                    <p className="text-white/80 text-sm">Start new transaction</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>

      {/* Recent Activity */}
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Recent Sales */}
        <Card className="hover:shadow-lg transition-all duration-300 border-0 shadow-sm">
          <CardHeader className="border-b border-border/50 pb-4">
            <CardTitle className="flex items-center gap-2 text-xl">
              <ShoppingCart className="h-5 w-5 text-primary" />
              Recent Sales
            </CardTitle>
          </CardHeader>
          <CardContent className="p-6">
            <div className="space-y-4">
              {[1, 2, 3, 4, 5].map((i) => (
                <div 
                  key={i} 
                  className="flex items-center justify-between p-4 rounded-lg bg-muted/30 hover:bg-muted/50 transition-colors group"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <ShoppingCart className="h-5 w-5" />
                    </div>
                    <div>
                      <p className="font-medium text-foreground group-hover:text-primary transition-colors">
                        Sale #{1000 + i}
                      </p>
                      <p className="text-sm text-muted-foreground">2 hours ago</p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="font-semibold text-foreground">${(200 + i * 45.5).toFixed(2)}</p>
                    <p className="text-sm text-emerald-600 font-medium">Completed</p>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Recent Activities */}
        <Card className="hover:shadow-lg transition-all duration-300 border-0 shadow-sm">
          <CardHeader className="border-b border-border/50 pb-4">
            <CardTitle className="flex items-center gap-2 text-xl">
              <Activity className="h-5 w-5 text-primary" />
              Recent Activities
            </CardTitle>
          </CardHeader>
          <CardContent className="p-6">
            <div className="space-y-5">
              {[
                { 
                  title: 'New product added: iPhone 15 Pro', 
                  time: '5 minutes ago', 
                  color: 'bg-blue-500', 
                  icon: Package 
                },
                { 
                  title: 'Purchase order #PO-001 received', 
                  time: '1 hour ago', 
                  color: 'bg-emerald-500', 
                  icon: Package 
                },
                { 
                  title: 'Low stock alert: Office Chair', 
                  time: '2 hours ago', 
                  color: 'bg-amber-500', 
                  icon: Package 
                },
                { 
                  title: 'New customer registered', 
                  time: '3 hours ago', 
                  color: 'bg-purple-500', 
                  icon: Users 
                },
                { 
                  title: 'Payment received: $1,200.00', 
                  time: '4 hours ago', 
                  color: 'bg-green-500', 
                  icon: DollarSign 
                }
              ].map((activity, index) => (
                <div key={index} className="flex items-start gap-4 group">
                  <div className={cn('mt-1 flex h-3 w-3 rounded-full', activity.color, 'group-hover:scale-125 transition-transform')}></div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start gap-3">
                      <div className={cn('p-2 rounded-lg', activity.color.replace('bg-', 'bg-').replace('-500', '-100'))}>
                        <activity.icon className={cn('h-4 w-4', activity.color.replace('bg-', 'text-'))} />
                      </div>
                      <div className="flex-1">
                        <p className="text-sm font-medium text-foreground group-hover:text-primary transition-colors">
                          {activity.title}
                        </p>
                        <p className="text-xs text-muted-foreground mt-1">{activity.time}</p>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}