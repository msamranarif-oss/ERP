import * as React from "react"
import { cn } from "@/lib/utils"
import { Loader2, RefreshCw } from "lucide-react"

interface LoadingSpinnerProps extends React.HTMLAttributes<HTMLDivElement> {
  size?: "sm" | "md" | "lg"
  variant?: "primary" | "secondary" | "success" | "warning" | "danger"
}

function LoadingSpinner({ 
  size = "md", 
  variant = "primary",
  className,
  ...props 
}: LoadingSpinnerProps) {
  const sizeClasses = {
    sm: "h-4 w-4",
    md: "h-6 w-6",
    lg: "h-8 w-8"
  }

  const variantClasses = {
    primary: "text-blue-500",
    secondary: "text-slate-500",
    success: "text-green-500",
    warning: "text-amber-500",
    danger: "text-red-500"
  }

  return (
    <div 
      className={cn(
        "flex items-center justify-center",
        className
      )}
      {...props}
    >
      <Loader2 
        className={cn(
          "animate-spin",
          sizeClasses[size],
          variantClasses[variant]
        )} 
      />
    </div>
  )
}

LoadingSpinner.displayName = "LoadingSpinner"

interface LoadingOverlayProps extends React.HTMLAttributes<HTMLDivElement> {
  loading?: boolean
  message?: string
  spinnerSize?: "sm" | "md" | "lg"
  variant?: "primary" | "secondary" | "success" | "warning" | "danger"
}

function LoadingOverlay({ 
  loading = true,
  message,
  spinnerSize = "md",
  variant = "primary",
  className,
  children,
  ...props 
}: LoadingOverlayProps) {
  if (!loading) return children

  return (
    <div className={cn("relative", className)} {...props}>
      {children}
      <div className="absolute inset-0 bg-white/70 backdrop-blur-sm flex items-center justify-center rounded-lg">
        <div className="flex flex-col items-center gap-3">
          <LoadingSpinner size={spinnerSize} variant={variant} />
          {message && (
            <p className="text-sm text-slate-600 font-medium">{message}</p>
          )}
        </div>
      </div>
    </div>
  )
}

LoadingOverlay.displayName = "LoadingOverlay"

interface LoadingButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  loading?: boolean
  spinnerSize?: "sm" | "md"
}

const LoadingButton = React.forwardRef<HTMLButtonElement, LoadingButtonProps>(
  ({ loading = false, spinnerSize = "sm", children, className, ...props }, ref) => {
    return (
      <button
        ref={ref}
        className={cn(
          "inline-flex items-center justify-center gap-2",
          loading && "opacity-70 cursor-not-allowed",
          className
        )}
        disabled={loading}
        {...props}
      >
        {loading && (
          <LoadingSpinner 
            size={spinnerSize} 
            variant="secondary" 
            className="!justify-start" 
          />
        )}
        {children}
      </button>
    )
  }
)
LoadingButton.displayName = "LoadingButton"

interface LoadingSkeletonProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: "text" | "rect" | "circle" | "card"
  width?: string
  height?: string
}

function LoadingSkeleton({ 
  variant = "text",
  width,
  height,
  className,
  ...props 
}: LoadingSkeletonProps) {
  const baseClasses = "animate-pulse bg-slate-200 rounded"
  
  const variantClasses = {
    text: "h-4 rounded",
    rect: "rounded",
    circle: "rounded-full",
    card: "rounded-lg"
  }

  const sizeStyles = {
    text: { width: width || "100%", height: height || "1rem" },
    rect: { width: width || "100%", height: height || "100%" },
    circle: { width: width || "2rem", height: height || "2rem" },
    card: { width: width || "100%", height: height || "8rem" }
  }

  return (
    <div
      className={cn(
        baseClasses,
        variantClasses[variant],
        className
      )}
      style={sizeStyles[variant]}
      {...props}
    />
  )
}

LoadingSkeleton.displayName = "LoadingSkeleton"

// Predefined skeleton layouts
interface DataListSkeletonProps {
  count?: number
  showImage?: boolean
  className?: string
}

function DataListSkeleton({ count = 5, showImage = false, className }: DataListSkeletonProps) {
  return (
    <div className={cn("space-y-4", className)}>
      {Array.from({ length: count }).map((_, index) => (
        <div key={index} className="flex items-center gap-4 p-4 bg-white rounded-lg border">
          {showImage && (
            <LoadingSkeleton variant="circle" width="3rem" height="3rem" />
          )}
          <div className="flex-1 space-y-2">
            <LoadingSkeleton variant="text" width="60%" height="1.25rem" />
            <LoadingSkeleton variant="text" width="40%" height="1rem" />
          </div>
          <LoadingSkeleton variant="text" width="4rem" height="1.5rem" />
        </div>
      ))}
    </div>
  )
}

DataListSkeleton.displayName = "DataListSkeleton"

interface DataTableSkeletonProps {
  rows?: number
  columns?: number
  className?: string
}

function DataTableSkeleton({ rows = 5, columns = 4, className }: DataTableSkeletonProps) {
  return (
    <div className={cn("space-y-4", className)}>
      {/* Header skeleton */}
      <div className="grid grid-cols-12 gap-4 px-4 py-3 bg-slate-50 rounded-lg">
        {Array.from({ length: columns }).map((_, index) => (
          <div key={index} className="col-span-3">
            <LoadingSkeleton variant="text" width="80%" height="1rem" />
          </div>
        ))}
      </div>
      
      {/* Rows skeleton */}
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <div key={rowIndex} className="grid grid-cols-12 gap-4 px-4 py-3 border rounded-lg">
          {Array.from({ length: columns }).map((_, colIndex) => (
            <div key={colIndex} className="col-span-3">
              <LoadingSkeleton variant="text" width="90%" height="1rem" />
            </div>
          ))}
        </div>
      ))}
    </div>
  )
}

DataTableSkeleton.displayName = "DataTableSkeleton"

interface FormSkeletonProps {
  fields?: number
  className?: string
}

function FormSkeleton({ fields = 6, className }: FormSkeletonProps) {
  return (
    <div className={cn("space-y-6", className)}>
      {Array.from({ length: fields }).map((_, index) => (
        <div key={index} className="space-y-2">
          <LoadingSkeleton variant="text" width="30%" height="0.875rem" />
          <LoadingSkeleton variant="rect" height="2.75rem" />
        </div>
      ))}
      <div className="flex justify-end gap-3 pt-4">
        <LoadingSkeleton variant="rect" width="6rem" height="2.75rem" />
        <LoadingSkeleton variant="rect" width="8rem" height="2.75rem" />
      </div>
    </div>
  )
}

FormSkeleton.displayName = "FormSkeleton"

export {
  LoadingSpinner,
  LoadingOverlay,
  LoadingButton,
  LoadingSkeleton,
  DataListSkeleton,
  DataTableSkeleton,
  FormSkeleton,
  type LoadingSpinnerProps,
  type LoadingOverlayProps,
  type LoadingButtonProps,
  type LoadingSkeletonProps,
  type DataListSkeletonProps,
  type DataTableSkeletonProps,
  type FormSkeletonProps
}