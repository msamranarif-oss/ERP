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
import { Loader2, Upload, Package, Tag, DollarSign, Settings } from 'lucide-react'
import { cn } from '@/lib/utils'

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
      category_id: product?.category_id || undefined,
      base_unit_id: product?.base_unit_id || undefined,
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
      tax_type: product?.tax_type || undefined,
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
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-8">
      {(createMutation.isError || updateMutation.isError) && (
        <Alert variant="destructive" className="border-destructive/50 bg-destructive/5">
          <AlertDescription className="text-destructive-foreground">
            {createMutation.error?.message || updateMutation.error?.message}
          </AlertDescription>
        </Alert>
      )}
      
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left Column - Basic Information */}
        <div className="lg:col-span-2 space-y-6">
          <Card className="border-0 shadow-sm">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle className="flex items-center gap-2 text-lg">
                <Package className="h-5 w-5 text-primary" />
                Basic Information
              </CardTitle>
            </CardHeader>
            <CardContent className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="name" className="text-sm font-medium">Product Name *</Label>
                  <Input
                    id="name"
                    placeholder="Enter product name"
                    {...register('name', { required: true })}
                    className={cn(
                      "h-11 transition-colors",
                      errors.name ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                    )}
                  />
                  {errors.name && (
                    <p className="text-sm text-destructive">{errors.name.message}</p>
                  )}
                </div>
                
                <div className="space-y-2">
                  <Label htmlFor="sku" className="text-sm font-medium">SKU</Label>
                  <Input
                    id="sku"
                    placeholder="Enter SKU"
                    {...register('sku')}
                    className={cn(
                      "h-11 transition-colors",
                      errors.sku ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                    )}
                  />
                </div>
                
                <div className="space-y-2">
                  <Label htmlFor="barcode" className="text-sm font-medium">Barcode</Label>
                  <Input
                    id="barcode"
                    placeholder="Enter barcode"
                    {...register('barcode')}
                    className={cn(
                      "h-11 transition-colors",
                      errors.barcode ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                    )}
                  />
                </div>
                
                <div className="space-y-2">
                  <Label htmlFor="category_id" className="text-sm font-medium">Category</Label>
                  <Select
                    value={watch('category_id')?.toString()}
                    onValueChange={(value) => setValue('category_id', Number(value))}
                  >
                    <SelectTrigger className="h-11">
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
                
                <div className="space-y-2">
                  <Label htmlFor="base_unit_id" className="text-sm font-medium">Base Unit</Label>
                  <Select
                    value={watch('base_unit_id')?.toString()}
                    onValueChange={(value) => setValue('base_unit_id', Number(value))}
                  >
                    <SelectTrigger className="h-11">
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
            </CardContent>
          </Card>
          
          <Card className="border-0 shadow-sm">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle className="flex items-center gap-2 text-lg">
                <DollarSign className="h-5 w-5 text-primary" />
                Pricing Information
              </CardTitle>
            </CardHeader>
            <CardContent className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="cost_price" className="text-sm font-medium">Cost Price</Label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">$</span>
                    <Input
                      id="cost_price"
                      type="number"
                      step="0.01"
                      placeholder="0.00"
                      className={cn(
                        "h-11 pl-8 transition-colors",
                        errors.cost_price ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                      )}
                      {...register('cost_price', { valueAsNumber: true })}
                    />
                  </div>
                </div>
                
                <div className="space-y-2">
                  <Label htmlFor="selling_price" className="text-sm font-medium">Selling Price</Label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">$</span>
                    <Input
                      id="selling_price"
                      type="number"
                      step="0.01"
                      placeholder="0.00"
                      className={cn(
                        "h-11 pl-8 transition-colors",
                        errors.selling_price ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                      )}
                      {...register('selling_price', { valueAsNumber: true })}
                    />
                  </div>
                </div>
                
                <div className="space-y-2">
                  <Label htmlFor="min_price" className="text-sm font-medium">Minimum Price</Label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">$</span>
                    <Input
                      id="min_price"
                      type="number"
                      step="0.01"
                      placeholder="0.00"
                      className={cn(
                        "h-11 pl-8 transition-colors",
                        errors.min_price ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                      )}
                      {...register('min_price', { valueAsNumber: true })}
                    />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
          
          <Card className="border-0 shadow-sm">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle className="flex items-center gap-2 text-lg">
                <Settings className="h-5 w-5 text-primary" />
                Inventory Settings
              </CardTitle>
            </CardHeader>
            <CardContent className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="reorder_level" className="text-sm font-medium">Reorder Level</Label>
                  <Input
                    id="reorder_level"
                    type="number"
                    placeholder="0"
                    className={cn(
                      "h-11 transition-colors",
                      errors.reorder_level ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                    )}
                    {...register('reorder_level', { valueAsNumber: true })}
                  />
                </div>
                
                <div className="space-y-2">
                  <Label htmlFor="reorder_quantity" className="text-sm font-medium">Reorder Quantity</Label>
                  <Input
                    id="reorder_quantity"
                    type="number"
                    placeholder="0"
                    className={cn(
                      "h-11 transition-colors",
                      errors.reorder_quantity ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                    )}
                    {...register('reorder_quantity', { valueAsNumber: true })}
                  />
                </div>
              </div>
              
              <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mt-6">
                <div className="flex items-center space-x-3">
                  <Checkbox
                    id="is_active"
                    {...register('is_active')}
                    className="rounded data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                  />
                  <Label htmlFor="is_active" className="text-sm font-medium">Active</Label>
                </div>
                
                <div className="flex items-center space-x-3">
                  <Checkbox
                    id="is_sellable"
                    {...register('is_sellable')}
                    className="rounded data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                  />
                  <Label htmlFor="is_sellable" className="text-sm font-medium">Sellable</Label>
                </div>
                
                <div className="flex items-center space-x-3">
                  <Checkbox
                    id="is_purchasable"
                    {...register('is_purchasable')}
                    className="rounded data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                  />
                  <Label htmlFor="is_purchasable" className="text-sm font-medium">Purchasable</Label>
                </div>
                
                <div className="flex items-center space-x-3">
                  <Checkbox
                    id="track_inventory"
                    {...register('track_inventory')}
                    className="rounded data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                  />
                  <Label htmlFor="track_inventory" className="text-sm font-medium">Track Inventory</Label>
                </div>
                
                <div className="flex items-center space-x-3">
                  <Checkbox
                    id="has_variants"
                    {...register('has_variants')}
                    className="rounded data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                  />
                  <Label htmlFor="has_variants" className="text-sm font-medium">Has Variants</Label>
                </div>
                
                <div className="flex items-center space-x-3">
                  <Checkbox
                    id="allow_negative_stock"
                    {...register('allow_negative_stock')}
                    className="rounded data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
                  />
                  <Label htmlFor="allow_negative_stock" className="text-sm font-medium">Allow Negative Stock</Label>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
        
        {/* Right Column - Image and Description */}
        <div className="space-y-6">
          <Card className="border-0 shadow-sm">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle className="flex items-center gap-2 text-lg">
                <Upload className="h-5 w-5 text-primary" />
                Product Image
              </CardTitle>
            </CardHeader>
            <CardContent className="p-6">
              <div className="flex flex-col items-center justify-center">
                <div className="relative group">
                  <Input
                    id="image"
                    type="file"
                    accept="image/*"
                    onChange={handleFileChange}
                    className="hidden"
                  />
                  <Label
                    htmlFor="image"
                    className="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-lg cursor-pointer bg-muted/20 hover:bg-muted/40 transition-colors group-hover:border-primary/50"
                  >
                    {imagePreview ? (
                      <div className="relative w-full h-full">
                        <img 
                          src={imagePreview} 
                          alt="Preview" 
                          className="w-full h-full object-contain rounded-md"
                        />
                        <div className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-md">
                          <span className="text-white text-sm font-medium">Change Image</span>
                        </div>
                      </div>
                    ) : (
                      <div className="flex flex-col items-center justify-center pt-8 pb-6">
                        <Upload className="h-10 w-10 text-muted-foreground mb-3" />
                        <p className="text-sm text-muted-foreground text-center">
                          Click to upload product image
                        </p>
                        <p className="text-xs text-muted-foreground mt-1">
                          PNG, JPG, GIF up to 10MB
                        </p>
                      </div>
                    )}
                  </Label>
                </div>
              </div>
            </CardContent>
          </Card>
          
          <Card className="border-0 shadow-sm">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle className="flex items-center gap-2 text-lg">
                <Tag className="h-5 w-5 text-primary" />
                Description
              </CardTitle>
            </CardHeader>
            <CardContent className="p-6">
              <div className="space-y-2">
                <Textarea
                  placeholder="Enter product description..."
                  className={cn(
                    "min-h-32 resize-none transition-colors",
                    errors.description ? 'border-destructive focus-visible:ring-destructive' : 'border-input'
                  )}
                  {...register('description')}
                />
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
      
      <div className="flex justify-end gap-4 pt-4 border-t">
        <Button 
          type="button" 
          variant="outline" 
          onClick={onClose}
          className="px-6 h-11"
        >
          Cancel
        </Button>
        <Button 
          type="submit" 
          disabled={isSubmitting}
          className="px-6 h-11 bg-primary hover:bg-primary/90"
        >
          {isSubmitting ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {product ? 'Updating Product...' : 'Creating Product...'}
            </>
          ) : product ? 'Update Product' : 'Create Product'}
        </Button>
      </div>
    </form>
  )
}