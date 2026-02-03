import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, Search, User, CreditCard, AlertTriangle, CheckCircle } from 'lucide-react'
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
import { creditCustomersApi, customersApi } from '@/lib/api'
import { CreditCustomerForm } from '../forms/credit-customer-form'
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
  PaginationInfo,
  usePagination
} from '@/components/ui/pagination'

interface CreditCustomer {
  id: number
  customer?: {
    name: string
    email: string
  }
  credit_limit?: number
  interest_rate: number
  status: string
  is_verified: boolean
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export function CreditCustomersPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingCustomer, setEditingCustomer] = useState(null)
  const [itemsPerPage, setItemsPerPage] = useState(10)
  
  const queryClient = useQueryClient()

  const { data: customersData, isLoading, refetch } = useQuery({
    queryKey: ['creditCustomers', { search, page: currentPage, per_page: itemsPerPage }],
    queryFn: () => creditCustomersApi.getAll({ search, page: currentPage, per_page: itemsPerPage }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => creditCustomersApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['creditCustomers'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this credit customer?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (customer: any) => {
    setEditingCustomer(customer)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingCustomer(null)
  }

  const customers = customersData?.data?.data || []
  const paginationMeta: PaginationMeta = customersData?.data?.meta || {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0
  }
  
  // Generate pagination items
  const paginationItems = usePagination({
    currentPage: paginationMeta.current_page,
    totalPages: paginationMeta.last_page,
    siblingCount: 1
  })

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'active':
        return 'success'
      case 'inactive':
        return 'secondary'
      case 'suspended':
        return 'destructive'
      default:
        return 'secondary'
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">Credit Customers</h1>
          <p className="text-muted-foreground">Manage credit accounts and customers</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditingCustomer(null)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Credit Customer
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                {editingCustomer ? 'Edit Credit Customer' : 'Add New Credit Customer'}
              </DialogTitle>
            </DialogHeader>
            <CreditCustomerForm 
              customer={editingCustomer} 
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
                placeholder="Search credit customers..."
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
                <TableHead>Customer</TableHead>
                <TableHead>Credit Limit</TableHead>
                <TableHead>Interest Rate</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Verified</TableHead>
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
              ) : customers.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center">
                    No credit customers found
                  </TableCell>
                </TableRow>
              ) : (
                customers.map((customer: any) => (
                  <TableRow key={customer.id}>
                    <TableCell className="font-medium">
                      {customer.customer?.name}
                      <div className="text-sm text-muted-foreground">{customer.customer?.email}</div>
                    </TableCell>
                    <TableCell>${customer.credit_limit?.toFixed(2)}</TableCell>
                    <TableCell>{customer.interest_rate}%</TableCell>
                    <TableCell>
                      <Badge variant={getStatusBadgeVariant(customer.status)}>
                        {customer.status.charAt(0).toUpperCase() + customer.status.slice(1)}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {customer.is_verified ? (
                        <CheckCircle className="h-4 w-4 text-green-500" />
                      ) : (
                        <AlertTriangle className="h-4 w-4 text-yellow-500" />
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            Actions
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => handleEdit(customer)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => handleDelete(customer.id)}>
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
          
          {/* Standardized Pagination */}
          {paginationMeta.last_page > 1 && (
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4 mt-4 bg-slate-50/50 p-4 rounded-lg border border-slate-100">
              <PaginationInfo
                currentPage={paginationMeta.current_page}
                totalPages={paginationMeta.last_page}
                totalItems={paginationMeta.total}
                itemsPerPage={paginationMeta.per_page}
                from={paginationMeta.from}
                to={paginationMeta.to}
              />
              
              <Pagination>
                <PaginationContent>
                  <PaginationItem>
                    <PaginationPrevious 
                      onClick={() => setCurrentPage(paginationMeta.current_page - 1)}
                      disabled={paginationMeta.current_page === 1}
                    />
                  </PaginationItem>
                  
                  {paginationItems.map((item, index) => (
                    item === "..." ? (
                      <PaginationItem key={`ellipsis-${index}`}>
                        <PaginationEllipsis />
                      </PaginationItem>
                    ) : (
                      <PaginationItem key={item}>
                        <PaginationLink
                          onClick={() => setCurrentPage(Number(item))}
                          isActive={Number(item) === paginationMeta.current_page}
                        >
                          {item}
                        </PaginationLink>
                      </PaginationItem>
                    )
                  ))}
                  
                  <PaginationItem>
                    <PaginationNext 
                      onClick={() => setCurrentPage(paginationMeta.current_page + 1)}
                      disabled={paginationMeta.current_page === paginationMeta.last_page}
                    />
                  </PaginationItem>
                </PaginationContent>
              </Pagination>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}