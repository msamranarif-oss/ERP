import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { categoriesApi, unitsApi, productsApi } from '@/lib/api'
import { Loader2, Upload } from 'lucide-react'

const productSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  sku: z.string().optional(),
  barcode: z.string().optional(),
  category_id: z.number().optional(),
  base_unit_id: z.number().optional(),
  description: z.string().optional(),
  cost_price: z.number().min(0, 'Cost price must be greater than 0').optional().default(0),
  selling_price: z.number().min(0, 'Selling price must be greater than 0').optional().default(0),
  min_price: z.number().min(0, 'Minimum price must be greater than 0').optional().default(0),
  reorder_level: z.number().min(0).optional().default(0),
  reorder_quantity: z.number().min(0).optional().default(0),
  is_active: z.boolean().default(true),
  is_sellable: z.boolean().default(true),
  is_purchasable: z.boolean().default(true),
  track_inventory: z.boolean().default(true),
  has_variants: z.boolean().default(false),
  allow_negative_stock: z.boolean().default(false),
  tax_type: z.enum(['inclusive', 'exclusive', 'exempt']).optional(),
  tax_rate: z.number().min(0).max(100).optional().default(0),
})

type ProductFormValues = z.infer<typeof productSchema>

interface ProductFormProps {
  product?: any
  onClose: () => void
  onSuccess: () => void
}

export function ProductForm({ product, onClose, onSuccess }: ProductFormProps) {
  const [imagePreview, setImagePreview] = useState<string | null>(product?.image || null)
  const [file, setFile] = useState<File | null>(null)

  const queryClient = useQueryClient()

  const { data: categoriesData } = useQuery({
    queryKey: ['categories'],
    queryFn: () => categoriesApi.getAll({ per_page: 100 }),
  })

  const { data: unitsData } = useQuery({
    queryKey: ['units'],
    queryFn: () => unitsApi.getAll({ per_page: 100 }),
  })

  const createMutation = useMutation({
    mutationFn: (data: any) => productsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] })
      onSuccess()
      onClose()
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: any) => productsApi.update(product.id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] })
      onSuccess()
      onClose()
    },
  })

  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
  } = useForm<ProductFormValues>({
    resolver: zodResolver(productSchema),
    defaultValues: {
      name: product?.name || '',
      sku: product?.sku || '',
      barcode: product?.barcode || '',
      category_id: product?.category_id,
      base_unit_id: product?.base_unit_id,
      description: product?.description || '',
      cost_price: product?.cost_price || 0,
      selling_price: product?.selling_price || 0,
      min_price: product?.min_price || 0,
      reorder_level: product?.reorder_level || 0,
      reorder_quantity: product?.reorder_quantity || 0,
      is_active: product?.is_active ?? true,
      is_sellable: product?.is_sellable ?? true,
      is_purchasable: product?.is_purchasable ?? true,
      track_inventory: product?.track_inventory ?? true,
      has_variants: product?.has_variants ?? false,
      allow_negative_stock: product?.allow_negative_stock ?? false,
      tax_type: product?.tax_type,
      tax_rate: product?.tax_rate || 0,
    },
  })

  const onSubmit = async (data: ProductFormValues) => {
    const formData = new FormData()

    // Append form fields
    Object.entries(data).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        formData.append(key, value.toString())
      }
    })

    // Append file if exists
    if (file) {
      formData.append('image', file)
    }

    if (product) {
      updateMutation.mutate(formData)
    } else {
      createMutation.mutate(formData)
    }
  }

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0]
    if (selectedFile) {
      setFile(selectedFile)
      const reader = new FileReader()
      reader.onloadend = () => {
        setImagePreview(reader.result as string)
      }
      reader.readAsDataURL(selectedFile)
    }
  }

  const categories = categoriesData?.data?.data || []
  const units = unitsData?.data?.data || []

  const isSubmitting = createMutation.isPending || updateMutation.isPending

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      {(createMutation.isError || updateMutation.isError) && (
        <Alert variant="destructive">
          <AlertDescription>
            {createMutation.error?.message || updateMutation.error?.message}
          </AlertDescription>
        </Alert>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="space-y-4">
          <div>
            <Label htmlFor="name">Name *</Label>
            <Input
              id="name"
              {...register('name', { required: true })}
              className={errors.name ? 'border-red-500' : ''}
            />
            {errors.name && (
              <p className="text-sm text-red-500 mt-1">{errors.name.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="sku">SKU</Label>
            <Input
              id="sku"
              {...register('sku')}
              className={errors.sku ? 'border-red-500' : ''}
            />
            {errors.sku && (
              <p className="text-sm text-red-500 mt-1">{errors.sku.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="barcode">Barcode</Label>
            <Input
              id="barcode"
              {...register('barcode')}
              className={errors.barcode ? 'border-red-500' : ''}
            />
            {errors.barcode && (
              <p className="text-sm text-red-500 mt-1">{errors.barcode.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="category_id">Category</Label>
            <Select
              value={watch('category_id')?.toString()}
              onValueChange={(value) => setValue('category_id', Number(value))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select category" />
              </SelectTrigger>
              <SelectContent>
                {categories.map((category: any) => (
                  <SelectItem key={category.id} value={category.id.toString()}>
                    {category.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div>
            <Label htmlFor="base_unit_id">Base Unit</Label>
            <Select
              value={watch('base_unit_id')?.toString()}
              onValueChange={(value) => setValue('base_unit_id', Number(value))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select unit" />
              </SelectTrigger>
              <SelectContent>
                {units.map((unit: any) => (
                  <SelectItem key={unit.id} value={unit.id.toString()}>
                    {unit.name} ({unit.abbreviation})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="space-y-4">
          <div>
            <Label htmlFor="cost_price">Cost Price</Label>
            <Input
              id="cost_price"
              type="number"
              step="0.01"
              {...register('cost_price', { valueAsNumber: true })}
              className={errors.cost_price ? 'border-red-500' : ''}
            />
            {errors.cost_price && (
              <p className="text-sm text-red-500 mt-1">{errors.cost_price.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="selling_price">Selling Price</Label>
            <Input
              id="selling_price"
              type="number"
              step="0.01"
              {...register('selling_price', { valueAsNumber: true })}
              className={errors.selling_price ? 'border-red-500' : ''}
            />
            {errors.selling_price && (
              <p className="text-sm text-red-500 mt-1">{errors.selling_price.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="min_price">Minimum Price</Label>
            <Input
              id="min_price"
              type="number"
              step="0.01"
              {...register('min_price', { valueAsNumber: true })}
              className={errors.min_price ? 'border-red-500' : ''}
            />
            {errors.min_price && (
              <p className="text-sm text-red-500 mt-1">{errors.min_price.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="reorder_level">Reorder Level</Label>
            <Input
              id="reorder_level"
              type="number"
              {...register('reorder_level', { valueAsNumber: true })}
              className={errors.reorder_level ? 'border-red-500' : ''}
            />
            {errors.reorder_level && (
              <p className="text-sm text-red-500 mt-1">{errors.reorder_level.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="reorder_quantity">Reorder Quantity</Label>
            <Input
              id="reorder_quantity"
              type="number"
              {...register('reorder_quantity', { valueAsNumber: true })}
              className={errors.reorder_quantity ? 'border-red-500' : ''}
            />
            {errors.reorder_quantity && (
              <p className="text-sm text-red-500 mt-1">{errors.reorder_quantity.message}</p>
            )}
          </div>
        </div>
      </div>

      <div className="space-y-4">
        <div>
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            {...register('description')}
            className={errors.description ? 'border-red-500' : ''}
          />
          {errors.description && (
            <p className="text-sm text-red-500 mt-1">{errors.description.message}</p>
          )}
        </div>

        <div>
          <Label htmlFor="image">Image</Label>
          <div className="flex items-center gap-4">
            <div className="flex-1">
              <Input
                id="image"
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="hidden"
              />
              <Label
                htmlFor="image"
                className="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer bg-muted hover:bg-accent"
              >
                {imagePreview ? (
                  <img
                    src={imagePreview}
                    alt="Preview"
                    className="w-full h-full object-contain rounded-md"
                  />
                ) : (
                  <div className="flex flex-col items-center justify-center pt-5 pb-6">
                    <Upload className="h-8 w-8 text-muted-foreground" />
                    <p className="text-sm text-muted-foreground mt-2">
                      Click to upload image
                    </p>
                  </div>
                )}
              </Label>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="flex items-center space-x-2">
            <Checkbox
              id="is_active"
              {...register('is_active')}
            />
            <Label htmlFor="is_active">Active</Label>
          </div>

          <div className="flex items-center space-x-2">
            <Checkbox
              id="is_sellable"
              {...register('is_sellable')}
            />
            <Label htmlFor="is_sellable">Sellable</Label>
          </div>

          <div className="flex items-center space-x-2">
            <Checkbox
              id="is_purchasable"
              {...register('is_purchasable')}
            />
            <Label htmlFor="is_purchasable">Purchasable</Label>
          </div>

          <div className="flex items-center space-x-2">
            <Checkbox
              id="track_inventory"
              {...register('track_inventory')}
            />
            <Label htmlFor="track_inventory">Track Inventory</Label>
          </div>

          <div className="flex items-center space-x-2">
            <Checkbox
              id="has_variants"
              {...register('has_variants')}
            />
            <Label htmlFor="has_variants">Has Variants</Label>
          </div>

          <div className="flex items-center space-x-2">
            <Checkbox
              id="allow_negative_stock"
              {...register('allow_negative_stock')}
            />
            <Label htmlFor="allow_negative_stock">Allow Negative Stock</Label>
          </div>
        </div>
      </div>

      <div className="flex justify-end gap-4 pt-4">
        <Button type="button" variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {product ? 'Updating...' : 'Creating...'}
            </>
          ) : product ? 'Update Product' : 'Create Product'}
        </Button>
      </div>
    </form>
  )
}
