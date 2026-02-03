import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { DataTable } from '@/components/ui/data-table'
import { useToast } from '@/hooks/use-toast'
import { Plus, Shield, ShieldCheck, ShieldAlert, MoreHorizontal, Calendar, Info } from 'lucide-react'

interface Role {
  id: string
  name: string
  guard_name: string
  description?: string
  permissions: string[]
  created_at: string
}

export function RolesPage() {
  const [roles, setRoles] = useState<Role[]>([])
  const [loading, setLoading] = useState(true)
  const { toast } = useToast()

  useEffect(() => {
    fetchRoles()
  }, [])

  const fetchRoles = async () => {
    try {
      // Replace with actual API call
      const mockRoles: Role[] = [
        {
          id: '1',
          name: 'Admin',
          guard_name: 'sanctum',
          description: 'Full system access across all branches and modules.',
          permissions: ['manage-users', 'manage-products', 'manage-finances'],
          created_at: '2023-01-15T10:30:00Z'
        },
        {
          id: '2',
          name: 'Manager',
          guard_name: 'sanctum',
          description: 'Branch-level management and stock control.',
          permissions: ['view-reports', 'manage-inventory'],
          created_at: '2023-02-20T14:45:00Z'
        },
        {
          id: '3',
          name: 'Cashier',
          guard_name: 'sanctum',
          description: 'Standard Point of Sale operations and invoicing.',
          permissions: ['create-sales', 'process-payments'],
          created_at: '2023-03-10T09:15:00Z'
        },
      ]
      setRoles(mockRoles)
      setLoading(false)
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to load roles',
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
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Security Roles</h1>
          <p className="text-slate-500">
            Define access levels and fine-tune system permissions for each role.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2">
            <Plus className="h-4 w-4" />
            Create Custom Role
          </Button>
        </div>
      </div>

      {/* Roles Content */}
      <div className="card-enterprise overflow-hidden border-slate-200 shadow-sm">
        <DataTable
          columns={[
            {
              accessorKey: 'name',
              header: 'Role Name',
              cell: ({ row }) => (
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 border border-blue-100">
                    <ShieldCheck className="h-4 w-4" />
                  </div>
                  <div className="font-bold text-slate-900">{row.original.name}</div>
                </div>
              ),
            },
            {
              accessorKey: 'description',
              header: 'Capability Description',
              cell: ({ row }) => (
                <div className="max-w-[400px] text-sm text-slate-500 leading-relaxed">
                  {row.original.description}
                </div>
              ),
            },
            {
              accessorKey: 'permissions',
              header: 'Active Permissions',
              cell: ({ row }) => (
                <div className="flex flex-wrap gap-1.5">
                  {row.original.permissions.slice(0, 3).map((permission, idx) => (
                    <Badge key={idx} variant="outline" className="text-[10px] font-bold uppercase tracking-wider border-slate-200 text-slate-500 px-1.5 py-0">
                      {permission.replace('-', ' ')}
                    </Badge>
                  ))}
                  {row.original.permissions.length > 3 && (
                    <Badge variant="outline" className="text-[10px] font-bold border-slate-100 bg-slate-50 text-slate-400">
                      +{row.original.permissions.length - 3}
                    </Badge>
                  )}
                </div>
              ),
            },
            {
              accessorKey: 'created_at',
              header: 'Creation Date',
              cell: ({ row }) => (
                <div className="flex items-center gap-1.5 text-xs text-slate-400">
                  <Calendar className="h-3.5 w-3.5" />
                  {new Date(row.original.created_at).toLocaleDateString()}
                </div>
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
          data={roles}
          loading={loading}
        />
      </div>
    </div>
  )
}