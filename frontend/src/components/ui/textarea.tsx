import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'
import { TextareaHTMLAttributes, forwardRef } from 'react'

const textareaVariants = cva(
  'flex w-full rounded-lg border border-input bg-background px-4 py-3 text-base transition-all duration-200 placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50 min-h-[120px]',
  {
    variants: {
      variant: {
        default: 'shadow-sm hover:shadow-md focus:shadow-lg',
        outline: 'border-2 border-input shadow-sm hover:shadow-md focus:shadow-lg',
        filled: 'bg-muted shadow-sm hover:shadow-md',
        minimal: 'border-0 bg-transparent shadow-sm focus:bg-background',
      },
      size: {
        sm: 'text-sm px-3 py-2',
        default: 'text-base py-3',
        lg: 'text-lg px-5 py-4',
      },
      state: {
        default: '',
        success: 'border-emerald-500 focus:ring-emerald-300/50 text-emerald-700 bg-emerald-50',
        warning: 'border-amber-500 focus:ring-amber-300/50 text-amber-700 bg-amber-50',
        error: 'border-red-500 focus:ring-red-300/50 text-red-700 bg-red-50',
        info: 'border-blue-500 focus:ring-blue-300/50 text-blue-700 bg-blue-50',
      },
      resize: {
        none: 'resize-none',
        vertical: 'resize-y',
        horizontal: 'resize-x',
        both: 'resize',
      }
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
      state: 'default',
      resize: 'vertical'
    }
  }
)

export interface TextareaProps
  extends TextareaHTMLAttributes<HTMLTextAreaElement>,
    VariantProps<typeof textareaVariants> {
  leftIcon?: React.ReactNode
}

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ 
    className, 
    variant, 
    size, 
    state,
    resize,
    leftIcon,
    ...props 
  }, ref) => {
    return (
      <div className="relative w-full">
        {leftIcon && (
          <div className="absolute left-3 top-3 text-muted-foreground">
            {leftIcon}
          </div>
        )}
        <textarea
          className={cn(
            textareaVariants({ variant, size, state, resize, className }),
            leftIcon && 'pl-10'
          )}
          ref={ref}
          {...props}
        />
      </div>
    )
  }
)
Textarea.displayName = 'Textarea'

export { Textarea, textareaVariants }