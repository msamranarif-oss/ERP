import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, ShieldCheck, Landmark, MoreHorizontal, ArrowRightLeft, CheckCircle2, XCircle } from 'lucide-react'
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
import { accountsApi } from '@/lib/api'
import { AccountForm } from '../forms/account-form'

export function ChartOfAccountsPage() {
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingAccount, setEditingAccount] = useState(null)

  const queryClient = useQueryClient()

  const { data: accountsData, isLoading, refetch } = useQuery({
    queryKey: ['accounts'],
    queryFn: () => accountsApi.getAll({ per_page: 200 }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => accountsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['accounts'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to permanently remove this account from the ledger?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (account: any) => {
    setEditingAccount(account)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingAccount(null)
  }

  const accounts = accountsData?.data?.data || []

  const getTypeStyle = (type: string) => {
    switch (type) {
      case 'asset': return 'border-blue-200 text-blue-700 bg-blue-25'
      case 'liability': return 'border-red-200 text-red-700 bg-red-25'
      case 'equity': return 'border-purple-200 text-purple-700 bg-purple-25'
      case 'revenue': return 'border-green-200 text-green-700 bg-green-25'
      case 'expense': return 'border-orange-200 text-orange-700 bg-orange-25'
      default: return 'border-slate-200 text-slate-700 bg-slate-25'
    }
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Chart of Accounts</h1>
          <p className="text-slate-500">Global general ledger accounts and financial categorization.</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2" onClick={() => setEditingAccount(null)}>
              <Plus className="h-4 w-4" />
              Register Account
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-xl font-bold text-slate-900">
                {editingAccount ? 'Update Ledger Definition' : 'Define New Ledger Account'}
              </DialogTitle>
            </DialogHeader>
            <AccountForm
              account={editingAccount}
              onClose={handleDialogClose}
              onSuccess={refetch}
            />
          </DialogContent>
        </Dialog>
      </div>

      {/* Ledger Table */}
      <div className="card-enterprise border-slate-200 shadow-sm overflow-hidden">
        <DataTable
          columns={[
            {
              accessorKey: 'code',
              header: 'G/L Code',
              cell: ({ row }) => (
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-50 text-slate-400 border border-slate-100">
                    <Landmark className="h-4 w-4" />
                  </div>
                  <div className="font-mono font-bold text-slate-900 tracking-wider text-sm">{row.original.code}</div>
                </div>
              ),
            },
            {
              accessorKey: 'name',
              header: 'Account Identity',
              cell: ({ row }) => (
                <div className="flex flex-col">
                  <div className="font-bold text-slate-900">{row.original.name}</div>
                  <div className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">{row.original.category.replace('_', ' ')}</div>
                </div>
              ),
            },
            {
              accessorKey: 'type',
              header: 'Account Class',
              cell: ({ row }) => (
                <div className={`px-2 py-0.5 rounded border text-[10px] font-black uppercase tracking-widest w-fit ${getTypeStyle(row.original.type)}`}>
                  {row.original.type}
                </div>
              ),
            },
            {
              accessorKey: 'balance',
              header: 'Ledger Balance',
              cell: ({ row }) => (
                <div className="font-black text-slate-900">
                  ${row.original.opening_balance?.toFixed(2) || '0.00'}
                </div>
              ),
            },
            {
              accessorKey: 'is_active',
              header: 'Status',
              cell: ({ row }) => (
                row.original.is_active ? (
                  <div className="flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-green-100">
                    <CheckCircle2 className="h-3 w-3" /> Active
                  </div>
                ) : (
                  <div className="flex items-center gap-1.5 text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-slate-100">
                    <XCircle className="h-3 w-3" /> Disabled
                  </div>
                )
              ),
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
                      <Edit className="h-4 w-4" /> Update Defintion
                    </DropdownMenuItem>
                    <DropdownMenuItem className="gap-2 focus:bg-slate-50 cursor-pointer">
                      <ArrowRightLeft className="h-4 w-4" /> View Ledger
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => handleDelete(row.original.id)} className="gap-2 focus:bg-red-50 focus:text-red-700 cursor-pointer text-red-600">
                      <Trash2 className="h-4 w-4" /> Remove Account
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              ),
            },
          ]}
          data={accounts}
          loading={isLoading}
        />
      </div>
    </div>
  )
}