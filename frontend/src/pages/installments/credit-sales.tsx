import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, CreditCard, DollarSign, Calendar, User, Eye, ArrowUpRight, CheckCircle2, AlertCircle, MoreHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { creditSalesApi } from '@/lib/api'
import { CreditSaleForm } from '../forms/credit-sale-form'

import { ColumnDef } from '@tanstack/react-table'

export type CreditSale = {
  id: number
  credit_sale_number: string
  customer?: { customer: { name: string } }
  loan_amount: number
  interest_rate: number
  down_payment: number
  number_of_installments: number
  status: string
}

export function CreditSalesPage() {
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingSale, setEditingSale] = useState<CreditSale | null>(null)

  const queryClient = useQueryClient()

  // Fetch a larger batch for better local searching in DataTable
  const { data: salesData, isLoading, refetch } = useQuery({
    queryKey: ['creditSales'],
    queryFn: () => creditSalesApi.getAll({ per_page: 100 }),
  })

  const sales: CreditSale[] = salesData?.data?.data || []

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Credit & Installments</h1>
          <p className="text-slate-500">Monitor credit sales, loan disbursements, and repayment schedules.</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2" onClick={() => setEditingSale(null)}>
              <Plus className="h-4 w-4" />
              New Credit Arrangement
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-xl font-bold text-slate-900">
                {editingSale ? 'Update Arrangement Details' : 'Register New Credit Sale'}
              </DialogTitle>
            </DialogHeader>
            <CreditSaleForm
              sale={editingSale}
              onClose={() => setIsDialogOpen(false)}
              onSuccess={refetch}
            />
          </DialogContent>
        </Dialog>
      </div>

      {/* Credit Sales Table */}
      <div className="card-enterprise border-slate-200 shadow-sm overflow-hidden">
        <DataTable<CreditSale, unknown>
          columns={[
            {
              accessorKey: 'credit_sale_number',
              header: 'Contract ID',
              cell: ({ row }) => (
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 border border-blue-100">
                    <CreditCard className="h-4 w-4" />
                  </div>
                  <div className="font-bold text-slate-900 uppercase tracking-tighter">{row.original.credit_sale_number}</div>
                </div>
              ),
            },
            {
              accessorKey: 'customer',
              header: 'Client Portfolio',
              cell: ({ row }) => (
                <div className="flex flex-col">
                  <div className="font-bold text-slate-900">{row.original.customer?.customer?.name || 'Walk-in'}</div>
                  <div className="text-[10px] font-bold text-slate-400 uppercase">Internal Account</div>
                </div>
              ),
            },
            {
              accessorKey: 'loan_amount',
              header: 'Financing Details',
              cell: ({ row }) => (
                <div className="flex flex-col">
                  <div className="font-bold text-slate-900">${row.original.loan_amount?.toFixed(2)}</div>
                  <div className="text-[10px] font-bold text-green-600 uppercase">Interest: {row.original.interest_rate}%</div>
                </div>
              ),
            },
            {
              accessorKey: 'down_payment',
              header: 'Disbursement',
              cell: ({ row }) => (
                <div className="flex flex-col">
                  <div className="font-semibold text-slate-600">DP: ${row.original.down_payment?.toFixed(2)}</div>
                  <div className="text-[10px] font-medium text-slate-400 uppercase tracking-tight">Remaining: {row.original.number_of_installments} Inst.</div>
                </div>
              ),
            },
            {
              accessorKey: 'status',
              header: 'Account Status',
              cell: ({ row }) => {
                const status = row.original.status
                if (status === 'active') {
                  return <div className="flex items-center gap-1.5 text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-blue-100"><ArrowUpRight className="h-3 w-3" /> Repaying</div>
                }
                if (status === 'completed') {
                  return <div className="flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-green-100"><CheckCircle2 className="h-3 w-3" /> Settled</div>
                }
                if (status === 'defaulted') {
                  return <div className="flex items-center gap-1.5 text-red-600 bg-red-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-red-100"><AlertCircle className="h-3 w-3" /> Overdue</div>
                }
                return <Badge variant="secondary" className="text-[10px] font-bold uppercase">{status}</Badge>
              },
            },
            {
              id: 'actions',
              cell: () => (
                <Button variant="ghost" size="icon" className="h-8 w-8 text-slate-400 hover:text-slate-900 hover:bg-slate-100">
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              ),
            },
          ]}
          data={sales}
          loading={isLoading}
        />
      </div>
    </div>
  )
}