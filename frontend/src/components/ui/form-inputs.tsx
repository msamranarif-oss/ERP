import * as React from "react"
import { cn } from "@/lib/utils"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { 
  Calendar, 
  DollarSign, 
  Percent, 
  Hash,
  Search,
  Eye,
  EyeOff
} from "lucide-react"

// Enhanced Input with icons and validation
interface FormInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  description?: string
  required?: boolean
  icon?: React.ReactNode
  prefix?: string
  suffix?: string
  type?: string
}

const FormInput = React.forwardRef<HTMLInputElement, FormInputProps>(
  ({ 
    className, 
    label,
    error,
    description,
    required = false,
    icon,
    prefix,
    suffix,
    type = "text",
    id,
    ...props 
  }, ref) => {
    const inputId = id || `input-${Math.random().toString(36).substr(2, 9)}`
    const hasError = !!error
    
    return (
      <div className="space-y-2">
        {label && (
          <Label 
            htmlFor={inputId} 
            className={cn(
              "text-sm font-medium text-slate-700",
              required && "after:content-['*'] after:ml-0.5 after:text-red-500"
            )}
          >
            {label}
          </Label>
        )}
        
        <div className="relative">
          {icon && (
            <div className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
              {icon}
            </div>
          )}
          
          {prefix && (
            <div className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm">
              {prefix}
            </div>
          )}
          
          <Input
            id={inputId}
            ref={ref}
            type={type}
            className={cn(
              "h-11 transition-colors",
              icon && "pl-10",
              prefix && "pl-8",
              suffix && "pr-8",
              hasError && "border-red-500 focus-visible:ring-red-500",
              className
            )}
            {...props}
          />
          
          {suffix && (
            <div className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm">
              {suffix}
            </div>
          )}
        </div>
        
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
FormInput.displayName = "FormInput"

// Predefined specialized inputs
const FormInputCurrency = React.forwardRef<HTMLInputElement, Omit<FormInputProps, 'type' | 'prefix' | 'icon'>>(
  ({ className, ...props }, ref) => (
    <FormInput
      ref={ref}
      type="number"
      step="0.01"
      placeholder="0.00"
      icon={<DollarSign className="h-4 w-4" />}
      prefix="$"
      className={className}
      {...props}
    />
  )
)
FormInputCurrency.displayName = "FormInputCurrency"

const FormInputPercentage = React.forwardRef<HTMLInputElement, Omit<FormInputProps, 'type' | 'suffix' | 'icon'>>(
  ({ className, ...props }, ref) => (
    <FormInput
      ref={ref}
      type="number"
      step="0.01"
      placeholder="0"
      icon={<Percent className="h-4 w-4" />}
      suffix="%"
      className={className}
      {...props}
    />
  )
)
FormInputPercentage.displayName = "FormInputPercentage"

const FormInputNumber = React.forwardRef<HTMLInputElement, Omit<FormInputProps, 'type' | 'icon'>>(
  ({ className, ...props }, ref) => (
    <FormInput
      ref={ref}
      type="number"
      icon={<Hash className="h-4 w-4" />}
      className={className}
      {...props}
    />
  )
)
FormInputNumber.displayName = "FormInputNumber"

const FormInputSearch = React.forwardRef<HTMLInputElement, Omit<FormInputProps, 'type' | 'icon'>>(
  ({ className, ...props }, ref) => (
    <FormInput
      ref={ref}
      type="search"
      icon={<Search className="h-4 w-4" />}
      className={className}
      {...props}
    />
  )
)
FormInputSearch.displayName = "FormInputSearch"

// Enhanced Textarea
interface FormTextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string
  error?: string
  description?: string
  required?: boolean
  rows?: number
}

const FormTextarea = React.forwardRef<HTMLTextAreaElement, FormTextareaProps>(
  ({ 
    className, 
    label,
    error,
    description,
    required = false,
    rows = 4,
    id,
    ...props 
  }, ref) => {
    const textareaId = id || `textarea-${Math.random().toString(36).substr(2, 9)}`
    const hasError = !!error
    
    return (
      <div className="space-y-2">
        {label && (
          <Label 
            htmlFor={textareaId} 
            className={cn(
              "text-sm font-medium text-slate-700",
              required && "after:content-['*'] after:ml-0.5 after:text-red-500"
            )}
          >
            {label}
          </Label>
        )}
        
        <Textarea
          id={textareaId}
          ref={ref}
          rows={rows}
          className={cn(
            "min-h-24 transition-colors resize-none",
            hasError && "border-red-500 focus-visible:ring-red-500",
            className
          )}
          {...props}
        />
        
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
FormTextarea.displayName = "FormTextarea"

// Enhanced Select
interface FormSelectProps {
  label?: string
  error?: string
  description?: string
  required?: boolean
  placeholder?: string
  options: Array<{ value: string; label: string; disabled?: boolean }>
  value?: string
  onValueChange?: (value: string) => void
  className?: string
  disabled?: boolean
}

const FormSelect = React.forwardRef<HTMLDivElement, FormSelectProps>(
  ({ 
    label,
    error,
    description,
    required = false,
    placeholder = "Select an option",
    options,
    value,
    onValueChange,
    className,
    disabled = false,
    ...props 
  }, ref) => {
    const hasError = !!error
    
    return (
      <div className="space-y-2">
        {label && (
          <Label 
            className={cn(
              "text-sm font-medium text-slate-700",
              required && "after:content-['*'] after:ml-0.5 after:text-red-500"
            )}
          >
            {label}
          </Label>
        )}
        
        <Select 
          value={value} 
          onValueChange={onValueChange}
          disabled={disabled}
        >
          <SelectTrigger 
            className={cn(
              "h-11 transition-colors",
              hasError && "border-red-500 focus:ring-red-500",
              className
            )}
            {...props}
          >
            <SelectValue placeholder={placeholder} />
          </SelectTrigger>
          <SelectContent>
            {options.map((option) => (
              <SelectItem 
                key={option.value} 
                value={option.value}
                disabled={option.disabled}
              >
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        
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
FormSelect.displayName = "FormSelect"

// Enhanced Checkbox with label
interface FormCheckboxProps {
  label: string
  description?: string
  checked?: boolean
  onCheckedChange?: (checked: boolean) => void
  disabled?: boolean
  className?: string
  id?: string
}

const FormCheckbox = React.forwardRef<HTMLButtonElement, FormCheckboxProps>(
  ({ 
    label,
    description,
    checked,
    onCheckedChange,
    disabled = false,
    className,
    id,
    ...props 
  }, ref) => {
    const checkboxId = id || `checkbox-${Math.random().toString(36).substr(2, 9)}`
    
    return (
      <div className="space-y-1">
        <div className="flex items-center space-x-3">
          <Checkbox
            id={checkboxId}
            ref={ref}
            checked={checked}
            onCheckedChange={onCheckedChange}
            disabled={disabled}
            className={cn(
              "rounded data-[state=checked]:bg-blue-600 data-[state=checked]:text-white",
              className
            )}
            {...props}
          />
          <Label 
            htmlFor={checkboxId} 
            className={cn(
              "text-sm font-medium",
              disabled && "text-slate-400"
            )}
          >
            {label}
          </Label>
        </div>
        
        {description && (
          <p className="text-xs text-slate-500 ml-7">{description}</p>
        )}
      </div>
    )
  }
)
FormCheckbox.displayName = "FormCheckbox"

// Enhanced Switch with label
interface FormSwitchProps {
  label: string
  description?: string
  checked?: boolean
  onCheckedChange?: (checked: boolean) => void
  disabled?: boolean
  className?: string
  id?: string
}

const FormSwitch = React.forwardRef<HTMLButtonElement, FormSwitchProps>(
  ({ 
    label,
    description,
    checked,
    onCheckedChange,
    disabled = false,
    className,
    id,
    ...props 
  }, ref) => {
    const switchId = id || `switch-${Math.random().toString(36).substr(2, 9)}`
    
    return (
      <div className="space-y-1">
        <div className="flex items-center justify-between">
          <div>
            <Label 
              htmlFor={switchId} 
              className={cn(
                "text-sm font-medium",
                disabled && "text-slate-400"
              )}
            >
              {label}
            </Label>
          </div>
          <Switch
            id={switchId}
            ref={ref}
            checked={checked}
            onCheckedChange={onCheckedChange}
            disabled={disabled}
            className={className}
            {...props}
          />
        </div>
        
        {description && (
          <p className="text-xs text-slate-500">{description}</p>
        )}
      </div>
    )
  }
)
FormSwitch.displayName = "FormSwitch"

// Password Input with show/hide
interface FormPasswordInputProps extends Omit<FormInputProps, 'type'> {
  showPassword?: boolean
  onShowPasswordChange?: (show: boolean) => void
}

const FormPasswordInput = React.forwardRef<HTMLInputElement, FormPasswordInputProps>(
  ({ 
    showPassword = false,
    onShowPasswordChange,
    className,
    ...props 
  }, ref) => {
    const [showPass, setShowPass] = React.useState(showPassword)
    
    const handleTogglePassword = () => {
      const newState = !showPass
      setShowPass(newState)
      onShowPasswordChange?.(newState)
    }
    
    return (
      <div className="relative">
        <FormInput
          ref={ref}
          type={showPass ? "text" : "password"}
          className={cn("pr-10", className)}
          {...props}
        />
        <button
          type="button"
          onClick={handleTogglePassword}
          className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
          aria-label={showPass ? "Hide password" : "Show password"}
        >
          {showPass ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
    )
  }
)
FormPasswordInput.displayName = "FormPasswordInput"

export {
  FormInput,
  FormInputCurrency,
  FormInputPercentage,
  FormInputNumber,
  FormInputSearch,
  FormTextarea,
  FormSelect,
  FormCheckbox,
  FormSwitch,
  FormPasswordInput,
  type FormInputProps,
  type FormTextareaProps,
  type FormSelectProps,
  type FormCheckboxProps,
  type FormSwitchProps,
  type FormPasswordInputProps
}