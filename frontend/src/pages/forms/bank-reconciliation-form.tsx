import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { Button } from '@/components/ui/button'
import { 
  Form, 
  FormControl, 
  FormField, 
  FormItem, 
  FormLabel, 
  FormMessage 
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useQuery, useMutation } from '@tanstack/react-query'
import { bankReconciliationsApi, bankAccountsApi } from '@/lib/api'

const formSchema = z.object({
  bank_account_id: z.string().min(1, 'Bank account is required'),
  statement_date: z.string().min(1, 'Statement date is required'),
  statement_balance: z.string().min(1, 'Statement balance is required'),
  book_balance: z.string().min(1, 'Book balance is required'),
  notes: z.string().optional(),
})

interface BankReconciliationFormProps {
  reconciliation?: any
  onClose: () => void
  onSuccess: () => void
}

export function BankReconciliationForm({ reconciliation, onClose, onSuccess }: BankReconciliationFormProps) {
  const { data: bankAccountsData } = useQuery({
    queryKey: ['bankAccounts'],
    queryFn: () => bankAccountsApi.getAll({ per_page: 100 }),
  })

  const bankAccounts = bankAccountsData?.data?.data || []

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      bank_account_id: reconciliation?.bank_account_id?.toString() || '',
      statement_date: reconciliation?.statement_date || new Date().toISOString().split('T')[0],
      statement_balance: reconciliation?.statement_balance ? reconciliation.statement_balance.toString() : '',
      book_balance: reconciliation?.book_balance ? reconciliation.book_balance.toString() : '',
      notes: reconciliation?.notes || '',
    },
  })

  const createMutation = useMutation({
    mutationFn: (data: any) => bankReconciliationsApi.create(data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: any) => bankReconciliationsApi.update(reconciliation.id, data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const onSubmit = async (values: z.infer<typeof formSchema>) => {
    const data = {
      ...values,
      statement_balance: parseFloat(values.statement_balance),
      book_balance: parseFloat(values.book_balance),
    }

    if (reconciliation) {
      updateMutation.mutate(data)
    } else {
      createMutation.mutate(data)
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Bank Reconciliation Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <FormField
              control={form.control}
              name="bank_account_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Bank Account *</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select a bank account" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {bankAccounts.map((account: any) => (
                        <SelectItem key={account.id} value={account.id.toString()}>
                          {account.name} - {account.bank_name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="statement_date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Statement Date *</FormLabel>
                    <FormControl>
                      <Input type="date" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="statement_balance"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Statement Balance *</FormLabel>
                    <FormControl>
                      <Input type="number" step="0.01" placeholder="0.00" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="book_balance"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Book Balance *</FormLabel>
                    <FormControl>
                      <Input type="number" step="0.01" placeholder="0.00" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="notes"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Notes</FormLabel>
                  <FormControl>
                    <Textarea placeholder="Additional notes about the reconciliation..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        <div className="flex justify-end gap-4">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={createMutation.isLoading || updateMutation.isLoading}>
            {reconciliation ? 'Update' : 'Create'} Reconciliation
          </Button>
        </div>
      </form>
    </Form>
  )
}