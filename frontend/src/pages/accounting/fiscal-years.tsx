import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, Search, Calendar, Building } from 'lucide-react'
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
import { fiscalYearsApi } from '@/lib/api'
import { FiscalYearForm } from '../forms/fiscal-year-form'

export function FiscalYearsPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingYear, setEditingYear] = useState(null)
  
  const queryClient = useQueryClient()

  const { data: yearsData, isLoading, refetch } = useQuery({
    queryKey: ['fiscalYears', { search, page: currentPage }],
    queryFn: () => fiscalYearsApi.getAll({ search, page: currentPage, per_page: 10 }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => fiscalYearsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['fiscalYears'] })
    },
  })

  const closeMutation = useMutation({
    mutationFn: (id: number) => fiscalYearsApi.close(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['fiscalYears'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this fiscal year?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleClose = (id: number) => {
    if (window.confirm('Are you sure you want to close this fiscal year?')) {
      closeMutation.mutate(id)
    }
  }

  const handleEdit = (year: any) => {
    setEditingYear(year)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingYear(null)
  }

  const years = yearsData?.data?.data || []
  const pagination = yearsData?.data?.meta || {}

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold">Fiscal Years</h1>
          <p className="text-muted-foreground">Manage your fiscal years</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditingYear(null)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Fiscal Year
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                {editingYear ? 'Edit Fiscal Year' : 'Add New Fiscal Year'}
              </DialogTitle>
            </DialogHeader>
            <FiscalYearForm 
              year={editingYear} 
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
                placeholder="Search fiscal years..."
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
                <TableHead>Year</TableHead>
                <TableHead>Start Date</TableHead>
                <TableHead>End Date</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Closed</TableHead>
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
              ) : years.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center">
                    No fiscal years found
                  </TableCell>
                </TableRow>
              ) : (
                years.map((year: any) => (
                  <TableRow key={year.id}>
                    <TableCell className="font-medium">{year.name}</TableCell>
                    <TableCell>{year.year}</TableCell>
                    <TableCell>{new Date(year.start_date).toLocaleDateString()}</TableCell>
                    <TableCell>{new Date(year.end_date).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <Badge variant={year.is_active ? 'success' : 'secondary'}>
                        {year.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant={year.is_closed ? 'destructive' : 'secondary'}>
                        {year.is_closed ? 'Closed' : 'Open'}
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
                          <DropdownMenuItem onClick={() => handleEdit(year)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                          </DropdownMenuItem>
                          {!year.is_closed && (
                            <DropdownMenuItem onClick={() => handleClose(year.id)}>
                              Close Year
                            </DropdownMenuItem>
                          )}
                          {!year.is_closed && !year.is_active && (
                            <DropdownMenuItem 
                              onClick={() => handleDelete(year.id)}
                              className="text-destructive"
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Delete
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