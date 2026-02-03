import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'
import { LabelHTMLAttributes, forwardRef } from 'react'

const labelVariants = cva(
  'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
  {
    variants: {
      size: {
        sm: 'text-xs',
        default: 'text-sm',
        lg: 'text-base',
        xl: 'text-lg',
      },
      weight: {
        normal: 'font-normal',
        medium: 'font-medium',
        semibold: 'font-semibold',
        bold: 'font-bold',
      },
      color: {
        default: 'text-foreground',
        muted: 'text-muted-foreground',
        primary: 'text-primary',
        secondary: 'text-secondary-foreground',
        success: 'text-emerald-600',
        warning: 'text-amber-600',
        error: 'text-destructive',
        info: 'text-blue-600',
      }
    },
    defaultVariants: {
      size: 'default',
      weight: 'medium',
      color: 'default'
    }
  }
)

export interface LabelProps
  extends LabelHTMLAttributes<HTMLLabelElement>,
    VariantProps<typeof labelVariants> {}

const Label = forwardRef<HTMLLabelElement, LabelProps>(
  ({ className, size, weight, color, ...props }, ref) => (
    <label
      ref={ref}
      className={cn(labelVariants({ size, weight, color, className }))}
      {...props}
    />
  )
)
Label.displayName = 'Label'

export { Label, labelVariants }