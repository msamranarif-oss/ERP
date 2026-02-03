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
import { Plus, Minus, Calendar } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { warehousesApi, productsApi } from '@/lib/api'

const formSchema = z.object({
  from_warehouse_id: z.string().min(1, 'From warehouse is required'),
  to_warehouse_id: z.string().min(1, 'To warehouse is required'),
  date: z.string().min(1, 'Date is required'),
  notes: z.string().optional(),
  items: z.array(z.object({
    product_id: z.string().min(1, 'Product is required'),
    quantity: z.number().min(1, 'Quantity must be greater than 0'),
    unit_cost: z.number().min(0, 'Unit cost must be at least 0'),
  })).min(1, 'At least one item is required'),
})

interface StockTransferFormProps {
  transfer?: any
  onClose: () => void
  onSuccess: () => void
}

export function StockTransferForm({ transfer, onClose, onSuccess }: StockTransferFormProps) {
  const [items, setItems] = useState<any[]>(transfer?.items || [{ product_id: '', quantity: 1, unit_cost: 0 }])

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
      from_warehouse_id: transfer?.from_warehouse_id || '',
      to_warehouse_id: transfer?.to_warehouse_id || '',
      date: transfer?.date || new Date().toISOString().split('T')[0],
      notes: transfer?.notes || '',
      items: items,
    },
  })

  useEffect(() => {
    if (transfer) {
      form.reset({
        from_warehouse_id: transfer.from_warehouse_id || '',
        to_warehouse_id: transfer.to_warehouse_id || '',
        date: transfer.date || new Date().toISOString().split('T')[0],
        notes: transfer.notes || '',
        items: transfer.items || [{ product_id: '', quantity: 1, unit_cost: 0 }],
      })
      setItems(transfer.items || [{ product_id: '', quantity: 1, unit_cost: 0 }])
    }
  }, [transfer, form])

  // Prevent selecting the same warehouse for both from and to
  const filteredWarehouses = warehouses.filter((warehouse: any) => 
    transfer ? 
      (transfer.id ? 
        (form.getValues('from_warehouse_id') && warehouse.id.toString() !== form.getValues('from_warehouse_id')) :
        (form.watch('from_warehouse_id') && warehouse.id.toString() !== form.watch('from_warehouse_id'))
      ) : 
      true
  )

  const onSubmit = async (values: z.infer<typeof formSchema>) => {
    try {
      if (transfer) {
        // Update existing transfer
        await import('@/lib/api').then(({ stockTransfersApi }) => 
          stockTransfersApi.update(transfer.id, { ...values, items: items })
        )
      } else {
        // Create new transfer
        await import('@/lib/api').then(({ stockTransfersApi }) => 
          stockTransfersApi.create({ ...values, items: items })
        )
      }
      onSuccess()
      onClose()
    } catch (error) {
      console.error('Error saving stock transfer:', error)
    }
  }

  const addItem = () => {
    setItems([...items, { product_id: '', quantity: 1, unit_cost: 0 }])
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
            <CardTitle>Transfer Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="from_warehouse_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>From Warehouse *</FormLabel>
                    <Select 
                      onValueChange={(value) => {
                        field.onChange(value)
                        // Reset to_warehouse if it's the same as from_warehouse
                        if (form.getValues('to_warehouse_id') === value) {
                          form.setValue('to_warehouse_id', '')
                        }
                      }} 
                      defaultValue={field.value}
                    >
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Select from warehouse" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {warehouses
                          .filter((warehouse: any) => 
                            !transfer || 
                            transfer.id || 
                            warehouse.id.toString() !== form.watch('to_warehouse_id')
                          )
                          .map((warehouse: any) => (
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
                name="to_warehouse_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>To Warehouse *</FormLabel>
                    <Select 
                      onValueChange={(value) => {
                        field.onChange(value)
                        // Reset from_warehouse if it's the same as to_warehouse
                        if (form.getValues('from_warehouse_id') === value) {
                          form.setValue('from_warehouse_id', '')
                        }
                      }} 
                      defaultValue={field.value}
                    >
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Select to warehouse" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {warehouses
                          .filter((warehouse: any) => 
                            !transfer || 
                            transfer.id || 
                            warehouse.id.toString() !== form.watch('from_warehouse_id')
                          )
                          .map((warehouse: any) => (
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
                <div key={index} className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
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
            {transfer ? 'Update' : 'Create'} Stock Transfer
          </Button>
        </div>
      </form>
    </Form>
  )
}