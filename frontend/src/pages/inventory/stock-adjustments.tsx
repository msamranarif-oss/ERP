import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, Search, Package, Warehouse, PlusCircle, MinusCircle, CheckCircle, XCircle } from 'lucide-react'
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
import { stockAdjustmentsApi, warehousesApi } from '@/lib/api'
import { StockAdjustmentForm } from '../forms/stock-adjustment-form'

export function StockAdjustmentsPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingAdjustment, setEditingAdjustment] = useState(null)
  
  const queryClient = useQueryClient()

  const { data: adjustmentsData, isLoading, refetch } = useQuery({
    queryKey: ['stockAdjustments', { search, page: currentPage }],
    queryFn: () => stockAdjustmentsApi.getAll({ search, page: currentPage, per_page: 10 }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => stockAdjustmentsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stockAdjustments'] })
    },
  })

  const approveMutation = useMutation({
    mutationFn: (id: number) => stockAdjustmentsApi.approve(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stockAdjustments'] })
    },
  })

  const rejectMutation = useMutation({
    mutationFn: (id: number) => stockAdjustmentsApi.reject(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stockAdjustments'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this stock adjustment?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (adjustment: any) => {
    setEditingAdjustment(adjustment)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingAdjustment(null)
  }

  const handleStatusAction = (adjustment: any) => {
    if (adjustment.status === 'pending') {
      if (window.confirm('Are you sure you want to approve this stock adjustment?')) {
        approveMutation.mutate(adjustment.id)
      }
    } else if (adjustment.status === 'pending') {
      if (window.confirm('Are you sure you want to reject this stock adjustment?')) {
        rejectMutation.mutate(adjustment.id)
      }
    }
  }

  const adjustments = adjustmentsData?.data?.data || []
  const pagination = adjustmentsData?.data?.meta || {}

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'pending':
        return 'secondary'
      case 'approved':
        return 'success'
      case 'rejected':
        return 'destructive'
      case 'cancelled':
        return 'destructive'
      default:
        return 'secondary'
    }
  }

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'addition':
        return <PlusCircle className="h-4 w-4 text-green-500" />
      case 'subtraction':
        return <MinusCircle className="h-4 w-4 text-red-500" />
      default:
        return <Package className="h-4 w-4" />
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">Stock Adjustments</h1>
          <p className="text-muted-foreground">Manage stock level adjustments</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditingAdjustment(null)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Stock Adjustment
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                {editingAdjustment ? 'Edit Stock Adjustment' : 'Add New Stock Adjustment'}
              </DialogTitle>
            </DialogHeader>
            <StockAdjustmentForm 
              adjustment={editingAdjustment} 
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
                placeholder="Search stock adjustments..."
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
                <TableHead>Adjustment Number</TableHead>
                <TableHead>Warehouse</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Reason</TableHead>
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
              ) : adjustments.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center">
                    No stock adjustments found
                  </TableCell>
                </TableRow>
              ) : (
                adjustments.map((adjustment: any) => (
                  <TableRow key={adjustment.id}>
                    <TableCell className="font-medium">{adjustment.adjustment_number}</TableCell>
                    <TableCell>{adjustment.warehouse?.name || '-'}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        {getTypeIcon(adjustment.adjustment_type)}
                        <span className="capitalize">{adjustment.adjustment_type}</span>
                      </div>
                    </TableCell>
                    <TableCell>{new Date(adjustment.date).toLocaleDateString()}</TableCell>
                    <TableCell>{adjustment.reason}</TableCell>
                    <TableCell>
                      <Badge variant={getStatusBadgeVariant(adjustment.status)}>
                        {adjustment.status.charAt(0).toUpperCase() + adjustment.status.slice(1)}
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
                          <DropdownMenuItem onClick={() => handleEdit(adjustment)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                          </DropdownMenuItem>
                          {adjustment.status === 'pending' && (
                            <DropdownMenuItem onClick={() => handleStatusAction(adjustment)}>
                              <CheckCircle className="mr-2 h-4 w-4" />
                              Approve
                            </DropdownMenuItem>
                          )}
                          {adjustment.status === 'pending' && (
                            <DropdownMenuItem onClick={() => handleStatusAction(adjustment)}>
                              <XCircle className="mr-2 h-4 w-4" />
                              Reject
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuItem onClick={() => handleDelete(adjustment.id)}>
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