import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

// UI Component Utility Classes
export const uiClasses = {
  // Page Layout
  page: {
    container: "space-y-6",
    header: "flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4",
    title: "text-2xl font-bold tracking-tight text-slate-900",
    description: "text-slate-500 mt-1"
  },
  
  // Section Layout
  section: {
    header: "flex items-center justify-between gap-4 py-4 border-b border-slate-200",
    title: "text-lg font-semibold text-slate-800"
  },
  
  // Card Variants
  card: {
    surface: "bg-white border border-slate-200 rounded-lg shadow-sm",
    elevated: "bg-white border border-slate-200 rounded-lg shadow-md",
    bordered: "bg-white border-2 border-slate-300 rounded-lg",
    content: "p-6"
  },
  
  // Button Variants
  button: {
    primary: "bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0",
    secondary: "bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium h-10 px-4 gap-2 transition-all duration-200 hover:shadow-sm",
    outline: "border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold h-10 px-4 gap-2 transition-all duration-200 hover:shadow-sm",
    destructive: "bg-red-600 hover:bg-red-700 text-white font-semibold h-10 px-4 gap-2 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0",
    ghost: "hover:bg-slate-100 text-slate-600 font-medium h-10 px-3 transition-all duration-200 hover:text-slate-900"
  },
  
  // Action Bars
  actionBar: {
    main: "flex items-center gap-3 flex-wrap",
    primary: "flex items-center gap-3"
  },
  
  // Status Badges
  badge: {
    success: "flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-green-100",
    warning: "flex items-center gap-1.5 text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-orange-100",
    danger: "flex items-center gap-1.5 text-red-600 bg-red-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-red-100",
    info: "flex items-center gap-1.5 text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-blue-100",
    secondary: "flex items-center gap-1.5 text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-slate-100"
  },
  
  // Table Styles
  table: {
    container: "rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden",
    headerCell: "h-12 text-slate-500 font-bold px-4 uppercase text-xs tracking-wider",
    dataCell: "px-4 py-3 text-slate-600 font-medium",
    row: "border-b border-slate-100 last:border-0 hover:bg-slate-50/50 transition-colors"
  },
  
  // Form Elements
  form: {
    group: "space-y-2",
    label: "block text-sm font-medium text-slate-700",
    input: "w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all",
    select: "w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all",
    textarea: "w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all min-h-[100px]"
  },
  
  // Loading States
  loading: {
    spinner: "inline-block h-5 w-5 animate-spin rounded-full border-2 border-current border-t-transparent",
    overlay: "absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-10"
  },
  
  // Empty States
  empty: {
    container: "flex flex-col items-center justify-center py-12 text-center",
    icon: "h-12 w-12 text-slate-300 mb-4",
    title: "text-lg font-medium text-slate-900 mb-1",
    description: "text-slate-500"
  },
  
  // Icons
  icon: {
    sm: "h-4 w-4",
    md: "h-5 w-5",
    lg: "h-6 w-6",
    xl: "h-8 w-8"
  },
  
  // Spacing Scale
  spacing: {
    xs: "space-x-1 space-y-1",
    sm: "space-x-2 space-y-2",
    md: "space-x-4 space-y-4",
    lg: "space-x-6 space-y-6",
    xl: "space-x-8 space-y-8"
  }
}

// Type definitions for better TypeScript support
export type ButtonVariant = keyof typeof uiClasses.button
export type CardVariant = keyof typeof uiClasses.card
export type BadgeVariant = keyof typeof uiClasses.badge

// Helper functions for common UI patterns
export const getButtonClass = (variant: ButtonVariant = "primary"): string => {
  return uiClasses.button[variant]
}

export const getCardClass = (variant: CardVariant = "surface"): string => {
  return uiClasses.card[variant]
}

export const getBadgeClass = (variant: BadgeVariant = "secondary"): string => {
  return uiClasses.badge[variant]
}

// Status badge helpers
export const getStatusBadgeClass = (status: string): string => {
  switch (status.toLowerCase()) {
    case 'active':
    case 'published':
    case 'completed':
    case 'settled':
      return uiClasses.badge.success
    case 'pending':
    case 'draft':
    case 'inactive':
      return uiClasses.badge.secondary
    case 'warning':
    case 'low stock':
    case 'overdue':
      return uiClasses.badge.warning
    case 'error':
    case 'failed':
    case 'suspended':
    case 'defaulted':
      return uiClasses.badge.danger
    case 'info':
    case 'processing':
      return uiClasses.badge.info
    default:
      return uiClasses.badge.secondary
  }
}

// Responsive helpers
export const responsive = {
  mobile: "max-sm:",
  tablet: "sm:",
  desktop: "md:",
  large: "lg:",
  extraLarge: "xl:"
}

// Animation classes
export const animations = {
  fadeIn: "animate-in fade-in duration-200",
  slideIn: "animate-in slide-in-from-bottom duration-200",
  scaleIn: "animate-in zoom-in-95 duration-200",
  pulse: "animate-pulse",
  bounce: "animate-bounce"
}

// Focus and interaction states
export const focusStates = {
  ring: "focus:ring-2 focus:ring-blue-500 focus:ring-offset-2",
  outline: "focus:outline-none",
  visible: "focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
}