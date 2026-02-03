import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, Package, FileText, Calendar, Truck, DollarSign, Eye, CheckCircle2, XCircle, MoreHorizontal, ShoppingCart, Clock } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu'
import { Badge } from '@/components/ui/badge'
import { purchaseOrdersApi } from '@/lib/api'
import { PurchaseOrderForm } from '../forms/purchase-order-form'

import { ColumnDef } from '@tanstack/react-table'

export type PurchaseOrder = {
  id: number
  po_number: string
  supplier?: { name: string }
  date: string
  total_amount: number
  status: string
}

export function PurchaseOrdersPage() {
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingOrder, setEditingOrder] = useState<PurchaseOrder | null>(null)

  const queryClient = useQueryClient()

  // Fetch larger batch for DataTable searching
  const { data: ordersData, isLoading, refetch } = useQuery({
    queryKey: ['purchaseOrders'],
    queryFn: () => purchaseOrdersApi.getAll({ per_page: 100 }),
  })

  const submitMutation = useMutation({
    mutationFn: (id: number) => purchaseOrdersApi.submit(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchaseOrders'] })
    },
  })

  const receiveMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => purchaseOrdersApi.receive(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchaseOrders'] })
    },
  })

  const cancelMutation = useMutation({
    mutationFn: (id: number) => purchaseOrdersApi.cancel(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchaseOrders'] })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => purchaseOrdersApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchaseOrders'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this purchase order?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (order: PurchaseOrder) => {
    setEditingOrder(order)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingOrder(null)
  }

  const handleStatusAction = (order: PurchaseOrder) => {
    if (order.status === 'pending') {
      if (window.confirm('Confirm submission of this Purchase Order?')) {
        submitMutation.mutate(order.id)
      }
    } else if (order.status === 'submitted') {
      if (window.confirm('Confirm receipt of all items in this order?')) {
        receiveMutation.mutate({ id: order.id, data: {} })
      }
    } else if (order.status === 'pending' || order.status === 'submitted') {
      if (window.confirm('Are you sure you want to cancel this procurement request?')) {
        cancelMutation.mutate(order.id)
      }
    }
  }

  const orders: PurchaseOrder[] = ordersData?.data?.data || []

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Procurement & POs</h1>
          <p className="text-slate-500">Manage supplier relations and inbound stock acquisition.</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2" onClick={() => setEditingOrder(null)}>
              <Plus className="h-4 w-4" />
              Generate Purchase Order
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-xl font-bold text-slate-900">
                {editingOrder ? 'Update PO Specifications' : 'Draft New Purchase Order'}
              </DialogTitle>
            </DialogHeader>
            <PurchaseOrderForm
              order={editingOrder}
              onClose={handleDialogClose}
              onSuccess={refetch}
            />
          </DialogContent>
        </Dialog>
      </div>

      {/* Purchase Orders Table */}
      <div className="card-enterprise border-slate-200 shadow-sm overflow-hidden">
        <DataTable<PurchaseOrder, unknown>
          columns={[
            {
              accessorKey: 'po_number',
              header: 'Reference ID',
              cell: ({ row }) => (
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 border border-blue-100">
                    <FileText className="h-4 w-4" />
                  </div>
                  <div className="font-bold text-slate-900 uppercase tracking-tighter">{row.original.po_number}</div>
                </div>
              ),
            },
            {
              accessorKey: 'supplier',
              header: 'Supplier Entity',
              cell: ({ row }) => (
                <div className="flex flex-col">
                  <div className="font-bold text-slate-900">{row.original.supplier?.name || 'Unknown Supplier'}</div>
                  <div className="flex items-center gap-1 text-[10px] font-bold text-slate-400 mt-0.5 uppercase tracking-tight">
                    <Truck className="h-2.5 w-2.5" /> Direct Vendor
                  </div>
                </div>
              ),
            },
            {
              accessorKey: 'date',
              header: 'Issuance Date',
              cell: ({ row }) => (
                <div className="flex items-center gap-1.5 text-slate-600 text-sm font-medium">
                  <Calendar className="h-3.5 w-3.5 text-slate-400" />
                  {new Date(row.original.date).toLocaleDateString()}
                </div>
              ),
            },
            {
              accessorKey: 'total_amount',
              header: 'Total Value',
              cell: ({ row }) => (
                <div className="font-black text-slate-900">${row.original.total_amount?.toFixed(2) || '0.00'}</div>
              ),
            },
            {
              accessorKey: 'status',
              header: 'Workflow Status',
              cell: ({ row }) => {
                const status = row.original.status
                if (status === 'pending') {
                  return <div className="flex items-center gap-1.5 text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-slate-100"><Clock className="h-3 w-3" /> Drafted</div>
                }
                if (status === 'submitted') {
                  return <div className="flex items-center gap-1.5 text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-blue-100"><CheckCircle2 className="h-3 w-3" /> Submitted</div>
                }
                if (status === 'received') {
                  return <div className="flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-green-100"><Package className="h-3 w-3" /> Received</div>
                }
                if (status === 'cancelled') {
                  return <div className="flex items-center gap-1.5 text-red-600 bg-red-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-red-100"><XCircle className="h-3 w-3" /> Voided</div>
                }
                return <Badge variant="secondary" className="text-[10px] font-bold uppercase">{status}</Badge>
              },
            },
            {
              id: 'actions',
              cell: ({ row }) => (
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-slate-400 hover:text-slate-900 hover:bg-slate-100">
                      <MoreHorizontal className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-44 border-slate-200 shadow-enterprise">
                    <DropdownMenuItem onClick={() => handleEdit(row.original)} className="gap-2 focus:bg-blue-50 focus:text-blue-700 cursor-pointer">
                      <Edit className="h-4 w-4" /> Edit Details
                    </DropdownMenuItem>
                    {row.original.status === 'pending' && (
                      <DropdownMenuItem onClick={() => handleStatusAction(row.original)} className="gap-2 focus:bg-green-50 focus:text-green-700 cursor-pointer">
                        <CheckCircle2 className="h-4 w-4" /> Submit Order
                      </DropdownMenuItem>
                    )}
                    {row.original.status === 'submitted' && (
                      <DropdownMenuItem onClick={() => handleStatusAction(row.original)} className="gap-2 focus:bg-blue-50 focus:text-blue-700 cursor-pointer">
                        <Package className="h-4 w-4" /> Confirm Receipt
                      </DropdownMenuItem>
                    )}
                    {(row.original.status === 'pending' || row.original.status === 'submitted') && (
                      <DropdownMenuItem onClick={() => handleStatusAction(row.original)} className="gap-2 focus:bg-red-50 focus:text-red-700 cursor-pointer text-red-600">
                        <XCircle className="h-4 w-4" /> Cancel PO
                      </DropdownMenuItem>
                    )}
                    <DropdownMenuItem onClick={() => handleDelete(row.original.id)} className="gap-2 focus:bg-slate-50 cursor-pointer">
                      <Trash2 className="h-4 w-4" /> Delete Permanently
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              ),
            },
          ]}
          data={orders}
          loading={isLoading}
        />
      </div>
    </div>
  )
}