import * as React from "react"
import { FocusVisible } from "@/lib/accessibility"

interface AccessibilityProviderProps {
  children: React.ReactNode
}

export function AccessibilityProvider({ children }: AccessibilityProviderProps) {
  return (
    <>
      <FocusVisible />
      {children}
    </>
  )
}

// Enhanced components with accessibility features
import { Button as BaseButton } from "@/components/ui/button"
import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuItem, 
  DropdownMenuTrigger 
} from "@/components/ui/dropdown-menu"
import { KEYS, useFocusManagement, useScreenReader } from "@/lib/accessibility"
import { cn } from "@/lib/utils"

// Accessible Button with keyboard support
interface AccessibleButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  loading?: boolean
  icon?: React.ReactNode
  iconPosition?: "left" | "right"
}

const AccessibleButton = React.forwardRef<HTMLButtonElement, AccessibleButtonProps>(
  ({ 
    children, 
    loading = false, 
    icon,
    iconPosition = "left",
    className,
    disabled,
    onClick,
    onKeyDown,
    ...props 
  }, ref) => {
    const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => {
      if (!loading && !disabled) {
        onClick?.(e)
      }
    }

    const handleKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
      if ((e.key === KEYS.ENTER || e.key === KEYS.SPACE) && !loading && !disabled) {
        e.preventDefault()
        onClick?.(e as any)
      }
      onKeyDown?.(e)
    }

    return (
      <BaseButton
        ref={ref}
        className={cn(
          "relative",
          loading && "opacity-70 cursor-not-allowed",
          className
        )}
        disabled={disabled || loading}
        onClick={handleClick}
        onKeyDown={handleKeyDown}
        role="button"
        tabIndex={disabled ? -1 : 0}
        aria-busy={loading}
        aria-disabled={disabled || loading}
        {...props}
      >
        {loading && (
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
          </div>
        )}
        <span className={cn(loading && "invisible")}>
          {icon && iconPosition === "left" && (
            <span className="mr-2">{icon}</span>
          )}
          {children}
          {icon && iconPosition === "right" && (
            <span className="ml-2">{icon}</span>
          )}
        </span>
      </BaseButton>
    )
  }
)
AccessibleButton.displayName = "AccessibleButton"

// Accessible Dropdown Menu
interface AccessibleDropdownMenuProps {
  trigger: React.ReactNode
  items: Array<{
    label: string
    value: string
    icon?: React.ReactNode
    disabled?: boolean
    onSelect?: () => void
  }>
  align?: "start" | "center" | "end"
  className?: string
}

function AccessibleDropdownMenu({ 
  trigger, 
  items, 
  align = "center",
  className 
}: AccessibleDropdownMenuProps) {
  const { trapFocus } = useFocusManagement()
  const { announce } = useScreenReader()
  const [isOpen, setIsOpen] = React.useState(false)

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open)
    if (open) {
      announce("Dropdown menu opened")
    } else {
      announce("Dropdown menu closed")
    }
  }

  const handleItemSelect = (item: typeof items[0]) => {
    if (!item.disabled && item.onSelect) {
      item.onSelect()
      announce(`${item.label} selected`)
    }
  }

  return (
    <DropdownMenu open={isOpen} onOpenChange={handleOpenChange}>
      <DropdownMenuTrigger asChild>
        {trigger}
      </DropdownMenuTrigger>
      <DropdownMenuContent 
        align={align}
        className={cn(
          "w-56",
          className
        )}
        onCloseAutoFocus={(e) => {
          // Prevent focus from returning to trigger when menu closes
          e.preventDefault()
        }}
      >
        {items.map((item) => (
          <DropdownMenuItem
            key={item.value}
            disabled={item.disabled}
            onSelect={() => handleItemSelect(item)}
            className={cn(
              "cursor-pointer",
              item.disabled && "cursor-not-allowed opacity-50"
            )}
          >
            {item.icon && <span className="mr-2">{item.icon}</span>}
            {item.label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

AccessibleDropdownMenu.displayName = "AccessibleDropdownMenu"

// Accessible Data Table with keyboard navigation
interface AccessibleDataTableProps {
  columns: Array<{
    key: string
    label: string
    sortable?: boolean
  }>
  data: Array<Record<string, any>>
  onRowClick?: (row: Record<string, any>) => void
  className?: string
}

function AccessibleDataTable({ 
  columns, 
  data, 
  onRowClick,
  className 
}: AccessibleDataTableProps) {
  const tableRef = React.useRef<HTMLTableElement>(null)
  const { announce } = useScreenReader()

  const handleKeyDown = (e: React.KeyboardEvent, rowIndex: number, rowData: Record<string, any>) => {
    switch (e.key) {
      case KEYS.ENTER:
      case KEYS.SPACE:
        e.preventDefault()
        if (onRowClick) {
          onRowClick(rowData)
          announce(`Row ${rowIndex + 1} activated`)
        }
        break
      case KEYS.ARROW_UP:
        e.preventDefault()
        if (rowIndex > 0) {
          const prevRow = tableRef.current?.rows[rowIndex]
          if (prevRow) {
            prevRow.focus()
            announce(`Row ${rowIndex} selected`)
          }
        }
        break
      case KEYS.ARROW_DOWN:
        e.preventDefault()
        if (rowIndex < data.length - 1) {
          const nextRow = tableRef.current?.rows[rowIndex + 2] // +2 because header is row 0
          if (nextRow) {
            nextRow.focus()
            announce(`Row ${rowIndex + 2} selected`)
          }
        }
        break
    }
  }

  return (
    <div className="rounded-md border">
      <table 
        ref={tableRef}
        className={cn("w-full caption-bottom text-sm", className)}
        role="table"
      >
        <thead className="[&_tr]:border-b">
          <tr className="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
            {columns.map((column) => (
              <th 
                key={column.key}
                className="h-12 px-4 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0"
                scope="col"
                aria-sort={column.sortable ? "none" : undefined}
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="[&_tr:last-child]:border-0">
          {data.map((row, rowIndex) => (
            <tr
              key={rowIndex}
              className="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted"
              tabIndex={onRowClick ? 0 : undefined}
              onKeyDown={(e) => handleKeyDown(e, rowIndex, row)}
              onClick={() => onRowClick?.(row)}
              role={onRowClick ? "button" : "row"}
              aria-label={`Row ${rowIndex + 1}`}
            >
              {columns.map((column) => (
                <td 
                  key={column.key}
                  className="p-4 align-middle [&:has([role=checkbox])]:pr-0"
                >
                  {row[column.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

AccessibleDataTable.displayName = "AccessibleDataTable"

export {
  AccessibilityProvider,
  AccessibleButton,
  AccessibleDropdownMenu,
  AccessibleDataTable,
  type AccessibleButtonProps,
  type AccessibleDropdownMenuProps,
  type AccessibleDataTableProps
}