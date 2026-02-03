import { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  BarChart3,
  Calendar,
  TrendingUp,
  DollarSign,
  Users,
  ShoppingCart
} from 'lucide-react'
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

interface SalesData {
  period: string
  sales: number
  orders: number
  customers: number
}

interface TopProduct {
  name: string
  sku: string
  quantity_sold: number
  revenue: number
}

export function SalesReportsPage() {
  const [salesData, setSalesData] = useState<SalesData[]>([])
  const [topProducts, setTopProducts] = useState<TopProduct[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Fetch sales data from API
    fetchSalesData()
  }, [])

  const fetchSalesData = async () => {
    try {
      // Mock data - replace with actual API calls
      const mockSalesData: SalesData[] = [
        { period: 'Jan', sales: 4000, orders: 240, customers: 120 },
        { period: 'Feb', sales: 3000, orders: 138, customers: 89 },
        { period: 'Mar', sales: 2000, orders: 98, customers: 65 },
        { period: 'Apr', sales: 2780, orders: 156, customers: 87 },
        { period: 'May', sales: 1890, orders: 123, customers: 76 },
        { period: 'Jun', sales: 2390, orders: 198, customers: 102 },
      ]

      const mockTopProducts: TopProduct[] = [
        { name: 'Product A', sku: 'PROD-A-001', quantity_sold: 120, revenue: 2400 },
        { name: 'Product B', sku: 'PROD-B-002', quantity_sold: 98, revenue: 1960 },
        { name: 'Product C', sku: 'PROD-C-003', quantity_sold: 75, revenue: 1500 },
        { name: 'Product D', sku: 'PROD-D-004', quantity_sold: 62, revenue: 1240 },
        { name: 'Product E', sku: 'PROD-E-005', quantity_sold: 54, revenue: 1080 },
      ]

      setSalesData(mockSalesData)
      setTopProducts(mockTopProducts)
      setLoading(false)
    } catch (error) {
      console.error('Failed to load sales data:', error)
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Sales Reports</h1>
          <p className="text-slate-500">Comprehensive analysis of sales performance and revenue trends.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Total Sales</CardTitle>
            <div className="stat-icon-bg-enterprise primary">
              <DollarSign className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">$15,231.89</div>
            <p className="text-xs text-green-600 font-semibold mt-1">+20.1% from last month</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Total Orders</CardTitle>
            <div className="stat-icon-bg-enterprise success">
              <ShoppingCart className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">1,234</div>
            <p className="text-xs text-green-600 font-semibold mt-1">+18.2% from last month</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Active Customers</CardTitle>
            <div className="stat-icon-bg-enterprise purple">
              <Users className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">567</div>
            <p className="text-xs text-green-600 font-semibold mt-1">+12.5% from last month</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Avg. Order Value</CardTitle>
            <div className="stat-icon-bg-enterprise warning">
              <BarChart3 className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">$123.45</div>
            <p className="text-xs text-slate-500 mt-1">Consistent with Oct</p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Sales Trends</CardTitle>
            <Button variant="outline" size="sm">
              <Calendar className="mr-2 h-4 w-4" />
              Last 6 months
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart
                data={salesData}
                margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
              >
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="period" />
                <YAxis />
                <Tooltip />
                <Area type="monotone" dataKey="sales" stackId="1" stroke="#8884d8" fill="#8884d8" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Top Selling Products</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {topProducts.map((product, index) => (
                <div key={index} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium">{product.name}</p>
                    <p className="text-sm text-muted-foreground">{product.sku}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-medium">{product.quantity_sold} sold</p>
                    <p className="text-sm text-muted-foreground">${product.revenue.toFixed(2)}</p>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Sales by Category</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {[
                { name: 'Electronics', percentage: 35, color: 'bg-blue-500' },
                { name: 'Clothing', percentage: 25, color: 'bg-green-500' },
                { name: 'Home & Garden', percentage: 20, color: 'bg-yellow-500' },
                { name: 'Sports', percentage: 15, color: 'bg-red-500' },
                { name: 'Books', percentage: 5, color: 'bg-purple-500' },
              ].map((category, index) => (
                <div key={index}>
                  <div className="flex items-center justify-between mb-1">
                    <span className="text-sm font-medium">{category.name}</span>
                    <span className="text-sm text-muted-foreground">{category.percentage}%</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div
                      className={`h-2 rounded-full ${category.color}`}
                      style={{ width: `${category.percentage}%` }}
                    ></div>
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