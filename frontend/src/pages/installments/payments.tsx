import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Search, DollarSign, Calendar, User, CreditCard } from 'lucide-react'
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
import { Badge } from '@/components/ui/badge'
import { installmentsApi, creditSalesApi } from '@/lib/api'

export function InstallmentPaymentsPage() {
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [dateFilter, setDateFilter] = useState<{ from?: string; to?: string }>({})
  
  const queryClient = useQueryClient()

  const { data: paymentsData, isLoading } = useQuery({
    queryKey: ['installmentPayments', { search, page: currentPage, ...dateFilter }],
    queryFn: () => installmentsApi.getAll({ 
      search, 
      page: currentPage, 
      per_page: 10,
      date_from: dateFilter.from,
      date_to: dateFilter.to
    }),
  })

  const payments = paymentsData?.data?.data || []
  const pagination = paymentsData?.data?.meta || {}

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Installment Payments</h1>
        <p className="text-muted-foreground">Track and manage installment payments</p>
      </div>

      <Card>
        <CardHeader>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="relative">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search payments..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-8"
              />
            </div>
            <div className="flex gap-2">
              <Input
                type="date"
                value={dateFilter.from || ''}
                onChange={(e) => setDateFilter({ ...dateFilter, from: e.target.value })}
                placeholder="From date"
              />
              <Input
                type="date"
                value={dateFilter.to || ''}
                onChange={(e) => setDateFilter({ ...dateFilter, to: e.target.value })}
                placeholder="To date"
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Installment #</TableHead>
                <TableHead>Credit Sale #</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Due Date</TableHead>
                <TableHead>Total Amount</TableHead>
                <TableHead>Paid Amount</TableHead>
                <TableHead>Remaining</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={8} className="text-center">
                    Loading...
                  </TableCell>
                </TableRow>
              ) : payments.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="text-center">
                    No installment payments found
                  </TableCell>
                </TableRow>
              ) : (
                payments.map((installment: any) => (
                  <TableRow key={installment.id}>
                    <TableCell className="font-medium">#{installment.installment_number}</TableCell>
                    <TableCell>{installment.credit_sale?.credit_sale_number || '-'}</TableCell>
                    <TableCell>{installment.credit_sale?.customer?.customer?.name || '-'}</TableCell>
                    <TableCell>{new Date(installment.due_date).toLocaleDateString()}</TableCell>
                    <TableCell>${installment.total_amount?.toFixed(2)}</TableCell>
                    <TableCell>${installment.paid_amount?.toFixed(2)}</TableCell>
                    <TableCell>${installment.remaining_amount?.toFixed(2)}</TableCell>
                    <TableCell>
                      <Badge variant={
                        installment.status === 'paid' ? 'success' : 
                        installment.status === 'partial' ? 'warning' : 'secondary'
                      }>
                        {installment.status.charAt(0).toUpperCase() + installment.status.slice(1)}
                      </Badge>
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