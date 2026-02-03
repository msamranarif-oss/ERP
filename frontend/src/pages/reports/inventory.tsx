import { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Package,
  AlertTriangle,
  TrendingUp,
  TrendingDown,
  Box,
  ShoppingCart
} from 'lucide-react'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

interface StockData {
  product: string
  current_stock: number
  reorder_level: number
  value: number
}

interface MovementData {
  product: string
  movement_type: string
  quantity: number
  date: string
}

export function InventoryReportsPage() {
  const [stockData, setStockData] = useState<StockData[]>([])
  const [movementData, setMovementData] = useState<MovementData[]>([])
  const [lowStockItems, setLowStockItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Fetch inventory data from API
    fetchInventoryData()
  }, [])

  const fetchInventoryData = async () => {
    try {
      // Mock data - replace with actual API calls
      const mockStockData: StockData[] = [
        { product: 'Product A', current_stock: 45, reorder_level: 20, value: 900 },
        { product: 'Product B', current_stock: 12, reorder_level: 15, value: 360 },
        { product: 'Product C', current_stock: 78, reorder_level: 25, value: 1560 },
        { product: 'Product D', current_stock: 5, reorder_level: 10, value: 100 },
        { product: 'Product E', current_stock: 120, reorder_level: 30, value: 2400 },
      ]

      const mockMovementData: MovementData[] = [
        { product: 'Product A', movement_type: 'IN', quantity: 50, date: '2023-06-01' },
        { product: 'Product B', movement_type: 'OUT', quantity: 38, date: '2023-06-02' },
        { product: 'Product C', movement_type: 'IN', quantity: 100, date: '2023-06-03' },
        { product: 'Product D', movement_type: 'OUT', quantity: 15, date: '2023-06-04' },
        { product: 'Product E', movement_type: 'IN', quantity: 80, date: '2023-06-05' },
      ]

      const mockLowStockItems = [
        { name: 'Product B', sku: 'PROD-B-002', current_stock: 12, reorder_level: 15 },
        { name: 'Product D', sku: 'PROD-D-004', current_stock: 5, reorder_level: 10 },
        { name: 'Product F', sku: 'PROD-F-006', current_stock: 3, reorder_level: 8 },
      ]

      setStockData(mockStockData)
      setMovementData(mockMovementData)
      setLowStockItems(mockLowStockItems)
      setLoading(false)
    } catch (error) {
      console.error('Failed to load inventory data:', error)
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Inventory Reports</h1>
          <p className="text-slate-500">Track inventory levels, movements, and valuation across all branches.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Total Items</CardTitle>
            <div className="stat-icon-bg-enterprise primary">
              <Box className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">1,234</div>
            <p className="text-xs text-green-600 font-semibold mt-1">+5.2% from last month</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Total Value</CardTitle>
            <div className="stat-icon-bg-enterprise success">
              <DollarSign className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">$45,231.89</div>
            <p className="text-xs text-green-600 font-semibold mt-1">+12.4% from last month</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Reorder Alerts</CardTitle>
            <div className="stat-icon-bg-enterprise warning">
              <AlertTriangle className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">12</div>
            <p className="text-xs text-red-600 font-semibold mt-1">Items below safety stock</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-slate-500">Fast Movers</CardTitle>
            <div className="stat-icon-bg-enterprise purple">
              <TrendingUp className="h-4 w-4" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">85</div>
            <p className="text-xs text-slate-500 mt-1">High turnaround items</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Stock Levels</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-72">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart
                  data={stockData}
                  margin={{ top: 20, right: 30, left: 20, bottom: 40 }}
                >
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="product" angle={-45} textAnchor="end" height={60} />
                  <YAxis />
                  <Tooltip />
                  <Bar dataKey="current_stock" fill="#8884d8" name="Current Stock" />
                  <Bar dataKey="reorder_level" fill="#ff7300" name="Reorder Level" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Low Stock Items</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {lowStockItems.map((item, index) => (
                <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                  <div>
                    <p className="font-medium">{item.name}</p>
                    <p className="text-sm text-muted-foreground">{item.sku}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-medium">{item.current_stock} in stock</p>
                    <p className="text-sm text-muted-foreground">Reorder at {item.reorder_level}</p>
                  </div>
                  <Badge variant="destructive" className="ml-4">
                    <AlertTriangle className="h-3 w-3 mr-1" />
                    Low
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent Stock Movements</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-2">Product</th>
                  <th className="text-left py-2">Movement Type</th>
                  <th className="text-left py-2">Quantity</th>
                  <th className="text-left py-2">Date</th>
                </tr>
              </thead>
              <tbody>
                {movementData.map((movement, index) => (
                  <tr key={index} className="border-b">
                    <td className="py-2">{movement.product}</td>
                    <td className="py-2">
                      <Badge variant={movement.movement_type === 'IN' ? 'default' : 'secondary'}>
                        {movement.movement_type}
                      </Badge>
                    </td>
                    <td className="py-2">{movement.movement_type === 'IN' ? '+' : '-'}{movement.quantity}</td>
                    <td className="py-2">{movement.date}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}