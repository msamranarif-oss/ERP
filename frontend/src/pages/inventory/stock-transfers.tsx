import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, Search, Package, Warehouse, ArrowRightLeft, CheckCircle, XCircle } from 'lucide-react'
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
import { stockTransfersApi, warehousesApi } from '@/lib/api'
import { StockTransferForm } from '../forms/stock-transfer-form'

export function StockTransfersPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingTransfer, setEditingTransfer] = useState(null)
  
  const queryClient = useQueryClient()

  const { data: transfersData, isLoading, refetch } = useQuery({
    queryKey: ['stockTransfers', { search, page: currentPage }],
    queryFn: () => stockTransfersApi.getAll({ search, page: currentPage, per_page: 10 }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => stockTransfersApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stockTransfers'] })
    },
  })

  const approveMutation = useMutation({
    mutationFn: (id: number) => stockTransfersApi.approve(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stockTransfers'] })
    },
  })

  const completeMutation = useMutation({
    mutationFn: (id: number) => stockTransfersApi.complete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stockTransfers'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this stock transfer?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (transfer: any) => {
    setEditingTransfer(transfer)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingTransfer(null)
  }

  const handleStatusAction = (transfer: any) => {
    if (transfer.status === 'pending') {
      if (window.confirm('Are you sure you want to approve this stock transfer?')) {
        approveMutation.mutate(transfer.id)
      }
    } else if (transfer.status === 'approved') {
      if (window.confirm('Are you sure you want to complete this stock transfer?')) {
        completeMutation.mutate(transfer.id)
      }
    }
  }

  const transfers = transfersData?.data?.data || []
  const pagination = transfersData?.data?.meta || {}

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'pending':
        return 'secondary'
      case 'approved':
        return 'default'
      case 'completed':
        return 'success'
      case 'cancelled':
        return 'destructive'
      default:
        return 'secondary'
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">Stock Transfers</h1>
          <p className="text-muted-foreground">Manage stock transfers between warehouses</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditingTransfer(null)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Stock Transfer
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                {editingTransfer ? 'Edit Stock Transfer' : 'Add New Stock Transfer'}
              </DialogTitle>
            </DialogHeader>
            <StockTransferForm 
              transfer={editingTransfer} 
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
                placeholder="Search stock transfers..."
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
                <TableHead>Transfer Number</TableHead>
                <TableHead>From Warehouse</TableHead>
                <TableHead>To Warehouse</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center">
                    Loading...
                  </TableCell>
                </TableRow>
              ) : transfers.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center">
                    No stock transfers found
                  </TableCell>
                </TableRow>
              ) : (
                transfers.map((transfer: any) => (
                  <TableRow key={transfer.id}>
                    <TableCell className="font-medium">{transfer.transfer_number}</TableCell>
                    <TableCell>{transfer.from_warehouse?.name || '-'}</TableCell>
                    <TableCell>{transfer.to_warehouse?.name || '-'}</TableCell>
                    <TableCell>{new Date(transfer.date).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <Badge variant={getStatusBadgeVariant(transfer.status)}>
                        {transfer.status.charAt(0).toUpperCase() + transfer.status.slice(1)}
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
                          <DropdownMenuItem onClick={() => handleEdit(transfer)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                          </DropdownMenuItem>
                          {transfer.status === 'pending' && (
                            <DropdownMenuItem onClick={() => handleStatusAction(transfer)}>
                              <CheckCircle className="mr-2 h-4 w-4" />
                              Approve
                            </DropdownMenuItem>
                          )}
                          {transfer.status === 'approved' && (
                            <DropdownMenuItem onClick={() => handleStatusAction(transfer)}>
                              <Package className="mr-2 h-4 w-4" />
                              Complete
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuItem onClick={() => handleDelete(transfer.id)}>
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                          </DropdownMenuItem>
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