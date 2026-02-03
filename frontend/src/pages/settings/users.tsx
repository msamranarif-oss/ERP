import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { DataTable } from '@/components/ui/data-table'
import { useToast } from '@/hooks/use-toast'
import { Plus, UserPlus, Shield, Building2, MoreHorizontal, UserCheck, UserX } from 'lucide-react'

interface User {
  id: string
  name: string
  email: string
  phone?: string
  role: {
    id: string
    name: string
  }
  branch: {
    id: string
    name: string
  }
  is_active: boolean
  created_at: string
}

export function UsersPage() {
  const [users, setUsers] = useState<User[]>([])
  const [loading, setLoading] = useState(true)
  const { toast } = useToast()

  useEffect(() => {
    fetchUsers()
  }, [])

  const fetchUsers = async () => {
    try {
      // Replace with actual API call
      const mockUsers: User[] = [
        {
          id: '1',
          name: 'John Doe',
          email: 'john@example.com',
          phone: '+1234567890',
          role: { id: '1', name: 'Admin' },
          branch: { id: '1', name: 'Main Branch' },
          is_active: true,
          created_at: '2023-01-15T10:30:00Z'
        },
        {
          id: '2',
          name: 'Jane Smith',
          email: 'jane@example.com',
          phone: '+1987654321',
          role: { id: '2', name: 'Manager' },
          branch: { id: '2', name: 'Downtown Branch' },
          is_active: true,
          created_at: '2023-02-20T14:45:00Z'
        },
      ]
      setUsers(mockUsers)
      setLoading(false)
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to load users',
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
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">User Management</h1>
          <p className="text-slate-500">
            Control access, roles, and branch assignments for your team.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button className="bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2">
            <UserPlus className="h-4 w-4" />
            Add New User
          </Button>
        </div>
      </div>

      {/* Main Content Area */}
      <div className="card-enterprise overflow-hidden border-slate-200 shadow-sm">
        <DataTable
          columns={[
            {
              accessorKey: 'name',
              header: 'Name',
              cell: ({ row }) => (
                <div className="flex items-center gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 font-bold border border-blue-100 text-xs text-uppercase">
                    {row.original.name.charAt(0)}
                  </div>
                  <div className="font-semibold text-slate-900">{row.original.name}</div>
                </div>
              ),
            },
            {
              accessorKey: 'email',
              header: 'Email Address',
              cell: ({ row }) => (
                <div className="text-slate-500">{row.original.email}</div>
              ),
            },
            {
              accessorKey: 'role',
              header: 'System Role',
              cell: ({ row }) => (
                <div className="flex items-center gap-1.5 font-medium text-slate-700">
                  <Shield className="h-3.5 w-3.5 text-slate-400" />
                  <span>{row.original.role.name}</span>
                </div>
              ),
            },
            {
              accessorKey: 'branch',
              header: 'Primary Branch',
              cell: ({ row }) => (
                <div className="flex items-center gap-1.5 text-slate-600">
                  <Building2 className="h-3.5 w-3.5 text-slate-400" />
                  <span>{row.original.branch.name}</span>
                </div>
              ),
            },
            {
              accessorKey: 'is_active',
              header: 'Account Status',
              cell: ({ row }) => (
                row.original.is_active ? (
                  <div className="flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-1 rounded-full text-xs font-bold w-fit">
                    <UserCheck className="h-3 w-3" />
                    ACTIVE
                  </div>
                ) : (
                  <div className="flex items-center gap-1.5 text-slate-400 bg-slate-50 px-2 py-1 rounded-full text-xs font-bold w-fit">
                    <UserX className="h-3 w-3" />
                    DEACTIVATED
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
          data={users}
          loading={loading}
        />
      </div>
    </div>
  )
}