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
import { journalEntriesApi, accountsApi } from '@/lib/api'

const formSchema = z.object({
  date: z.string().min(1, 'Date is required'),
  reference: z.string().optional(),
  description: z.string().min(1, 'Description is required'),
  lines: z.array(z.object({
    account_id: z.string().min(1, 'Account is required'),
    debit: z.string().min(1, 'Debit amount is required'),
    credit: z.string().min(1, 'Credit amount is required'),
    description: z.string().optional(),
  })).min(2, 'At least 2 lines are required'),
})

interface JournalEntryFormProps {
  entry?: any
  onClose: () => void
  onSuccess: () => void
}

export function JournalEntryForm({ entry, onClose, onSuccess }: JournalEntryFormProps) {
  const [lines, setLines] = useState<any[]>(entry?.lines || [{ account_id: '', debit: '0', credit: '0', description: '' }])
  const [totalDebit, setTotalDebit] = useState(0)
  const [totalCredit, setTotalCredit] = useState(0)

  const { data: accountsData } = useQuery({
    queryKey: ['accounts'],
    queryFn: () => accountsApi.getAll({ per_page: 100 }),
  })

  const accounts = accountsData?.data?.data || []

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      date: entry?.date || new Date().toISOString().split('T')[0],
      reference: entry?.reference || '',
      description: entry?.description || '',
      lines: lines,
    },
  })

  useEffect(() => {
    // Calculate totals whenever lines change
    const calculatedTotalDebit = lines.reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0)
    const calculatedTotalCredit = lines.reduce((sum, line) => sum + (parseFloat(line.credit) || 0), 0)
    
    setTotalDebit(calculatedTotalDebit)
    setTotalCredit(calculatedTotalCredit)
  }, [lines])

  useEffect(() => {
    if (entry) {
      form.reset({
        date: entry.date || new Date().toISOString().split('T')[0],
        reference: entry.reference || '',
        description: entry.description || '',
        lines: entry.lines || [{ account_id: '', debit: '0', credit: '0', description: '' }],
      })
      setLines(entry.lines || [{ account_id: '', debit: '0', credit: '0', description: '' }])
    }
  }, [entry, form])

  const createMutation = useMutation({
    mutationFn: (data: any) => journalEntriesApi.create(data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: any) => journalEntriesApi.update(entry.id, data),
    onSuccess: () => {
      onSuccess()
      onClose()
    },
  })

  const onSubmit = async (values: z.infer<typeof formSchema>) => {
    const data = {
      ...values,
      lines: lines.map(line => ({
        ...line,
        debit: parseFloat(line.debit),
        credit: parseFloat(line.credit),
      })),
    }

    if (entry) {
      updateMutation.mutate(data)
    } else {
      createMutation.mutate(data)
    }
  }

  const addLine = () => {
    setLines([...lines, { account_id: '', debit: '0', credit: '0', description: '' }])
  }

  const removeLine = (index: number) => {
    if (lines.length > 2) {
      const newLines = [...lines]
      newLines.splice(index, 1)
      setLines(newLines)
      form.setValue('lines', newLines)
    }
  }

  const updateLine = (index: number, field: string, value: any) => {
    const newLines = [...lines]
    newLines[index] = { ...newLines[index], [field]: value }
    setLines(newLines)
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Journal Entry Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
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
                name="reference"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Reference</FormLabel>
                    <FormControl>
                      <Input placeholder="Reference number" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Description *</FormLabel>
                  <FormControl>
                    <Textarea placeholder="Description of the journal entry..." {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Journal Lines</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {lines.map((line, index) => (
                <div key={index} className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                  <FormField
                    control={form.control}
                    name={`lines.${index}.account_id`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Account *</FormLabel>
                        <Select 
                          value={line.account_id} 
                          onValueChange={(value) => updateLine(index, 'account_id', value)}
                        >
                          <FormControl>
                            <SelectTrigger>
                              <SelectValue placeholder="Select account" />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            {accounts.map((account: any) => (
                              <SelectItem key={account.id} value={account.id.toString()}>
                                {account.code} - {account.name}
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
                    name={`lines.${index}.debit`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Debit *</FormLabel>
                        <FormControl>
                          <Input 
                            type="number" 
                            step="0.01" 
                            value={line.debit}
                            onChange={(e) => updateLine(index, 'debit', e.target.value)}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name={`lines.${index}.credit`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Credit *</FormLabel>
                        <FormControl>
                          <Input 
                            type="number" 
                            step="0.01" 
                            value={line.credit}
                            onChange={(e) => updateLine(index, 'credit', e.target.value)}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name={`lines.${index}.description`}
                    render={() => (
                      <FormItem>
                        <FormLabel>Line Description</FormLabel>
                        <FormControl>
                          <Input 
                            placeholder="Line description" 
                            value={line.description}
                            onChange={(e) => updateLine(index, 'description', e.target.value)}
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
                      onClick={() => removeLine(index)}
                      disabled={lines.length <= 2}
                    >
                      <Minus className="h-4 w-4" />
                    </Button>
                    {index === lines.length - 1 && (
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="icon"
                        onClick={addLine}
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
                <span>Total Debit:</span>
                <span className={totalDebit === totalCredit ? 'text-green-600' : 'text-red-600'}>
                  ${totalDebit.toFixed(2)}
                </span>
              </div>
              <div className="flex justify-between">
                <span>Total Credit:</span>
                <span className={totalDebit === totalCredit ? 'text-green-600' : 'text-red-600'}>
                  ${totalCredit.toFixed(2)}
                </span>
              </div>
              <div className={`flex justify-between font-bold pt-2 border-t ${totalDebit === totalCredit ? 'text-green-600' : 'text-red-600'}`}>
                <span>Difference:</span>
                <span>${Math.abs(totalDebit - totalCredit).toFixed(2)}</span>
              </div>
              {totalDebit !== totalCredit && (
                <div className="text-red-600 text-sm mt-2">
                  Debits and credits must balance to save the journal entry.
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end gap-4">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button 
            type="submit" 
            disabled={createMutation.isLoading || updateMutation.isLoading || totalDebit !== totalCredit}
          >
            {entry ? 'Update' : 'Create'} Journal Entry
          </Button>
        </div>
      </form>
    </Form>
  )
}