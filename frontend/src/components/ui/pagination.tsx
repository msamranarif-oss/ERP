import * as React from "react"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { 
  ChevronLeft, 
  ChevronRight, 
  ChevronsLeft, 
  ChevronsRight,
  MoreHorizontal
} from "lucide-react"

const Pagination = ({ className, ...props }: React.ComponentProps<"nav">) => (
  <nav
    role="navigation"
    aria-label="pagination"
    className={cn("flex items-center justify-between", className)}
    {...props}
  />
)
Pagination.displayName = "Pagination"

const PaginationContent = React.forwardRef<
  HTMLDivElement,
  React.ComponentProps<"div">
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn("flex items-center gap-2", className)}
    {...props}
  />
))
PaginationContent.displayName = "PaginationContent"

const PaginationItem = React.forwardRef<
  HTMLDivElement,
  React.ComponentProps<"div">
>(({ className, ...props }, ref) => (
  <div ref={ref} className={cn("flex items-center", className)} {...props} />
))
PaginationItem.displayName = "PaginationItem"

type PaginationLinkProps = {
  isActive?: boolean
  disabled?: boolean
} & React.ComponentProps<typeof Button>

const PaginationLink = ({
  className,
  isActive,
  disabled,
  size = "icon",
  variant = "outline",
  ...props
}: PaginationLinkProps) => (
  <Button
    aria-current={isActive ? "page" : undefined}
    disabled={disabled}
    className={cn(
      "h-9 w-9 p-0",
      isActive && "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground",
      disabled && "opacity-50 cursor-not-allowed",
      className
    )}
    size={size}
    variant={variant}
    {...props}
  />
)
PaginationLink.displayName = "PaginationLink"

const PaginationPrevious = ({
  className,
  disabled,
  ...props
}: React.ComponentProps<typeof PaginationLink>) => (
  <PaginationLink
    aria-label="Go to previous page"
    disabled={disabled}
    className={cn("gap-1 pl-2.5", className)}
    {...props}
  >
    <ChevronLeft className="h-4 w-4" />
    <span className="sr-only">Previous</span>
  </PaginationLink>
)
PaginationPrevious.displayName = "PaginationPrevious"

const PaginationNext = ({
  className,
  disabled,
  ...props
}: React.ComponentProps<typeof PaginationLink>) => (
  <PaginationLink
    aria-label="Go to next page"
    disabled={disabled}
    className={cn("gap-1 pr-2.5", className)}
    {...props}
  >
    <span className="sr-only">Next</span>
    <ChevronRight className="h-4 w-4" />
  </PaginationLink>
)
PaginationNext.displayName = "PaginationNext"

const PaginationFirst = ({
  className,
  disabled,
  ...props
}: React.ComponentProps<typeof PaginationLink>) => (
  <PaginationLink
    aria-label="Go to first page"
    disabled={disabled}
    className={cn("gap-1 pl-2.5", className)}
    {...props}
  >
    <ChevronsLeft className="h-4 w-4" />
    <span className="sr-only">First</span>
  </PaginationLink>
)
PaginationFirst.displayName = "PaginationFirst"

const PaginationLast = ({
  className,
  disabled,
  ...props
}: React.ComponentProps<typeof PaginationLink>) => (
  <PaginationLink
    aria-label="Go to last page"
    disabled={disabled}
    className={cn("gap-1 pr-2.5", className)}
    {...props}
  >
    <span className="sr-only">Last</span>
    <ChevronsRight className="h-4 w-4" />
  </PaginationLink>
)
PaginationLast.displayName = "PaginationLast"

const PaginationEllipsis = ({
  className,
  ...props
}: React.ComponentProps<"span">) => (
  <span
    aria-hidden
    className={cn("flex h-9 w-9 items-center justify-center", className)}
    {...props}
  >
    <MoreHorizontal className="h-4 w-4" />
    <span className="sr-only">More pages</span>
  </span>
)
PaginationEllipsis.displayName = "PaginationEllipsis"

const PaginationInfo = ({
  className,
  currentPage,
  totalPages,
  totalItems,
  itemsPerPage,
  from,
  to,
  ...props
}: React.ComponentProps<"div"> & {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
  from: number
  to: number
}) => (
  <div 
    className={cn("text-sm text-muted-foreground font-medium", className)} 
    {...props}
  >
    Showing <span className="font-semibold text-foreground">{from}</span> to{' '}
    <span className="font-semibold text-foreground">{to}</span> of{' '}
    <span className="font-semibold text-foreground">{totalItems}</span> results
    {totalPages > 1 && (
      <span className="ml-2">
        (Page <span className="font-semibold text-foreground">{currentPage}</span> of{' '}
        <span className="font-semibold text-foreground">{totalPages}</span>)
      </span>
    )}
  </div>
)
PaginationInfo.displayName = "PaginationInfo"

// Pagination Size Variants
type PaginationSize = "sm" | "md" | "lg"

interface UsePaginationProps {
  currentPage: number
  totalPages: number
  siblingCount?: number
  size?: PaginationSize
}

const usePagination = ({
  currentPage,
  totalPages,
  siblingCount = 1,
  size = "md"
}: UsePaginationProps) => {
  const DOTS = "..."
  
  // Handle edge cases
  if (totalPages <= 1) return [1]
  
  const leftSiblingIndex = Math.max(currentPage - siblingCount, 1)
  const rightSiblingIndex = Math.min(currentPage + siblingCount, totalPages)
  
  const shouldShowLeftDots = leftSiblingIndex > 2
  const shouldShowRightDots = rightSiblingIndex < totalPages - 1
  
  const firstPageIndex = 1
  const lastPageIndex = totalPages

  // Only show DOTS when there is just one page number to be inserted
  if (!shouldShowLeftDots && shouldShowRightDots) {
    let leftRange = []
    for (let i = 1; i <= leftSiblingIndex + 1; i++) {
      leftRange.push(i)
    }
    return [...leftRange, DOTS, totalPages]
  }
  
  // Show DOTS on right side when there are hidden pages in the left side
  if (shouldShowLeftDots && !shouldShowRightDots) {
    let rightRange = []
    for (let i = totalPages - (2 * siblingCount + 1); i <= totalPages; i++) {
      rightRange.push(i)
    }
    return [firstPageIndex, DOTS, ...rightRange]
  }
  
  // Show DOTS on both sides when there are hidden pages on both sides
  if (shouldShowLeftDots && shouldShowRightDots) {
    let middleRange = []
    for (let i = leftSiblingIndex; i <= rightSiblingIndex; i++) {
      middleRange.push(i)
    }
    return [firstPageIndex, DOTS, ...middleRange, DOTS, lastPageIndex]
  }
  
  // Show all pages when no dots are needed
  let range = []
  for (let i = 1; i <= totalPages; i++) {
    range.push(i)
  }
  return range
}

export {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
  PaginationFirst,
  PaginationLast,
  PaginationInfo,
  usePagination,
  type PaginationSize
}