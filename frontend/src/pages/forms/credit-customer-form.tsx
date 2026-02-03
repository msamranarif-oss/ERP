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
import { creditCustomersApi, customersApi } from '@/lib/api'

const formSchema = z.object({
  customer_id: z.string().min(1, 'Customer is required'),
  credit_limit: z.string().min(1, 'Credit limit is required'),
  interest_rate: z.string().min(1, 'Interest rate is required'),
  grace_period_days: z.string().min(1, 'Grace period is required'),
  late_fee_percent: z.string().min(1, 'Late fee percent is required'),
  max_installments: z.string().min(1, 'Max installments is required'),
  notes: z.string().optional(),
})

interface CreditCustomerFormProps {
  customer?: any
  onClose: () => void
  onSuccess: () => void
}

export function CreditCustomerForm({ customer, onClose, onSuccess }: CreditCustomerFormProps) {
  const { data: customersData } = useQuery({
    queryKey: ['customers'],
    queryFn: () => customersApi.getAll({ per_page: 100 }),
  })

  const customers = customersData?.data?.data || []

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      customer_id: customer?.customer_id?.toString() || '',
      credit_limit: customer?.credit_limit ? customer.credit_limit.toString() : '',
      interest_rate: customer?.interest_rate ? customer.interest_rate.toString() : '',
      grace_period_days: customer?.grace_period_days ? customer.grace_period_days.toString() : '',
      late_fee_percent: customer?.late_fee_percent ? customer.late_fee_percent.toString() : '',
      max_installments: customer?.max_installments ? customer.max_installments.toString() : '',
      notes: customer?.notes || '',
    },
  })

  const createMutation = useMutation({
    mutationFn: (data: any) => creditCustomersApi.create(data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: any) => creditCustomersApi.update(customer.id, data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const onSubmit = async (values: z.infer<typeof formSchema>) => {
    const data = {
      ...values,
      credit_limit: parseFloat(values.credit_limit),
      interest_rate: parseFloat(values.interest_rate),
      grace_period_days: parseInt(values.grace_period_days),
      late_fee_percent: parseFloat(values.late_fee_percent),
      max_installments: parseInt(values.max_installments),
    }

    if (customer) {
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
            <CardTitle>Credit Customer Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <FormField
              control={form.control}
              name="customer_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Customer *</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select a customer" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {customers.map((customer: any) => (
                        <SelectItem key={customer.id} value={customer.id.toString()}>
                          {customer.name} - {customer.email}
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
                name="credit_limit"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Credit Limit *</FormLabel>
                    <FormControl>
                      <Input type="number" step="0.01" placeholder="0.00" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="interest_rate"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Interest Rate (%) *</FormLabel>
                    <FormControl>
                      <Input type="number" step="0.01" placeholder="0.00" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <FormField
                control={form.control}
                name="grace_period_days"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Grace Period (Days) *</FormLabel>
                    <FormControl>
                      <Input type="number" placeholder="0" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="late_fee_percent"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Late Fee (%) *</FormLabel>
                    <FormControl>
                      <Input type="number" step="0.01" placeholder="0.00" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="max_installments"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Max Installments *</FormLabel>
                    <FormControl>
                      <Input type="number" placeholder="0" {...field} />
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
                    <Textarea placeholder="Additional notes about the credit customer..." {...field} />
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
            {customer ? 'Update' : 'Create'} Credit Customer
          </Button>
        </div>
      </form>
    </Form>
  )
}