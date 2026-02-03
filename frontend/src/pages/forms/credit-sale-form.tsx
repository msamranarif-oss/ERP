import { useState, useEffect } from 'react'
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
import { Plus, Minus } from 'lucide-react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { creditSalesApi, creditCustomersApi, productsApi } from '@/lib/api'

const formSchema = z.object({
  customer_id: z.string().min(1, 'Customer is required'),
  down_payment: z.string().min(1, 'Down payment is required'),
  loan_amount: z.string().min(1, 'Loan amount is required'),
  interest_rate: z.string().min(1, 'Interest rate is required'),
  number_of_installments: z.string().min(1, 'Number of installments is required'),
  installment_frequency: z.enum(['weekly', 'biweekly', 'monthly', 'quarterly'], {
    required_error: 'Installment frequency is required'
  }),
  first_installment_date: z.string().min(1, 'First installment date is required'),
  items: z.array(z.object({
    product_id: z.string().min(1, 'Product is required'),
    quantity: z.number().min(1, 'Quantity must be greater than 0'),
    unit_price: z.number().min(0, 'Unit price must be at least 0'),
    discount_percent: z.number().optional(),
    discount_amount: z.number().optional(),
  })).min(1, 'At least one item is required'),
  discount_percent: z.string().optional(),
  discount_amount: z.string().optional(),
  tax_percent: z.string().optional(),
  tax_amount: z.string().optional(),
  shipping_cost: z.string().optional(),
  total_amount: z.string().min(1, 'Total amount is required'),
  notes: z.string().optional(),
})

interface CreditSaleFormProps {
  sale?: any
  onClose: () => void
  onSuccess: () => void
}

export function CreditSaleForm({ sale, onClose, onSuccess }: CreditSaleFormProps) {
  const [items, setItems] = useState<any[]>(sale?.items || [{ product_id: '', quantity: 1, unit_price: 0, discount_percent: 0 }])
  const [subTotal, setSubTotal] = useState(0)

  const { data: creditCustomersData } = useQuery({
    queryKey: ['creditCustomers'],
    queryFn: () => creditCustomersApi.getAll({ per_page: 100 }),
  })

  const { data: productsData } = useQuery({
    queryKey: ['products'],
    queryFn: () => productsApi.getAll({ per_page: 100 }),
  })

  const creditCustomers = creditCustomersData?.data?.data || []
  const products = productsData?.data?.data || []

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      customer_id: sale?.customer_id?.toString() || '',
      down_payment: sale?.down_payment ? sale.down_payment.toString() : '',
      loan_amount: sale?.loan_amount ? sale.loan_amount.toString() : '',
      interest_rate: sale?.interest_rate ? sale.interest_rate.toString() : '',
      number_of_installments: sale?.number_of_installments ? sale.number_of_installments.toString() : '',
      installment_frequency: sale?.installment_frequency || 'monthly',
      first_installment_date: sale?.first_installment_date || new Date().toISOString().split('T')[0],
      items: items,
      discount_percent: sale?.discount_percent ? sale.discount_percent.toString() : '',
      discount_amount: sale?.discount_amount ? sale.discount_amount.toString() : '',
      tax_percent: sale?.tax_percent ? sale.tax_percent.toString() : '',
      tax_amount: sale?.tax_amount ? sale.tax_amount.toString() : '',
      shipping_cost: sale?.shipping_cost ? sale.shipping_cost.toString() : '',
      total_amount: sale?.total_amount ? sale.total_amount.toString() : '',
      notes: sale?.notes || '',
    },
  })

  useEffect(() => {
    // Calculate subtotal whenever items change
    const calculatedSubtotal = items.reduce((sum, item) => {
      const itemTotal = item.quantity * item.unit_price
      const discount = item.discount_percent 
        ? itemTotal * (item.discount_percent / 100)
        : item.discount_amount || 0
      return sum + (itemTotal - discount)
    }, 0)
    
    setSubTotal(calculatedSubtotal)
    form.setValue('total_amount', calculatedSubtotal.toString())
  }, [items, form])

  useEffect(() => {
    if (sale) {
      form.reset({
        customer_id: sale.customer_id?.toString() || '',
        down_payment: sale.down_payment ? sale.down_payment.toString() : '',
        loan_amount: sale.loan_amount ? sale.loan_amount.toString() : '',
        interest_rate: sale.interest_rate ? sale.interest_rate.toString() : '',
        number_of_installments: sale.number_of_installments ? sale.number_of_installments.toString() : '',
        installment_frequency: sale.installment_frequency || 'monthly',
        first_installment_date: sale.first_installment_date || new Date().toISOString().split('T')[0],
        items: sale.items || [{ product_id: '', quantity: 1, unit_price: 0, discount_percent: 0 }],
        discount_percent: sale.discount_percent ? sale.discount_percent.toString() : '',
        discount_amount: sale.discount_amount ? sale.discount_amount.toString() : '',
        tax_percent: sale.tax_percent ? sale.tax_percent.toString() : '',
        tax_amount: sale.tax_amount ? sale.tax_amount.toString() : '',
        shipping_cost: sale.shipping_cost ? sale.shipping_cost.toString() : '',
        total_amount: sale.total_amount ? sale.total_amount.toString() : '',
        notes: sale.notes || '',
      })
      setItems(sale.items || [{ product_id: '', quantity: 1, unit_price: 0, discount_percent: 0 }])
    }
  }, [sale, form])

  const createMutation = useMutation({
    mutationFn: (data: any) => creditSalesApi.create(data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: any) => creditSalesApi.update(sale.id, data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const onSubmit = async (values: z.infer<typeof formSchema>) => {
    const data = {
      ...values,
      down_payment: parseFloat(values.down_payment),
      loan_amount: parseFloat(values.loan_amount),
      interest_rate: parseFloat(values.interest_rate),
      number_of_installments: parseInt(values.number_of_installments),
      items: items.map(item => ({
        ...item,
        unit_price: parseFloat(item.unit_price.toString()),
        discount_percent: item.discount_percent ? parseFloat(item.discount_percent.toString()) : 0,
        discount_amount: item.discount_amount ? parseFloat(item.discount_amount.toString()) : 0,
      })),
      discount_percent: values.discount_percent ? parseFloat(values.discount_percent) : 0,
      discount_amount: values.discount_amount ? parseFloat(values.discount_amount) : 0,
      tax_percent: values.tax_percent ? parseFloat(values.tax_percent) : 0,
      tax_amount: values.tax_amount ? parseFloat(values.tax_amount) : 0,
      shipping_cost: values.shipping_cost ? parseFloat(values.shipping_cost) : 0,
      total_amount: parseFloat(values.total_amount),
    }

    if (sale) {
      updateMutation.mutate(data)
    } else {
      createMutation.mutate(data)
    }
  }

  const addItem = () => {
    setItems([...items, { product_id: '', quantity: 1, unit_price: 0, discount_percent: 0 }])
  }

  const removeItem = (index: number) => {
    if (items.length > 1) {
      const newItems = [...items]
      newItems.splice(index, 1)
      setItems(newItems)
      form.setValue('items', newItems)
    }
  }

  const updateItem = (index: number, field: string, value: any) => {
    const newItems = [...items]
    newItems[index] = { ...newItems[index], [field]: value }
    setItems(newItems)
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Credit Sale Information</CardTitle>
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
                        <SelectValue placeholder="Select a credit customer" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {creditCustomers.map((customer: any) => (
                        <SelectItem key={customer.id} value={customer.id.toString()}>
                          {customer.customer?.name} - ${customer.credit_limit?.toFixed(2)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <FormField
                control={form.control}
                name="down_payment"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Down Payment *</FormLabel>
                    <FormControl>
                      <Input type="number" step="0.01" placeholder="0.00" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="loan_amount"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Loan Amount *</FormLabel>
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
                name="number_of_installments"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Number of Installments *</FormLabel>
                    <FormControl>
                      <Input type="number" placeholder="0" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="installment_frequency"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Installment Frequency *</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Select frequency" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="weekly">Weekly</SelectItem>
                        <SelectItem value="biweekly">Bi-weekly</SelectItem>
                        <SelectItem value="monthly">Monthly</SelectItem>
                        <SelectItem value="quarterly">Quarterly</SelectItem>
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="first_installment_date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>First Installment Date *</FormLabel>
                    <FormControl>
                      <Input type="date" {...field} />
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
                    <Textarea placeholder="Additional notes about the credit sale..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Items</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {items.map((item, index) => (
                <div key={index} className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                  <FormField
                    control={form.control}
                    name={`items.${index}.product_id`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Product *</FormLabel>
                        <Select 
                          value={item.product_id} 
                          onValueChange={(value) => updateItem(index, 'product_id', value)}
                        >
                          <FormControl>
                            <SelectTrigger>
                              <SelectValue placeholder="Select a product" />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            {products.map((product: any) => (
                              <SelectItem key={product.id} value={product.id.toString()}>
                                {product.name} - ${product.selling_price?.toFixed(2)}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name={`items.${index}.quantity`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Quantity *</FormLabel>
                        <FormControl>
                          <Input 
                            type="number" 
                            min="1" 
                            value={item.quantity}
                            onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value))}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name={`items.${index}.unit_price`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Unit Price *</FormLabel>
                        <FormControl>
                          <Input 
                            type="number" 
                            min="0" 
                            step="0.01" 
                            value={item.unit_price}
                            onChange={(e) => updateItem(index, 'unit_price', parseFloat(e.target.value))}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name={`items.${index}.discount_percent`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Discount (%)</FormLabel>
                        <FormControl>
                          <Input 
                            type="number" 
                            min="0" 
                            max="100" 
                            step="0.01" 
                            value={item.discount_percent}
                            onChange={(e) => updateItem(index, 'discount_percent', parseFloat(e.target.value))}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <div className="flex gap-2">
                    <Button 
                      type="button" 
                      variant="outline" 
                      size="icon"
                      onClick={() => removeItem(index)}
                      disabled={items.length <= 1}
                    >
                      <Minus className="h-4 w-4" />
                    </Button>
                    {index === items.length - 1 && (
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="icon"
                        onClick={addItem}
                      >
                        <Plus className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Financial Summary</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <div className="flex justify-between">
                <span>Subtotal:</span>
                <span>${subTotal.toFixed(2)}</span>
              </div>
              <div className="flex justify-between">
                <span>Discount:</span>
                <span>${parseFloat(form.watch('discount_amount') || '0').toFixed(2)}</span>
              </div>
              <div className="flex justify-between">
                <span>Tax:</span>
                <span>${parseFloat(form.watch('tax_amount') || '0').toFixed(2)}</span>
              </div>
              <div className="flex justify-between">
                <span>Shipping:</span>
                <span>${parseFloat(form.watch('shipping_cost') || '0').toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-lg font-bold pt-2 border-t">
                <span>Total:</span>
                <span>${subTotal.toFixed(2)}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end gap-4">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={createMutation.isLoading || updateMutation.isLoading}>
            {sale ? 'Update' : 'Create'} Credit Sale
          </Button>
        </div>
      </form>
    </Form>
  )
}