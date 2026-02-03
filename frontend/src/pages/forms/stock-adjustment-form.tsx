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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Plus, Minus, Calendar, PlusCircle, MinusCircle } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { warehousesApi, productsApi } from '@/lib/api'

const formSchema = z.object({
  warehouse_id: z.string().min(1, 'Warehouse is required'),
  adjustment_type: z.enum(['addition', 'subtraction'], {
    required_error: 'Adjustment type is required'
  }),
  date: z.string().min(1, 'Date is required'),
  reason: z.string().min(1, 'Reason is required'),
  notes: z.string().optional(),
  items: z.array(z.object({
    product_id: z.string().min(1, 'Product is required'),
    quantity: z.number().min(1, 'Quantity must be greater than 0'),
    unit_cost: z.number().min(0, 'Unit cost must be at least 0'),
    reason: z.string().optional(),
  })).min(1, 'At least one item is required'),
})

interface StockAdjustmentFormProps {
  adjustment?: any
  onClose: () => void
  onSuccess: () => void
}

export function StockAdjustmentForm({ adjustment, onClose, onSuccess }: StockAdjustmentFormProps) {
  const [items, setItems] = useState<any[]>(adjustment?.items || [{ product_id: '', quantity: 1, unit_cost: 0, reason: '' }])

  const { data: warehousesData } = useQuery({
    queryKey: ['warehouses'],
    queryFn: () => warehousesApi.getAll(),
  })

  const { data: productsData } = useQuery({
    queryKey: ['products'],
    queryFn: () => productsApi.getAll(),
  })

  const warehouses = warehousesData?.data?.data || []
  const products = productsData?.data?.data || []

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      warehouse_id: adjustment?.warehouse_id || '',
      adjustment_type: adjustment?.adjustment_type || 'addition',
      date: adjustment?.date || new Date().toISOString().split('T')[0],
      reason: adjustment?.reason || '',
      notes: adjustment?.notes || '',
      items: items,
    },
  })

  useEffect(() => {
    if (adjustment) {
      form.reset({
        warehouse_id: adjustment.warehouse_id || '',
        adjustment_type: adjustment.adjustment_type || 'addition',
        date: adjustment.date || new Date().toISOString().split('T')[0],
        reason: adjustment.reason || '',
        notes: adjustment.notes || '',
        items: adjustment.items || [{ product_id: '', quantity: 1, unit_cost: 0, reason: '' }],
      })
      setItems(adjustment.items || [{ product_id: '', quantity: 1, unit_cost: 0, reason: '' }])
    }
  }, [adjustment, form])

  const onSubmit = async (values: z.infer<typeof formSchema>) => {
    try {
      if (adjustment) {
        // Update existing adjustment
        await import('@/lib/api').then(({ stockAdjustmentsApi }) => 
          stockAdjustmentsApi.update(adjustment.id, { ...values, items: items })
        )
      } else {
        // Create new adjustment
        await import('@/lib/api').then(({ stockAdjustmentsApi }) => 
          stockAdjustmentsApi.create({ ...values, items: items })
        )
      }
      onSuccess()
      onClose()
    } catch (error) {
      console.error('Error saving stock adjustment:', error)
    }
  }

  const addItem = () => {
    setItems([...items, { product_id: '', quantity: 1, unit_cost: 0, reason: '' }])
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
            <CardTitle>Adjustment Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="warehouse_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Warehouse *</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Select a warehouse" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {warehouses.map((warehouse: any) => (
                          <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                            {warehouse.name}
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
                name="adjustment_type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Type *</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Select adjustment type" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="addition">
                          <div className="flex items-center gap-2">
                            <PlusCircle className="h-4 w-4 text-green-500" />
                            Addition
                          </div>
                        </SelectItem>
                        <SelectItem value="subtraction">
                          <div className="flex items-center gap-2">
                            <MinusCircle className="h-4 w-4 text-red-500" />
                            Subtraction
                          </div>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Date *</FormLabel>
                    <FormControl>
                      <Input type="date" {...field} />
                    </FormControl>
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
                      <Input placeholder="Enter reason for adjustment..." {...field} />
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
                    <Textarea placeholder="Additional notes..." {...field} />
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
                    name={`items.${index}.unit_cost`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Unit Cost</FormLabel>
                        <FormControl>
                          <Input 
                            type="number" 
                            min="0" 
                            step="0.01" 
                            value={item.unit_cost}
                            onChange={(e) => updateItem(index, 'unit_cost', parseFloat(e.target.value))}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name={`items.${index}.reason`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Item Reason</FormLabel>
                        <FormControl>
                          <Input 
                            placeholder="Reason for this item..." 
                            value={item.reason}
                            onChange={(e) => updateItem(index, 'reason', e.target.value)}
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
          <Button type="submit">
            {adjustment ? 'Update' : 'Create'} Stock Adjustment
          </Button>
        </div>
      </form>
    </Form>
  )
}