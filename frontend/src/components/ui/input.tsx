import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'
import { InputHTMLAttributes, forwardRef } from 'react'

const inputVariants = cva(
  'flex w-full rounded-lg border border-input bg-background px-4 py-2.5 text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'shadow-sm hover:shadow-md focus:shadow-lg',
        outline: 'border-2 border-input shadow-sm hover:shadow-md focus:shadow-lg',
        filled: 'bg-muted shadow-sm hover:shadow-md',
        minimal: 'border-0 bg-transparent shadow-sm focus:bg-background',
      },
      size: {
        sm: 'h-9 rounded-md px-3 text-sm py-1.5',
        default: 'h-11 py-2.5',
        lg: 'h-12 rounded-lg px-5 py-3 text-base',
        xl: 'h-14 rounded-xl px-6 py-3.5 text-lg',
      },
      state: {
        default: '',
        success: 'border-emerald-500 focus:ring-emerald-300/50 text-emerald-700 bg-emerald-50',
        warning: 'border-amber-500 focus:ring-amber-300/50 text-amber-700 bg-amber-50',
        error: 'border-red-500 focus:ring-red-300/50 text-red-700 bg-red-50',
        info: 'border-blue-500 focus:ring-blue-300/50 text-blue-700 bg-blue-50',
      }
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
      state: 'default'
    }
  }
)

export interface InputProps
  extends InputHTMLAttributes<HTMLInputElement>,
    VariantProps<typeof inputVariants> {
  leftIcon?: React.ReactNode
  rightIcon?: React.ReactNode
}

const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ 
    className, 
    variant, 
    size, 
    state,
    type,
    leftIcon,
    rightIcon,
    ...props 
  }, ref) => {
    return (
      <div className="relative w-full">
        {leftIcon && (
          <div className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
            {leftIcon}
          </div>
        )}
        <input
          type={type}
          className={cn(
            inputVariants({ variant, size, state, className }),
            leftIcon && 'pl-10',
            rightIcon && 'pr-10'
          )}
          ref={ref}
          {...props}
        />
        {rightIcon && (
          <div className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">
            {rightIcon}
          </div>
        )}
      </div>
    )
  }
)
Input.displayName = 'Input'

export { Input, inputVariants }