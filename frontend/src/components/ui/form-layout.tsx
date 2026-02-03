import * as React from "react"
import { cn } from "@/lib/utils"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Loader2 } from "lucide-react"

interface FormLayoutProps {
  children: React.ReactNode
  onSubmit: (e: React.FormEvent) => void
  onCancel?: () => void
  isSubmitting?: boolean
  submitText?: string
  cancelText?: string
  className?: string
}

const FormLayout = React.forwardRef<HTMLFormElement, FormLayoutProps>(
  ({ 
    children, 
    onSubmit, 
    onCancel, 
    isSubmitting = false,
    submitText = "Submit",
    cancelText = "Cancel",
    className 
  }, ref) => {
    return (
      <form 
        ref={ref}
        onSubmit={onSubmit} 
        className={cn("space-y-8", className)}
      >
        {children}
        
        <div className="flex justify-end gap-4 pt-6 border-t border-slate-200">
          {onCancel && (
            <Button 
              type="button" 
              variant="outline" 
              onClick={onCancel}
              disabled={isSubmitting}
              className="px-6 h-11"
            >
              {cancelText}
            </Button>
          )}
          <Button 
            type="submit" 
            disabled={isSubmitting}
            className="px-6 h-11 bg-blue-600 hover:bg-blue-700"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                {submitText.includes('Create') ? 'Creating...' : 
                 submitText.includes('Update') ? 'Updating...' : 
                 submitText.includes('Save') ? 'Saving...' : 'Processing...'}
              </>
            ) : submitText}
          </Button>
        </div>
      </form>
    )
  }
)
FormLayout.displayName = "FormLayout"

interface FormSectionProps {
  title: string
  icon?: React.ReactNode
  children: React.ReactNode
  className?: string
}

const FormSection = React.forwardRef<HTMLDivElement, FormSectionProps>(
  ({ title, icon, children, className }, ref) => {
    return (
      <Card className={cn("border-0 shadow-sm", className)} ref={ref}>
        <CardHeader className="border-b bg-slate-50/50">
          <CardTitle className="flex items-center gap-2 text-lg font-semibold text-slate-800">
            {icon}
            {title}
          </CardTitle>
        </CardHeader>
        <CardContent className="p-6">
          {children}
        </CardContent>
      </Card>
    )
  }
)
FormSection.displayName = "FormSection"

interface FormGridProps {
  children: React.ReactNode
  columns?: 1 | 2 | 3 | 4
  className?: string
}

const FormGrid = React.forwardRef<HTMLDivElement, FormGridProps>(
  ({ children, columns = 2, className }, ref) => {
    const gridClasses = {
      1: "grid-cols-1",
      2: "grid-cols-1 md:grid-cols-2",
      3: "grid-cols-1 md:grid-cols-3",
      4: "grid-cols-1 md:grid-cols-2 lg:grid-cols-4"
    }
    
    return (
      <div 
        ref={ref}
        className={cn(`grid gap-6 ${gridClasses[columns]}`, className)}
      >
        {children}
      </div>
    )
  }
)
FormGrid.displayName = "FormGrid"

interface FormFieldProps {
  label: string
  htmlFor: string
  children: React.ReactNode
  error?: string
  description?: string
  required?: boolean
  className?: string
}

const FormField = React.forwardRef<HTMLDivElement, FormFieldProps>(
  ({ 
    label, 
    htmlFor, 
    children, 
    error, 
    description, 
    required = false,
    className 
  }, ref) => {
    return (
      <div ref={ref} className={cn("space-y-2", className)}>
        <label 
          htmlFor={htmlFor} 
          className={cn(
            "text-sm font-medium text-slate-700",
            required && "after:content-['*'] after:ml-0.5 after:text-red-500"
          )}
        >
          {label}
        </label>
        {children}
        {description && (
          <p className="text-xs text-slate-500">{description}</p>
        )}
        {error && (
          <p className="text-sm text-red-600">{error}</p>
        )}
      </div>
    )
  }
)
FormField.displayName = "FormField"

interface FormRowProps {
  children: React.ReactNode
  className?: string
}

const FormRow = React.forwardRef<HTMLDivElement, FormRowProps>(
  ({ children, className }, ref) => {
    return (
      <div 
        ref={ref} 
        className={cn("flex flex-col sm:flex-row gap-4", className)}
      >
        {children}
      </div>
    )
  }
)
FormRow.displayName = "FormRow"

interface FormActionsProps {
  children: React.ReactNode
  align?: "left" | "center" | "right"
  className?: string
}

const FormActions = React.forwardRef<HTMLDivElement, FormActionsProps>(
  ({ children, align = "right", className }, ref) => {
    const alignmentClasses = {
      left: "justify-start",
      center: "justify-center",
      right: "justify-end"
    }
    
    return (
      <div 
        ref={ref}
        className={cn(
          "flex items-center gap-3 pt-6 border-t border-slate-200",
          alignmentClasses[align],
          className
        )}
      >
        {children}
      </div>
    )
  }
)
FormActions.displayName = "FormActions"

// Predefined form layouts
interface TwoColumnFormProps {
  leftColumn: React.ReactNode
  rightColumn: React.ReactNode
  onSubmit: (e: React.FormEvent) => void
  onCancel?: () => void
  isSubmitting?: boolean
  submitText?: string
  cancelText?: string
  className?: string
}

const TwoColumnForm = React.forwardRef<HTMLFormElement, TwoColumnFormProps>(
  ({ 
    leftColumn, 
    rightColumn, 
    onSubmit, 
    onCancel, 
    isSubmitting,
    submitText,
    cancelText,
    className 
  }, ref) => {
    return (
      <FormLayout 
        ref={ref}
        onSubmit={onSubmit}
        onCancel={onCancel}
        isSubmitting={isSubmitting}
        submitText={submitText}
        cancelText={cancelText}
        className={className}
      >
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 space-y-6">
            {leftColumn}
          </div>
          <div className="space-y-6">
            {rightColumn}
          </div>
        </div>
      </FormLayout>
    )
  }
)
TwoColumnForm.displayName = "TwoColumnForm"

export {
  FormLayout,
  FormSection,
  FormGrid,
  FormField,
  FormRow,
  FormActions,
  TwoColumnForm,
  type FormLayoutProps,
  type FormSectionProps,
  type FormGridProps,
  type FormFieldProps,
  type FormRowProps,
  type FormActionsProps,
  type TwoColumnFormProps
}