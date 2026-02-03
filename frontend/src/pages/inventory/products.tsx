import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, Box, PackagePlus, MoreHorizontal, AlertTriangle, CheckCircle2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
import { productsApi } from '@/lib/api'
import { ProductForm } from './forms/product-form'

import { ColumnDef } from '@tanstack/react-table'

export type Product = {
  id: number
  name: string
  sku: string
  image?: string
  category?: { name: string }
  selling_price: number | string
  available_stock: number
  is_active: boolean
}

export function ProductsPage() {
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)

  const queryClient = useQueryClient()

  // Note: Local filtering/paging is handled by the shared DataTable component via its internal state
  // We're just providing the initial data fetch here.
  const { data: productsData, isLoading, refetch } = useQuery({
    queryKey: ['products'],
    queryFn: () => productsApi.getAll({ per_page: 100 }), // Fetch a larger batch for better local searching in DataTable
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => productsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this product?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (product: Product) => {
    setEditingProduct(product)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingProduct(null)
  }

  const products: Product[] = productsData?.data?.data || []

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Product Catalog</h1>
          <p className="text-slate-500">Maintain and monitor your global list of products and digital assets.</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2" onClick={() => setEditingProduct(null)}>
              <PackagePlus className="h-4 w-4" />
              Add New Product
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-xl font-bold text-slate-900">
                {editingProduct ? 'Update Product Details' : 'Register New Product'}
              </DialogTitle>
            </DialogHeader>
            <ProductForm
              product={editingProduct}
              onClose={handleDialogClose}
              onSuccess={refetch}
            />
          </DialogContent>
        </Dialog>
      </div>

      {/* Product List Surface */}
      <div className="card-enterprise border-slate-200 shadow-sm overflow-hidden">
        <DataTable<Product, unknown>
          columns={[
            {
              accessorKey: 'name',
              header: 'Product Info',
              cell: ({ row }) => (
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-50 border border-slate-100 overflow-hidden shrink-0">
                    {row.original.image ? (
                      <img src={row.original.image} alt="" className="h-full w-full object-cover" />
                    ) : (
                      <Box className="h-5 w-5 text-slate-300" />
                    )}
                  </div>
                  <div>
                    <div className="font-bold text-slate-900 leading-none">{row.original.name}</div>
                    <div className="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tighter">SKU: {row.original.sku}</div>
                  </div>
                </div>
              ),
            },
            {
              accessorKey: 'category',
              header: 'Category',
              cell: ({ row }) => (
                <div className="text-sm text-slate-600 font-medium">
                  {row.original.category?.name || <span className="text-slate-300">Uncategorized</span>}
                </div>
              ),
            },
            {
              accessorKey: 'price',
              header: 'Selling Price',
              cell: ({ row }) => (
                <div className="font-bold text-slate-900">
                  ${parseFloat(String(row.original.selling_price)).toFixed(2)}
                </div>
              ),
            },
            {
              accessorKey: 'available_stock',
              header: 'Inventory',
              cell: ({ row }) => {
                const stock = row.original.available_stock || 0
                const isLow = stock < 10
                return (
                  <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-1.5">
                      <span className={`text-sm font-black ${isLow ? 'text-orange-600' : 'text-slate-900'}`}>{stock}</span>
                      <span className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Units</span>
                    </div>
                    {isLow && (
                      <div className="flex items-center gap-1 text-[9px] font-black text-orange-600 uppercase tracking-widest">
                        <AlertTriangle className="h-2.5 w-2.5" /> Low Stock
                      </div>
                    )}
                  </div>
                )
              },
            },
            {
              accessorKey: 'is_active',
              header: 'Status',
              cell: ({ row }) => (
                row.original.is_active ? (
                  <div className="flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-0.5 rounded-full text-[10px] font-black w-fit uppercase border border-green-100">
                    <CheckCircle2 className="h-3 w-3" /> Published
                  </div>
                ) : (
                  <div className="flex items-center gap-1.5 text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full text-[10px] font-black w-fit uppercase border border-slate-100">
                    Draft
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
                  <DropdownMenuContent align="end" className="w-40 border-slate-200 shadow-enterprise">
                    <DropdownMenuItem onClick={() => handleEdit(row.original)} className="gap-2 focus:bg-blue-50 focus:text-blue-700 cursor-pointer">
                      <Edit className="h-4 w-4" /> Edit Details
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => handleDelete(row.original.id)} className="gap-2 focus:bg-red-50 focus:text-red-700 cursor-pointer text-red-600">
                      <Trash2 className="h-4 w-4" /> Remove Product
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              ),
            },
          ]}
          data={products}
          loading={isLoading}
        />
      </div>
    </div>
  )
}
