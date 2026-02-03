// Professional ERP Theme Configuration
export const theme = {
  // Professional color palette - Standardized for consistency
  colors: {
    // Primary Brand Colors
    primary: {
      50: '#eff6ff',
      100: '#dbeafe',
      200: '#bfdbfe',
      300: '#93c5fd',
      400: '#60a5fa',
      500: '#3b82f6',
      600: '#2563eb',
      700: '#1d4ed8',
      800: '#1e40af',
      900: '#1e3a8a',
      950: '#172554'
    },
    
    // Secondary/Neutral Colors
    secondary: {
      50: '#f8fafc',
      100: '#f1f5f9',
      200: '#e2e8f0',
      300: '#cbd5e1',
      400: '#94a3b8',
      500: '#64748b',
      600: '#475569',
      700: '#334155',
      800: '#1e293b',
      900: '#0f172a',
      950: '#020617'
    },
    
    // Status Colors - Standardized usage
    success: {
      50: '#f0fdf4',
      100: '#dcfce7',
      200: '#bbf7d0',
      300: '#86efac',
      400: '#4ade80',
      500: '#22c55e',
      600: '#16a34a',
      700: '#15803d',
      800: '#166534',
      900: '#14532d'
    },
    
    warning: {
      50: '#fffbeb',
      100: '#fef3c7',
      200: '#fde68a',
      300: '#fcd34d',
      400: '#fbbf24',
      500: '#f59e0b',
      600: '#d97706',
      700: '#b45309',
      800: '#92400e',
      900: '#78350f'
    },
    
    danger: {
      50: '#fef2f2',
      100: '#fee2e2',
      200: '#fecaca',
      300: '#fca5a5',
      400: '#f87171',
      500: '#ef4444',
      600: '#dc2626',
      700: '#b91c1c',
      800: '#991b1b',
      900: '#7f1d1d'
    },
    
    // Information/Action Colors
    info: {
      50: '#eff6ff',
      100: '#dbeafe',
      200: '#bfdbfe',
      300: '#93c5fd',
      400: '#60a5fa',
      500: '#3b82f6',
      600: '#2563eb',
      700: '#1d4ed8',
      800: '#1e40af',
      900: '#1e3a8a'
    },
    
    // Background/Interface Colors
    background: {
      default: '#ffffff',
      surface: '#f8fafc',
      elevated: '#ffffff',
      overlay: 'rgba(15, 23, 42, 0.5)'
    },
    
    // Text Colors - WCAG compliant
    text: {
      primary: '#0f172a',
      secondary: '#64748b',
      tertiary: '#94a3b8',
      disabled: '#cbd5e1',
      inverse: '#ffffff'
    },
    
    // Border Colors
    border: {
      default: '#e2e8f0',
      strong: '#cbd5e1',
      subtle: '#f1f5f9'
    }
  },
  
  // Typography
  typography: {
    fontFamily: {
      sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      mono: ['JetBrains Mono', 'monospace']
    },
    fontSize: {
      xs: '0.75rem',
      sm: '0.875rem',
      base: '1rem',
      lg: '1.125rem',
      xl: '1.25rem',
      '2xl': '1.5rem',
      '3xl': '1.875rem',
      '4xl': '2.25rem'
    },
    fontWeight: {
      thin: '100',
      extralight: '200',
      light: '300',
      normal: '400',
      medium: '500',
      semibold: '600',
      bold: '700',
      extrabold: '800',
      black: '900'
    }
  },
  
  // Spacing and sizing
  spacing: {
    xs: '0.5rem',
    sm: '1rem',
    md: '1.5rem',
    lg: '2rem',
    xl: '3rem',
    '2xl': '4rem'
  },
  
  // Shadows
  shadows: {
    sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
    md: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
    lg: '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
    xl: '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
    '2xl': '0 25px 50px -12px rgb(0 0 0 / 0.25)'
  },
  
  // Border radius
  borderRadius: {
    sm: '0.125rem',
    md: '0.25rem',
    lg: '0.5rem',
    xl: '0.75rem',
    '2xl': '1rem',
    full: '9999px'
  },
  
  // Transitions
  transitions: {
    default: 'all 0.2s ease-in-out',
    fast: 'all 0.1s ease-in-out',
    slow: 'all 0.3s ease-in-out'
  }
}

// Color utility system for consistent usage
export const colorUtils = {
  // Primary action colors
  primary: {
    main: theme.colors.primary[600],
    hover: theme.colors.primary[700],
    active: theme.colors.primary[800],
    light: theme.colors.primary[100],
    background: theme.colors.primary[50]
  },
  
  // Status colors
  status: {
    success: {
      main: theme.colors.success[600],
      light: theme.colors.success[100],
      text: theme.colors.success[800]
    },
    warning: {
      main: theme.colors.warning[600],
      light: theme.colors.warning[100],
      text: theme.colors.warning[800]
    },
    danger: {
      main: theme.colors.danger[600],
      light: theme.colors.danger[100],
      text: theme.colors.danger[800]
    },
    info: {
      main: theme.colors.info[600],
      light: theme.colors.info[100],
      text: theme.colors.info[800]
    }
  },
  
  // Text colors
  text: {
    primary: theme.colors.text.primary,
    secondary: theme.colors.text.secondary,
    tertiary: theme.colors.text.tertiary,
    disabled: theme.colors.text.disabled,
    inverse: theme.colors.text.inverse
  },
  
  // Background colors
  background: {
    default: theme.colors.background.default,
    surface: theme.colors.background.surface,
    elevated: theme.colors.background.elevated,
    overlay: theme.colors.background.overlay
  },
  
  // Border colors
  border: {
    default: theme.colors.border.default,
    strong: theme.colors.border.strong,
    subtle: theme.colors.border.subtle
  }
}

// Utility functions for consistent styling
export const uiUtils = {
  // Button styling
  button: {
    primary: 'bg-blue-600 hover:bg-blue-700 text-white font-semibold h-10 px-4 gap-2 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0',
    secondary: 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium h-10 px-4 gap-2 transition-all duration-200 hover:shadow-sm',
    outline: 'border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold h-10 px-4 gap-2 transition-all duration-200 hover:shadow-sm',
    destructive: 'bg-red-600 hover:bg-red-700 text-white font-semibold h-10 px-4 gap-2 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0',
    ghost: 'hover:bg-slate-100 text-slate-600 font-medium h-10 px-3 transition-all duration-200 hover:text-slate-900'
  },
  
  // Card styling
  card: {
    surface: 'bg-white border border-slate-200 rounded-lg shadow-sm',
    elevated: 'bg-white border border-slate-200 rounded-lg shadow-md',
    bordered: 'bg-white border-2 border-slate-300 rounded-lg'
  },
  
  // Badge styling
  badge: {
    success: 'flex items-center gap-1.5 text-green-600 bg-green-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-green-100',
    warning: 'flex items-center gap-1.5 text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-orange-100',
    danger: 'flex items-center gap-1.5 text-red-600 bg-red-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-red-100',
    info: 'flex items-center gap-1.5 text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-blue-100',
    secondary: 'flex items-center gap-1.5 text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-slate-100'
  }
}

// CSS custom properties for use in Tailwind
export const cssVariables = {
  '--erp-primary': theme.colors.primary[600],
  '--erp-primary-hover': theme.colors.primary[700],
  '--erp-secondary': theme.colors.secondary[600],
  '--erp-success': theme.colors.success[500],
  '--erp-warning': theme.colors.warning[500],
  '--erp-danger': theme.colors.danger[500],
  '--erp-background': theme.colors.secondary[50],
  '--erp-surface': theme.colors.secondary[100],
  '--erp-text-primary': theme.colors.secondary[900],
  '--erp-text-secondary': theme.colors.secondary[600],
  '--erp-border': theme.colors.secondary[300],
  '--erp-radius': theme.borderRadius.lg,
  '--erp-shadow': theme.shadows.md,
  '--erp-transition': theme.transitions.default
}