import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit, Trash2, FileText, Calendar, DollarSign, CheckCircle, XCircle, MoreHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
import { DataTable } from '@/components/ui/data-table'
import { journalEntriesApi, accountsApi } from '@/lib/api'
import { JournalEntryForm } from '../forms/journal-entry-form'

interface JournalEntry {
  id: number
  entry_number: string
  date: string
  reference?: string
  description: string
  total_debit: number
  total_credit: number
  status: string
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export function JournalEntriesPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingEntry, setEditingEntry] = useState(null)
  const [itemsPerPage, setItemsPerPage] = useState(10)
  
  const queryClient = useQueryClient()

  const { data: entriesData, isLoading, refetch } = useQuery({
    queryKey: ['journalEntries', { search, page: currentPage, per_page: itemsPerPage }],
    queryFn: () => journalEntriesApi.getAll({ search, page: currentPage, per_page: itemsPerPage }),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => journalEntriesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journalEntries'] })
    },
  })

  const postMutation = useMutation({
    mutationFn: (id: number) => journalEntriesApi.post(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journalEntries'] })
    },
  })

  const voidMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => journalEntriesApi.void(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journalEntries'] })
    },
  })

  const handleDelete = (id: number) => {
    if (window.confirm('Are you sure you want to delete this journal entry?')) {
      deleteMutation.mutate(id)
    }
  }

  const handleEdit = (entry: any) => {
    setEditingEntry(entry)
    setIsDialogOpen(true)
  }

  const handleDialogClose = () => {
    setIsDialogOpen(false)
    setEditingEntry(null)
  }

  const handleStatusAction = (entry: any) => {
    if (entry.status === 'draft') {
      if (window.confirm('Are you sure you want to post this journal entry?')) {
        postMutation.mutate(entry.id)
      }
    } else if (entry.status === 'posted') {
      const reason = prompt('Enter reason for voiding the journal entry:')
      if (reason) {
        voidMutation.mutate({ id: entry.id, data: { reason } })
      }
    }
  }

  const entries = entriesData?.data?.data || []
  const paginationMeta: PaginationMeta = entriesData?.data?.meta || {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0
  }

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'draft':
        return 'secondary'
      case 'posted':
        return 'success'
      case 'voided':
        return 'destructive'
      default:
        return 'secondary'
    }
  }

  return (
    <div className="space-y-6">
      <div className="page-header">
        <div>
          <h1 className="page-title">Journal Entries</h1>
          <p className="page-description">Record and manage journal entries</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => setEditingEntry(null)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Journal Entry
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                {editingEntry ? 'Edit Journal Entry' : 'Add New Journal Entry'}
              </DialogTitle>
            </DialogHeader>
            <JournalEntryForm 
              entry={editingEntry} 
              onClose={handleDialogClose} 
              onSuccess={refetch}
            />
          </DialogContent>
        </Dialog>
      </div>

      <div className="card-surface">
        <DataTable
          columns={[
            {
              accessorKey: 'entry_number',
              header: 'Entry #',
              cell: ({ row }) => (
                <div className="font-medium">{row.original.entry_number}</div>
              ),
            },
            {
              accessorKey: 'date',
              header: 'Date',
              cell: ({ row }) => (
                <div>{new Date(row.original.date).toLocaleDateString()}</div>
              ),
            },
            {
              accessorKey: 'reference',
              header: 'Reference',
              cell: ({ row }) => (
                <div>{row.original.reference || '-'}</div>
              ),
            },
            {
              accessorKey: 'description',
              header: 'Description',
              cell: ({ row }) => (
                <div className="max-w-xs truncate">{row.original.description}</div>
              ),
            },
            {
              accessorKey: 'total_debit',
              header: 'Debit Total',
              cell: ({ row }) => (
                <div className="font-medium">${row.original.total_debit?.toFixed(2)}</div>
              ),
            },
            {
              accessorKey: 'total_credit',
              header: 'Credit Total',
              cell: ({ row }) => (
                <div className="font-medium">${row.original.total_credit?.toFixed(2)}</div>
              ),
            },
            {
              accessorKey: 'status',
              header: 'Status',
              cell: ({ row }) => (
                <Badge variant={getStatusBadgeVariant(row.original.status)}>
                  {row.original.status.charAt(0).toUpperCase() + row.original.status.slice(1)}
                </Badge>
              ),
            },
            {
              id: 'actions',
              cell: ({ row }) => (
                <div className="text-right">
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon" className="h-8 w-8">
                        <MoreHorizontal className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem onClick={() => handleEdit(row.original)}>
                        <Edit className="mr-2 h-4 w-4" />
                        Edit
                      </DropdownMenuItem>
                      {row.original.status === 'draft' && (
                        <DropdownMenuItem onClick={() => handleStatusAction(row.original)}>
                          <CheckCircle className="mr-2 h-4 w-4" />
                          Post
                        </DropdownMenuItem>
                      )}
                      {row.original.status === 'posted' && (
                        <DropdownMenuItem 
                          onClick={() => handleStatusAction(row.original)}
                          className="text-destructive"
                        >
                          <XCircle className="mr-2 h-4 w-4" />
                          Void
                        </DropdownMenuItem>
                      )}
                      {row.original.status === 'draft' && (
                        <DropdownMenuItem 
                          onClick={() => handleDelete(row.original.id)}
                          className="text-destructive"
                        >
                          <Trash2 className="mr-2 h-4 w-4" />
                          Delete
                        </DropdownMenuItem>
                      )}
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              ),
            },
          ]}
          data={entries as JournalEntry[]}
          loading={isLoading}
          pagination={paginationMeta.last_page > 1 ? {
            currentPage: paginationMeta.current_page,
            totalPages: paginationMeta.last_page,
            totalItems: paginationMeta.total,
            itemsPerPage: paginationMeta.per_page,
            from: paginationMeta.from,
            to: paginationMeta.to,
            onPageChange: setCurrentPage
          } : undefined}
        />
      </div>
    </div>
  )
}