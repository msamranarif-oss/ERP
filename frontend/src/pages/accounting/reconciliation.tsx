import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Search, Building, Calendar, DollarSign, CheckCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from '@/components/ui/table'
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
import { bankReconciliationsApi, bankAccountsApi } from '@/lib/api'
import { BankReconciliationForm } from '../forms/bank-reconciliation-form'

export function BankReconciliationsPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingReconciliation, setEditingReconciliation] = useState(null)
  
  const queryClient = useQueryClient()

  const { data: reconciliationsData, isLoading, refetch } = useQuery({
    queryKey: ['bankReconciliations', { search, page: currentPage }],
    queryFn: () => bankReconciliationsApi.getAll({ search, page: currentPage, per_page: 10 }),
  })

  const completeMutation = useMutation({
    mutationFn: (id: number) => bankReconciliationsApi.complete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bankReconciliations'] })
    },
  })

  const handleComplete = (id: number) => {
    if (window.confirm('Are you sure you want to complete this bank reconciliation?')) {
      completeMutation.mutate(id)
    }
  }

  const handleEdit = (reconciliation: any) => {
    setEditingReconciliation(reconciliation)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingReconciliation(null)
  }

  const reconciliations = reconciliationsData?.data?.data || []
  const pagination = reconciliationsData?.data?.meta || {}

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'pending':
        return 'secondary'
      case 'completed':
        return 'success'
      default:
        return 'secondary'
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">Bank Reconciliations</h1>
          <p className="text-muted-foreground">Reconcile your bank statements</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditingReconciliation(null)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Reconciliation
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                {editingReconciliation ? 'Edit Reconciliation' : 'Add New Reconciliation'}
              </DialogTitle>
            </DialogHeader>
            <BankReconciliationForm 
              reconciliation={editingReconciliation} 
              onClose={handleDialogClose} 
              onSuccess={refetch}
            />
          </DialogContent>
        </Dialog>
      </div>

      <Card>
        <CardHeader>
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search reconciliations..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-8"
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Bank Account</TableHead>
                <TableHead>Statement Date</TableHead>
                <TableHead>Statement Balance</TableHead>
                <TableHead>Book Balance</TableHead>
                <TableHead>Adjusted Balance</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center">
                    Loading...
                  </TableCell>
                </TableRow>
              ) : reconciliations.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center">
                    No bank reconciliations found
                  </TableCell>
                </TableRow>
              ) : (
                reconciliations.map((reconciliation: any) => (
                  <TableRow key={reconciliation.id}>
                    <TableCell className="font-medium">{reconciliation.bank_account?.name}</TableCell>
                    <TableCell>{new Date(reconciliation.statement_date).toLocaleDateString()}</TableCell>
                    <TableCell>${reconciliation.statement_balance?.toFixed(2)}</TableCell>
                    <TableCell>${reconciliation.book_balance?.toFixed(2)}</TableCell>
                    <TableCell>${reconciliation.adjusted_book_balance?.toFixed(2)}</TableCell>
                    <TableCell>
                      <Badge variant={getStatusBadgeVariant(reconciliation.status)}>
                        {reconciliation.status.charAt(0).toUpperCase() + reconciliation.status.slice(1)}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            Actions
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => handleEdit(reconciliation)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                          </DropdownMenuItem>
                          {reconciliation.status === 'pending' && (
                            <DropdownMenuItem onClick={() => handleComplete(reconciliation.id)}>
                              <CheckCircle className="mr-2 h-4 w-4" />
                              Complete
                            </DropdownMenuItem>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
          
          {/* Pagination */}
          {pagination.last_page > 1 && (
            <div className="flex items-center justify-between mt-4">
              <div className="text-sm text-muted-foreground">
                Showing {pagination.from} to {pagination.to} of {pagination.total} results
              </div>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage(pagination.current_page - 1)}
                  disabled={pagination.current_page === 1}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage(pagination.current_page + 1)}
                  disabled={pagination.current_page === pagination.last_page}
                >
                  Next
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}