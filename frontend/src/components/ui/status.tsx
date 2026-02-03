import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"
import { 
  CheckCircle, 
  XCircle, 
  AlertCircle, 
  Info, 
  Clock,
  Minus,
  HelpCircle
} from "lucide-react"

const badgeVariants = cva(
  "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-black uppercase tracking-wide border",
  {
    variants: {
      variant: {
        success: "bg-green-50 text-green-700 border-green-200",
        warning: "bg-amber-50 text-amber-700 border-amber-200", 
        danger: "bg-red-50 text-red-700 border-red-200",
        info: "bg-blue-50 text-blue-700 border-blue-200",
        secondary: "bg-slate-100 text-slate-700 border-slate-200",
        outline: "border-slate-300 text-slate-600 bg-transparent"
      },
      size: {
        sm: "text-[10px] px-2 py-0.5",
        md: "text-xs px-2.5 py-0.5",
        lg: "text-sm px-3 py-1"
      }
    },
    defaultVariants: {
      variant: "secondary",
      size: "md"
    }
  }
)

interface StatusBadgeComponentProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {
  icon?: boolean
}

function StatusBadgeComponent({ className, variant, size, icon = false, children, ...props }: StatusBadgeComponentProps) {
  const getIcon = () => {
    switch (variant) {
      case "success":
        return <CheckCircle className="h-3 w-3" />
      case "warning":
        return <AlertCircle className="h-3 w-3" />
      case "danger":
        return <XCircle className="h-3 w-3" />
      case "info":
        return <Info className="h-3 w-3" />
      default:
        return <Minus className="h-3 w-3" />
    }
  }

  return (
    <div className={cn(badgeVariants({ variant, size, className }))} {...props}>
      {icon && getIcon()}
      {children}
    </div>
  )
}

StatusBadgeComponent.displayName = "StatusBadgeComponent"

// Status Badge with predefined status types
interface StatusBadgeProps extends Omit<StatusBadgeComponentProps, "variant"> {
  status: "active" | "inactive" | "pending" | "completed" | "failed" | "draft" | "published" | "archived" | "suspended" | "processing"
  variant?: "success" | "warning" | "danger" | "info" | "secondary" | "outline"
}

function StatusBadge({ status, variant, children, ...props }: StatusBadgeProps) {
  const getStatusConfig = () => {
    switch (status.toLowerCase()) {
      case "active":
      case "published":
      case "completed":
        return { variant: "success" as const, text: "Active" }
      case "pending":
      case "processing":
      case "draft":
        return { variant: "warning" as const, text: "Pending" }
      case "inactive":
      case "archived":
        return { variant: "secondary" as const, text: "Inactive" }
      case "failed":
      case "suspended":
        return { variant: "danger" as const, text: "Failed" }
      default:
        return { variant: "secondary" as const, text: status }
    }
  }

  const config = getStatusConfig()
  const finalVariant = variant || config.variant
  
  return (
    <StatusBadgeComponent variant={finalVariant} {...props}>
      {children || config.text}
    </StatusBadgeComponent>
  )
}

StatusBadge.displayName = "StatusBadge"

// Icon Badge for displaying counts or notifications
interface IconBadgeProps {
  count: number
  variant?: "primary" | "success" | "warning" | "danger"
  maxCount?: number
  className?: string
}

function IconBadge({ count, variant = "danger", maxCount = 99, className }: IconBadgeProps) {
  const displayCount = count > maxCount ? `${maxCount}+` : count
  
  const variantClasses = {
    primary: "bg-blue-500 text-white",
    success: "bg-green-500 text-white",
    warning: "bg-amber-500 text-white",
    danger: "bg-red-500 text-white"
  }

  if (count === 0) return null

  return (
    <span className={cn(
      "absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold",
      variantClasses[variant],
      className
    )}>
      {displayCount}
    </span>
  )
}

IconBadge.displayName = "IconBadge"

// Loading Badge
interface LoadingBadgeProps extends StatusBadgeComponentProps {
  loading?: boolean
}

function LoadingBadge({ loading = true, children, ...props }: LoadingBadgeProps) {
  return (
    <StatusBadgeComponent variant="secondary" {...props}>
      {loading && (
        <div className="h-2 w-2 rounded-full bg-current animate-pulse" />
      )}
      {children || "Loading"}
    </StatusBadgeComponent>
  )
}

LoadingBadge.displayName = "LoadingBadge"

// Empty State Component
interface EmptyStateProps {
  title: string
  description?: string
  icon?: React.ReactNode
  action?: React.ReactNode
  className?: string
}

function EmptyState({ title, description, icon, action, className }: EmptyStateProps) {
  return (
    <div className={cn("flex flex-col items-center justify-center py-12 text-center", className)}>
      {icon && (
        <div className="mb-4 text-slate-300">
          {icon}
        </div>
      )}
      <h3 className="text-lg font-medium text-slate-900 mb-1">{title}</h3>
      {description && (
        <p className="text-slate-500 max-w-md">{description}</p>
      )}
      {action && (
        <div className="mt-6">
          {action}
        </div>
      )}
    </div>
  )
}

EmptyState.displayName = "EmptyState"

// Standardized Empty States
const EmptyStates = {
  data: (
    <EmptyState
      title="No Data Found"
      description="There is no data to display at this time."
      icon={<HelpCircle className="h-12 w-12" />}
    />
  ),
  search: (
    <EmptyState
      title="No Results Found"
      description="Try adjusting your search criteria or filters."
      icon={<HelpCircle className="h-12 w-12" />}
    />
  ),
  error: (
    <EmptyState
      title="Something Went Wrong"
      description="We encountered an error while loading the data."
      icon={<XCircle className="h-12 w-12 text-red-400" />}
    />
  )
}

export {
  StatusBadgeComponent as Badge,
  StatusBadge,
  IconBadge,
  LoadingBadge,
  EmptyState,
  EmptyStates,
  badgeVariants
}

export type {
  StatusBadgeComponentProps as BadgeProps,
  StatusBadgeProps,
  IconBadgeProps,
  LoadingBadgeProps,
  EmptyStateProps
}