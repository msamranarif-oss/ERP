import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, Search, Package, Tag, Box, Warehouse, FileText, Receipt } from 'lucide-react'
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

interface Supplier {
  id: number
  name: string
  code: string
  email: string
  phone: string
  address: string
  city: string
  country: string
  contact_person: string
  is_active: boolean
  balance: number
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export function SuppliersPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingSupplier, setEditingSupplier] = useState(null)
  const [itemsPerPage, setItemsPerPage] = useState(10)
  
  const queryClient = useQueryClient()

  const { data: suppliersData, isLoading } = useQuery({
    queryKey: ['suppliers', { search, page: currentPage, per_page: itemsPerPage }],
    queryFn: () => ({
      data: {
        data: [
          { id: 1, name: 'ABC Electronics', code: 'SUP001', email: 'contact@abcelectronics.com', phone: '(555) 123-4567', address: '123 Tech St', city: 'San Francisco', country: 'USA', contact_person: 'John Doe', is_active: true, balance: 0 },
          { id: 2, name: 'XYZ Hardware', code: 'SUP002', email: 'orders@xyzhardware.com', phone: '(555) 987-6543', address: '456 Hardware Ave', city: 'Chicago', country: 'USA', contact_person: 'Jane Smith', is_active: true, balance: 0 },
        ] as Supplier[],
        meta: {
          current_page: currentPage,
          last_page: 1,
          per_page: itemsPerPage,
          total: 2,
          from: 1,
          to: 2
        } as PaginationMeta
      }
    }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => {
      // Mock deletion
      return Promise.resolve()
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this supplier?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (supplier: any) => {
    setEditingSupplier(supplier)
    setIsDialogOpen(true)
  }

  const suppliers = suppliersData?.data?.data || []
  const paginationMeta: PaginationMeta = suppliersData?.data?.meta || {
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

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">Suppliers</h1>
          <p className="text-muted-foreground">Manage your product suppliers</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditingSupplier(null)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Supplier
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>
                {editingSupplier ? 'Edit Supplier' : 'Add New Supplier'}
              </DialogTitle>
            </DialogHeader>
            <SupplierForm 
              supplier={editingSupplier} 
              onClose={() => {
                setIsDialogOpen(false)
                setEditingSupplier(null)
              }} 
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
                placeholder="Search suppliers..."
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
                <TableHead>Name</TableHead>
                <TableHead>Code</TableHead>
                <TableHead>Contact Person</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Phone</TableHead>
                <TableHead>City</TableHead>
                <TableHead>Country</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={9} className="text-center">
                    Loading...
                  </TableCell>
                </TableRow>
              ) : suppliers.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={9} className="text-center">
                    No suppliers found
                  </TableCell>
                </TableRow>
              ) : (
                suppliers.map((supplier: any) => (
                  <TableRow key={supplier.id}>
                    <TableCell className="font-medium">{supplier.name}</TableCell>
                    <TableCell>{supplier.code}</TableCell>
                    <TableCell>{supplier.contact_person}</TableCell>
                    <TableCell>{supplier.email}</TableCell>
                    <TableCell>{supplier.phone}</TableCell>
                    <TableCell>{supplier.city}</TableCell>
                    <TableCell>{supplier.country}</TableCell>
                    <TableCell>
                      <span className={`px-2 py-1 rounded-full text-xs ${
                        supplier.is_active 
                          ? 'bg-green-100 text-green-800' 
                          : 'bg-red-100 text-red-800'
                      }`}>
                        {supplier.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            Actions
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => handleEdit(supplier)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => handleDelete(supplier.id)}>
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

function SupplierForm({ supplier, onClose }: { supplier: any, onClose: () => void }) {
  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium mb-1">Name *</label>
        <input 
          type="text" 
          defaultValue={supplier?.name || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          required
        />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Code</label>
        <input 
          type="text" 
          defaultValue={supplier?.code || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Contact Person</label>
        <input 
          type="text" 
          defaultValue={supplier?.contact_person || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Email</label>
        <input 
          type="email" 
          defaultValue={supplier?.email || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Phone</label>
        <input 
          type="text" 
          defaultValue={supplier?.phone || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Address</label>
        <input 
          type="text" 
          defaultValue={supplier?.address || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">City</label>
        <input 
          type="text" 
          defaultValue={supplier?.city || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Country</label>
        <input 
          type="text" 
          defaultValue={supplier?.country || ''} 
          className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        />
      </div>
      <div className="flex items-center space-x-2">
        <input 
          type="checkbox" 
          id="is_active"
          defaultChecked={supplier?.is_active !== false}
          className="rounded"
        />
        <label htmlFor="is_active" className="text-sm">Active</label>
      </div>
      
      <div className="flex justify-end gap-4 pt-4">
        <Button type="button" variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button type="submit">
          {supplier ? 'Update Supplier' : 'Create Supplier'}
        </Button>
      </div>
    </div>
  )
}
