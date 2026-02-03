import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Package, Tag, Warehouse, Truck, ChevronRight, TrendingUp } from 'lucide-react'
import { Link } from 'react-router-dom'

export function InventoryIndexPage() {
  const quickLinks = [
    {
      title: 'Catalog Management',
      description: 'System-wide product list, SKU tracking, and inventory levels.',
      icon: Package,
      to: '/inventory/products',
      type: 'primary'
    },
    {
      title: 'Classification',
      description: 'Manage product categories and organizational hierarchy.',
      icon: Tag,
      to: '/inventory/categories',
      type: 'success'
    },
    {
      title: 'Warehouse Control',
      description: 'Physical storage locations, zones, and branch transfers.',
      icon: Warehouse,
      to: '/inventory/warehouses',
      type: 'purple'
    },
    {
      title: 'Vendor Directory',
      description: 'Supplier contact details, order history, and procurement.',
      icon: Truck,
      to: '/inventory/suppliers',
      type: 'warning'
    },
  ]

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Inventory Management</h1>
          <p className="text-slate-500">
            Enterprise-grade stock control and supply chain monitoring.
          </p>
        </div>
      </div>

      {/* Primary Links */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {quickLinks.map((link) => (
          <Link key={link.to} to={link.to} className="group">
            <Card className="border-slate-200 shadow-sm hover:shadow-md hover:border-blue-200 transition-all cursor-pointer h-full">
              <CardHeader className="pb-4">
                <div className="flex items-center justify-between">
                  <div className={`stat-icon-bg-enterprise ${link.type}`}>
                    <link.icon className="h-6 w-6" />
                  </div>
                  <ChevronRight className="h-4 w-4 text-slate-300 group-hover:text-blue-400 transition-all group-hover:translate-x-1" />
                </div>
                <CardTitle className="text-lg font-bold text-slate-900 mt-4">{link.title}</CardTitle>
                <CardDescription className="text-slate-500 text-sm leading-relaxed mt-1">
                  {link.description}
                </CardDescription>
              </CardHeader>
            </Card>
          </Link>
        ))}
      </div>

      {/* Overview stats - Refined */}
      <Card className="card-enterprise border-slate-200 shadow-sm">
        <CardHeader className="bg-slate-50/50 border-b border-slate-100">
          <CardTitle className="text-sm font-bold uppercase tracking-wider text-slate-400">Inventory Status Overview</CardTitle>
        </CardHeader>
        <CardContent className="pt-6">
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 px-2">
            <div className="flex flex-col gap-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-tighter">Total Products</span>
              <span className="text-3xl font-black text-slate-900">120</span>
              <div className="flex items-center gap-1 text-[10px] font-bold text-green-600 bg-green-50 w-fit px-1.5 py-0.5 rounded uppercase">
                <TrendingUp className="h-2.5 w-2.5" /> +12 NEW
              </div>
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-tighter">Active Categories</span>
              <span className="text-3xl font-black text-slate-900">15</span>
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Across 3 Clusters</span>
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-tighter">Global Warehouses</span>
              <span className="text-3xl font-black text-slate-900">3</span>
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Operational Hubs</span>
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-tighter">Low Stock Alerts</span>
              <span className="text-3xl font-black text-red-600">8</span>
              <div className="flex items-center gap-1 text-[10px] font-bold text-red-600 bg-red-50 w-fit px-1.5 py-0.5 rounded uppercase">
                Requires Action
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
