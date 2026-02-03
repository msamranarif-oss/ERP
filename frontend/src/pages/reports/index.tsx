import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
  BarChart3,
  Package,
  CreditCard,
  DollarSign,
  FileText,
  TrendingUp,
  Activity,
  ChevronRight
} from 'lucide-react'
import { Link } from 'react-router-dom'

export function ReportsIndexPage() {
  const reportSections = [
    {
      title: 'Sales Reports',
      description: 'View sales performance, trends, and analytics',
      icon: BarChart3,
      link: '/reports/sales',
      type: 'primary',
    },
    {
      title: 'Inventory Reports',
      description: 'Track stock levels, movements, and valuation',
      icon: Package,
      link: '/reports/inventory',
      type: 'success',
    },
    {
      title: 'Financial Reports',
      description: 'Access financial statements and accounting reports',
      icon: DollarSign,
      link: '/reports/financial',
      type: 'purple',
    },
    {
      title: 'Installment Reports',
      description: 'Monitor credit sales and payment schedules',
      icon: CreditCard,
      link: '/reports/installments',
      type: 'warning',
    },
    {
      title: 'Activity Reports',
      description: 'Track user activities and system logs',
      icon: Activity,
      link: '/reports/activity',
      type: 'primary',
    },
    {
      title: 'Custom Reports',
      description: 'Create and manage custom reports',
      icon: FileText,
      link: '/reports/custom',
      type: 'purple',
    },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Reports Dashboard</h1>
          <p className="text-slate-500">
            Comprehensive business intelligence and performance metrics.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {reportSections.map((section, index) => (
          <Link key={index} to={section.link} className="group">
            <Card className="border-slate-200 shadow-sm hover:shadow-md hover:border-blue-200 transition-all cursor-pointer h-full">
              <CardHeader className="pb-4">
                <div className="flex items-center justify-between">
                  <div className={`stat-icon-bg-enterprise ${section.type}`}>
                    <section.icon className="h-6 w-6" />
                  </div>
                  <TrendingUp className="h-4 w-4 text-slate-300 group-hover:text-blue-400 transition-colors" />
                </div>
                <CardTitle className="text-lg font-bold text-slate-900 mt-4">{section.title}</CardTitle>
                <CardDescription className="text-slate-500 text-sm leading-relaxed mt-1">
                  {section.description}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-center text-sm font-semibold text-blue-600 group-hover:text-blue-700">
                  View full report <ChevronRight className="h-4 w-4 ml-1 transition-transform group-hover:translate-x-1" />
                </div>
              </CardContent>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  )
}