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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Plus, Minus } from 'lucide-react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { saleReturnsApi, salesApi, productsApi } from '@/lib/api'

const formSchema = z.object({
  sale_id: z.string().min(1, 'Sale is required'),
  reason: z.string().min(1, 'Reason is required'),
  refund_amount: z.string().min(1, 'Refund amount is required'),
  refund_method: z.string().min(1, 'Refund method is required'),
  notes: z.string().optional(),
  items: z.array(z.object({
    product_id: z.string().min(1, 'Product is required'),
    quantity: z.number().min(1, 'Quantity must be greater than 0'),
    unit_price: z.number().min(0, 'Unit price must be at least 0'),
    return_reason: z.string().min(1, 'Return reason is required'),
  })).min(1, 'At least one item is required'),
})

interface SaleReturnFormProps {
  saleReturn?: any
  onClose: () => void
  onSuccess: () => void
}

export function SaleReturnForm({ saleReturn, onClose, onSuccess }: SaleReturnFormProps) {
  const [items, setItems] = useState<any[]>(saleReturn?.items || [{ product_id: '', quantity: 1, unit_price: 0, return_reason: '' }])

  const { data: salesData } = useQuery({
    queryKey: ['sales'],
    queryFn: () => salesApi.getAll({ per_page: 100 }),
  })

  const { data: productsData } = useQuery({
    queryKey: ['products'],
    queryFn: () => productsApi.getAll({ per_page: 100 }),
  })

  const sales = salesData?.data?.data || []
  const products = productsData?.data?.data || []

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      sale_id: saleReturn?.sale_id?.toString() || '',
      reason: saleReturn?.reason || '',
      refund_amount: saleReturn?.refund_amount ? saleReturn.refund_amount.toString() : '',
      refund_method: saleReturn?.refund_method || 'cash',
      notes: saleReturn?.notes || '',
      items: items,
    },
  })

  useEffect(() => {
    if (saleReturn) {
      form.reset({
        sale_id: saleReturn.sale_id?.toString() || '',
        reason: saleReturn.reason || '',
        refund_amount: saleReturn.refund_amount ? saleReturn.refund_amount.toString() : '',
        refund_method: saleReturn.refund_method || 'cash',
        notes: saleReturn.notes || '',
        items: saleReturn.items || [{ product_id: '', quantity: 1, unit_price: 0, return_reason: '' }],
      })
      setItems(saleReturn.items || [{ product_id: '', quantity: 1, unit_price: 0, return_reason: '' }])
    }
  }, [saleReturn, form])

  const createMutation = useMutation({
    mutationFn: (data: any) => saleReturnsApi.create(data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: any) => saleReturnsApi.update(saleReturn.id, data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const onSubmit = async (values: z.infer<typeof formSchema>) => {
    const data = {
      ...values,
      refund_amount: parseFloat(values.refund_amount),
      items: items,
    }

    if (saleReturn) {
      updateMutation.mutate(data)
    } else {
      createMutation.mutate(data)
    }
  }

  const addItem = () => {
    setItems([...items, { product_id: '', quantity: 1, unit_price: 0, return_reason: '' }])
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
            <CardTitle>Return Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <FormField
              control={form.control}
              name="sale_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Sale *</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select a sale" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {sales.map((sale: any) => (
                        <SelectItem key={sale.id} value={sale.id.toString()}>
                          {sale.sale_number} - {sale.customer?.name || 'Walk-in Customer'}
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
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Reason *</FormLabel>
                  <FormControl>
                    <Input placeholder="Reason for return" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="refund_amount"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Refund Amount *</FormLabel>
                    <FormControl>
                      <Input type="number" step="0.01" placeholder="0.00" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="refund_method"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Refund Method *</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Select refund method" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                        <SelectItem value="credit_account">Credit Account</SelectItem>
                      </SelectContent>
                    </Select>
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
                    <Textarea placeholder="Additional notes about the return..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Returned Items</CardTitle>
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
                                {product.name}
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
                    name={`items.${index}.return_reason`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Return Reason *</FormLabel>
                        <FormControl>
                          <Input 
                            placeholder="Reason for return..." 
                            value={item.return_reason}
                            onChange={(e) => updateItem(index, 'return_reason', e.target.value)}
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

        <div className="flex justify-end gap-4">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={createMutation.isLoading || updateMutation.isLoading}>
            {saleReturn ? 'Update' : 'Create'} Sale Return
          </Button>
        </div>
      </form>
    </Form>
  )
}