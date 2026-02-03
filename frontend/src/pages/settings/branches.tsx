import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { DataTable } from '@/components/ui/data-table'
import { useToast } from '@/hooks/use-toast'
import { Plus, MapPin, Phone, Mail, Building2, MoreHorizontal, CheckCircle2, XCircle, Code } from 'lucide-react'

interface Branch {
  id: string
  name: string
  code: string
  address: string
  phone?: string
  email?: string
  is_active: boolean
  manager_name?: string
  manager_phone?: string
  created_at: string
}

export function BranchesPage() {
  const [branches, setBranches] = useState<Branch[]>([])
  const [loading, setLoading] = useState(true)
  const { toast } = useToast()

  useEffect(() => {
    fetchBranches()
  }, [])

  const fetchBranches = async () => {
    try {
      // Replace with actual API call
      const mockBranches: Branch[] = [
        {
          id: '1',
          name: 'Main Branch',
          code: 'MAIN',
          address: '123 Main Street, New York, NY 10001',
          phone: '+1234567890',
          email: 'main@company.com',
          is_active: true,
          manager_name: 'John Smith',
          manager_phone: '+1234567890',
          created_at: '2023-01-15T10:30:00Z'
        },
        {
          id: '2',
          name: 'Downtown Branch',
          code: 'DTWN',
          address: '456 Downtown Ave, New York, NY 10002',
          phone: '+1987654321',
          email: 'downtown@company.com',
          is_active: true,
          manager_name: 'Jane Doe',
          manager_phone: '+1987654321',
          created_at: '2023-02-20T14:45:00Z'
        },
        {
          id: '3',
          name: 'Westside Branch',
          code: 'WEST',
          address: '789 West Side Blvd, New York, NY 10003',
          phone: '+1555123456',
          email: 'westside@company.com',
          is_active: false,
          manager_name: 'Bob Johnson',
          manager_phone: '+1555123456',
          created_at: '2023-03-10T09:15:00Z'
        },
      ]
      setBranches(mockBranches)
      setLoading(false)
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to load branches',
        variant: 'destructive',
      })
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Page Header - Professional Enterprise */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Branch Locations</h1>
          <p className="text-slate-500">
            Manage your physical points of sale and operational hubs.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2">
            <Plus className="h-4 w-4" />
            Add New Location
          </Button>
        </div>
      </div>

      {/* Branches Table */}
      <div className="card-enterprise overflow-hidden border-slate-200 shadow-sm">
        <DataTable
          columns={[
            {
              accessorKey: 'name',
              header: 'Branch Name',
              cell: ({ row }) => (
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 border border-blue-100">
                    <Building2 className="h-4 w-4" />
                  </div>
                  <div>
                    <div className="font-bold text-slate-900">{row.original.name}</div>
                    <div className="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">ID: {row.original.code}</div>
                  </div>
                </div>
              ),
            },
            {
              accessorKey: 'address',
              header: 'Physical Location',
              cell: ({ row }) => (
                <div className="flex flex-col max-w-[250px]">
                  <div className="flex items-start gap-1.5 text-slate-600 text-sm">
                    <MapPin className="h-3.5 w-3.5 mt-0.5 text-slate-400 shrink-0" />
                    <span>{row.original.address}</span>
                  </div>
                </div>
              ),
            },
            {
              accessorKey: 'manager_name',
              header: 'Branch Manager',
              cell: ({ row }) => (
                <div className="flex flex-col">
                  <div className="text-sm font-semibold text-slate-700">{row.original.manager_name || 'Unassigned'}</div>
                  {row.original.manager_phone && (
                    <div className="text-[11px] text-slate-400">{row.original.manager_phone}</div>
                  )}
                </div>
              ),
            },
            {
              accessorKey: 'is_active',
              header: 'Status',
              cell: ({ row }) => (
                row.original.is_active ? (
                  <div className="flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-1 rounded-full text-xs font-bold w-fit">
                    <CheckCircle2 className="h-3 w-3" />
                    OPERATIONAL
                  </div>
                ) : (
                  <div className="flex items-center gap-1.5 text-slate-400 bg-slate-50 px-2 py-1 rounded-full text-xs font-bold w-fit">
                    <XCircle className="h-3 w-3" />
                    INACTIVE
                  </div>
                )
              ),
            },
            {
              id: 'actions',
              cell: () => (
                <Button variant="ghost" size="icon" className="h-8 w-8 text-slate-400 hover:text-slate-900 hover:bg-slate-100">
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              ),
            },
          ]}
          data={branches}
          loading={loading}
        />
      </div>
    </div>
  )
}