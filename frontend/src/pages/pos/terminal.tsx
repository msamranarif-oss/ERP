import { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow
} from '@/components/ui/table'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue
} from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { productsApi, customersApi, paymentMethodsApi, registerSessionsApi, posApi } from '@/lib/api'
import {
  Search,
  Plus,
  Minus,
  Scan,
  CreditCard,
  DollarSign,
  Trash2,
  ShoppingCart,
  User,
  Receipt,
  CheckCircle2,
  AlertCircle,
  PackageSearch
} from 'lucide-react'

interface CartItem {
  id: number
  product_id: number
  name: string
  sku: string
  barcode: string
  price: number
  quantity: number
  subtotal: number
}

export function POSTerminalPage() {
  const [cart, setCart] = useState<CartItem[]>([])
  const [searchTerm, setSearchTerm] = useState('')
  const [selectedCustomer, setSelectedCustomer] = useState<string>('')
  const [paymentMethod, setPaymentMethod] = useState<string>('')
  const [amountPaid, setAmountPaid] = useState<number>(0)

  const queryClient = useQueryClient()

  // Fetch products
  const { data: productsData, isLoading: productsLoading } = useQuery({
    queryKey: ['posProducts', { search: searchTerm }],
    queryFn: () => productsApi.getAll({ search: searchTerm, per_page: 20 }),
    enabled: searchTerm.length >= 2,
  })

  // Fetch customers
  const { data: customersData } = useQuery({
    queryKey: ['customers'],
    queryFn: () => customersApi.getAll({ per_page: 100 }),
  })

  // Fetch payment methods
  const { data: paymentMethodsData } = useQuery({
    queryKey: ['paymentMethods'],
    queryFn: () => paymentMethodsApi.getAll({ per_page: 100 }),
  })

  // Fetch current register session
  const { data: registerSessionData } = useQuery({
    queryKey: ['currentRegisterSession'],
    queryFn: () => registerSessionsApi.current(),
  })

  const products = productsData?.data?.data || []
  const customers = customersData?.data?.data || []
  const paymentMethods = paymentMethodsData?.data?.data || []
  const currentRegisterSession = registerSessionData?.data || null

  const addToCart = (product: any) => {
    const existingItem = cart.find(item => item.product_id === product.id)
    if (existingItem) {
      setCart(cart.map(item =>
        item.product_id === product.id
          ? { ...item, quantity: item.quantity + 1, subtotal: (item.quantity + 1) * item.price }
          : item
      ))
    } else {
      setCart([...cart, {
        id: Date.now(),
        product_id: product.id,
        name: product.name,
        sku: product.sku,
        barcode: product.barcode || '',
        price: product.selling_price,
        quantity: 1,
        subtotal: product.selling_price,
      }])
    }
  }

  const removeFromCart = (productId: number) => {
    setCart(cart.filter(item => item.product_id !== productId))
  }

  const updateQuantity = (productId: number, newQuantity: number) => {
    if (newQuantity <= 0) {
      removeFromCart(productId)
      return
    }
    setCart(cart.map(item =>
      item.product_id === productId
        ? { ...item, quantity: newQuantity, subtotal: newQuantity * item.price }
        : item
    ))
  }

  const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0)
  const tax = subtotal * 0.1
  const total = subtotal + tax

  const { mutate: processSale, isPending: isProcessing } = useMutation({
    mutationFn: (data: any) => posApi.createSale(data),
    onSuccess: () => {
      setCart([])
      setAmountPaid(0)
      setPaymentMethod('')
      setSelectedCustomer('')
      alert('Sale completed successfully!')
      queryClient.invalidateQueries({ queryKey: ['posProducts'] })
      queryClient.invalidateQueries({ queryKey: ['currentRegisterSession'] })
    },
    onError: (error: any) => {
      alert(`Sale failed: ${error.message || 'Unknown error'}`)
    }
  })

  const handleCheckout = () => {
    if (!currentRegisterSession) return
    const saleData = {
      customer_id: selectedCustomer ? parseInt(selectedCustomer) : null,
      items: cart.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.price,
        discount_amount: 0,
        discount_percent: 0,
        tax_amount: (item.subtotal * 0.1),
        tax_percent: 10
      })),
      total_amount: total,
      paid_amount: amountPaid,
      payment_method_id: parseInt(paymentMethod),
      payment_amount: amountPaid,
      change_amount: Math.max(0, amountPaid - total),
      register_session_id: currentRegisterSession.data.id,
      notes: ''
    }
    processSale(saleData)
  }

  return (
    <div className="space-y-6">
      {/* Header Area */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">POS Terminal</h1>
          <p className="text-slate-500">Efficient transaction processing and inventory lookup.</p>
        </div>
        <div className="flex items-center gap-3">
          {currentRegisterSession ? (
            <div className="flex items-center gap-2 text-xs font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-full border border-green-100 uppercase tracking-wider">
              <CheckCircle2 className="h-4 w-4" />
              SESSION ACTIVE: {currentRegisterSession.data.cash_register?.name}
            </div>
          ) : (
            <div className="flex items-center gap-2 text-xs font-bold text-red-600 bg-red-50 px-3 py-1.5 rounded-full border border-red-100 uppercase tracking-wider">
              <AlertCircle className="h-4 w-4" />
              NO ACTIVE SESSION
            </div>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Product Selection Area */}
        <div className="lg:col-span-8 space-y-6">
          <Card className="card-enterprise border-slate-200 shadow-sm overflow-hidden">
            <CardHeader className="bg-slate-50/50 border-b border-slate-100 pb-4">
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <PackageSearch className="h-4 w-4 text-blue-600" />
                Inventory Lookup
              </CardTitle>
            </CardHeader>
            <CardContent className="pt-6">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <Input
                  placeholder="Scan barcode or type product name..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-10 h-12 bg-white border-slate-200 focus:ring-blue-100 transition-all text-lg"
                  autoFocus
                />
                <Button variant="ghost" size="icon" className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400">
                  <Scan className="h-5 w-5" />
                </Button>
              </div>

              {searchTerm.length >= 2 && (
                <div className="mt-4 border border-slate-200 rounded-lg overflow-hidden max-h-[300px] overflow-y-auto bg-white shadow-inner">
                  {productsLoading ? (
                    <div className="p-8 text-center"><span className="spinner h-6 w-6 text-blue-600" /></div>
                  ) : products.length > 0 ? (
                    <Table className="table-enterprise">
                      <TableHeader className="bg-slate-50 sticky top-0 z-10">
                        <TableRow>
                          <TableHead className="text-xs font-bold uppercase py-2">Product Info</TableHead>
                          <TableHead className="text-xs font-bold uppercase py-2">Stock</TableHead>
                          <TableHead className="text-xs font-bold uppercase py-2">Price</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {products.map((product: any) => (
                          <TableRow
                            key={product.id}
                            className="cursor-pointer hover:bg-blue-50/30 transition-colors group"
                            onClick={() => addToCart(product)}
                          >
                            <TableCell>
                              <div className="font-bold text-slate-900 group-hover:text-blue-700">{product.name}</div>
                              <div className="text-[11px] font-medium text-slate-400 uppercase tracking-tighter">SKU: {product.sku}</div>
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline" className="border-slate-200 text-slate-500">{product.quantity_sum || 0} in stock</Badge>
                            </TableCell>
                            <TableCell className="font-bold text-slate-900">
                              ${product.selling_price.toFixed(2)}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : (
                    <div className="p-8 text-center text-slate-400 font-medium italic">No products matched "{searchTerm}"</div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Cart Table */}
          <Card className="card-enterprise border-slate-200 shadow-sm overflow-hidden">
            <CardHeader className="bg-slate-50/50 border-b border-slate-100 pb-4">
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <ShoppingCart className="h-4 w-4 text-blue-600" />
                Current Shopping Cart
              </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              {cart.length > 0 ? (
                <Table className="table-enterprise">
                  <TableHeader>
                    <TableRow className="bg-slate-25 hover:bg-transparent">
                      <TableHead className="text-[11px] font-bold uppercase">Item Details</TableHead>
                      <TableHead className="text-[11px] font-bold uppercase text-center w-32">Quantity</TableHead>
                      <TableHead className="text-[11px] font-bold uppercase">Subtotal</TableHead>
                      <TableHead className="w-10"></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {cart.map((item) => (
                      <TableRow key={item.id} className="border-b border-slate-100 last:border-0 hover:bg-slate-50/20">
                        <TableCell>
                          <div className="font-bold text-slate-900">{item.name}</div>
                          <div className="text-[10px] text-slate-400 font-medium">UNIT PRICE: ${item.price.toFixed(2)}</div>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center justify-center gap-1">
                            <Button
                              variant="ghost"
                              size="icon"
                              className="h-7 w-7 border border-slate-200 rounded-md hover:bg-white hover:border-slate-300"
                              onClick={() => updateQuantity(item.product_id, item.quantity - 1)}
                            >
                              <Minus className="h-3 w-3" />
                            </Button>
                            <div className="w-10 text-center font-bold text-slate-900">{item.quantity}</div>
                            <Button
                              variant="ghost"
                              size="icon"
                              className="h-7 w-7 border border-slate-200 rounded-md hover:bg-white hover:border-slate-300"
                              onClick={() => updateQuantity(item.product_id, item.quantity + 1)}
                            >
                              <Plus className="h-3 w-3" />
                            </Button>
                          </div>
                        </TableCell>
                        <TableCell className="font-bold text-slate-900">${item.subtotal.toFixed(2)}</TableCell>
                        <TableCell>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-slate-300 hover:text-red-600 hover:bg-red-50"
                            onClick={() => removeFromCart(item.product_id)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <div className="p-12 text-center flex flex-col items-center gap-3">
                  <div className="h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center text-slate-300">
                    <ShoppingCart className="h-6 w-6" />
                  </div>
                  <div className="text-slate-400 font-medium">Your shopping cart is currently empty.</div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Checkout Column */}
        <div className="lg:col-span-4 space-y-6">
          <Card className="card-enterprise border-slate-200 shadow-enterprise-lg bg-white sticky top-24">
            <CardHeader className="bg-slate-900 text-white rounded-t-lg">
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <Receipt className="h-4 w-4" />
                Order Summary
              </CardTitle>
            </CardHeader>
            <CardContent className="pt-6 space-y-5">
              <div className="space-y-2">
                <Label className="text-xs font-bold uppercase text-slate-400 tracking-wider flex items-center gap-1.5">
                  <User className="h-3 w-3" /> Customer Selection
                </Label>
                <Select value={selectedCustomer} onValueChange={setSelectedCustomer}>
                  <SelectTrigger className="h-10 border-slate-200 bg-white shadow-none">
                    <SelectValue placeholder="Standard Walk-in Customer" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="">Standard Walk-in Customer</SelectItem>
                    {customers.map((customer: any) => (
                      <SelectItem key={customer.id} value={customer.id.toString()}>
                        {customer.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="bg-slate-50 p-4 rounded-lg border border-slate-100 space-y-3">
                <div className="flex justify-between text-sm">
                  <span className="text-slate-500 font-medium">Subtotal</span>
                  <span className="text-slate-900 font-bold">${subtotal.toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-slate-500 font-medium">Estimated Tax (10%)</span>
                  <span className="text-slate-900 font-bold">${tax.toFixed(2)}</span>
                </div>
                <div className="h-px bg-slate-200 my-1"></div>
                <div className="flex justify-between items-center pt-1">
                  <span className="text-slate-900 font-bold">Grand Total</span>
                  <span className="text-2xl font-black text-blue-600">${total.toFixed(2)}</span>
                </div>
              </div>

              <div className="space-y-4">
                <div className="space-y-2">
                  <Label className="text-xs font-bold uppercase text-slate-400 tracking-wider">Payment Configuration</Label>
                  <div className="grid grid-cols-2 gap-2">
                    {paymentMethods.slice(0, 2).map((method: any) => (
                      <Button
                        key={method.id}
                        variant="outline"
                        className={`h-12 gap-2 font-bold transition-all border-slate-200 ${paymentMethod === method.id.toString() ? 'border-blue-600 bg-blue-50 text-blue-600 ring-1 ring-blue-600' : 'hover:bg-slate-50 text-slate-600'}`}
                        onClick={() => setPaymentMethod(method.id.toString())}
                      >
                        {method.type === 'cash' ? <DollarSign className="h-4 w-4" /> : <CreditCard className="h-4 w-4" />}
                        {method.name}
                      </Button>
                    ))}
                  </div>
                </div>

                <div className="space-y-2">
                  <Label className="text-xs font-bold uppercase text-slate-400 tracking-wider">Cash Received</Label>
                  <div className="relative">
                    <DollarSign className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <Input
                      type="number"
                      step="0.01"
                      className="pl-8 h-12 text-lg font-bold border-slate-200 focus:ring-blue-100"
                      value={amountPaid || ''}
                      onChange={(e) => setAmountPaid(parseFloat(e.target.value) || 0)}
                      placeholder="Enter amount"
                    />
                  </div>
                </div>

                {amountPaid > 0 && (
                  <div className="p-3 bg-blue-600 text-white rounded-lg flex items-center justify-between">
                    <span className="text-xs font-bold uppercase tracking-wider opacity-80">Change Due</span>
                    <span className="text-xl font-black">${(Math.max(0, amountPaid - total)).toFixed(2)}</span>
                  </div>
                )}
              </div>

              <Button
                className="w-full h-14 text-lg font-black uppercase tracking-widest bg-blue-600 hover:bg-blue-700 text-white shadow-enterprise transition-all disabled:opacity-50 disabled:grayscale"
                onClick={handleCheckout}
                disabled={!currentRegisterSession || cart.length === 0 || !paymentMethod || amountPaid < total || isProcessing}
              >
                {isProcessing ? 'Processing Transaction...' : 'Finalize Sale'}
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}