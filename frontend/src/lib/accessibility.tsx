// Accessibility utility functions and hooks
import { useState, useEffect, useCallback } from 'react'

// ARIA roles and attributes constants
export const ARIA_ROLES = {
  BUTTON: 'button',
  LINK: 'link',
  MENU: 'menu',
  MENUITEM: 'menuitem',
  COMBOBOX: 'combobox',
  LISTBOX: 'listbox',
  OPTION: 'option',
  ALERT: 'alert',
  ALERTDIALOG: 'alertdialog',
  DIALOG: 'dialog',
  TOOLTIP: 'tooltip',
  TAB: 'tab',
  TABLIST: 'tablist',
  TABPANEL: 'tabpanel',
  MAIN: 'main',
  NAVIGATION: 'navigation',
  BANNER: 'banner',
  CONTENTINFO: 'contentinfo',
  COMPLEMENTARY: 'complementary',
  SEARCH: 'search'
} as const

export const ARIA_STATES = {
  EXPANDED: 'aria-expanded',
  SELECTED: 'aria-selected',
  CHECKED: 'aria-checked',
  DISABLED: 'aria-disabled',
  HIDDEN: 'aria-hidden',
  INVALID: 'aria-invalid',
  REQUIRED: 'aria-required',
  BUSY: 'aria-busy',
  LIVE: 'aria-live',
  ATOMIC: 'aria-atomic',
  RELEVANT: 'aria-relevant'
} as const

// Keyboard navigation utilities
export const KEYS = {
  ENTER: 'Enter',
  SPACE: ' ',
  ESCAPE: 'Escape',
  TAB: 'Tab',
  ARROW_UP: 'ArrowUp',
  ARROW_DOWN: 'ArrowDown',
  ARROW_LEFT: 'ArrowLeft',
  ARROW_RIGHT: 'ArrowRight',
  HOME: 'Home',
  END: 'End',
  PAGE_UP: 'PageUp',
  PAGE_DOWN: 'PageDown'
} as const

// Focus management hook
export function useFocusManagement() {
  const [focusedElement, setFocusedElement] = useState<HTMLElement | null>(null)

  const focusFirst = useCallback((container: HTMLElement) => {
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    if (focusableElements.length > 0) {
      ;(focusableElements[0] as HTMLElement).focus()
    }
  }, [])

  const focusLast = useCallback((container: HTMLElement) => {
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    if (focusableElements.length > 0) {
      ;(focusableElements[focusableElements.length - 1] as HTMLElement).focus()
    }
  }, [])

  const trapFocus = useCallback((container: HTMLElement) => {
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    
    if (focusableElements.length === 0) return

    const firstElement = focusableElements[0] as HTMLElement
    const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement

    const handleTab = (e: KeyboardEvent) => {
      if (e.key === KEYS.TAB) {
        if (e.shiftKey) {
          if (document.activeElement === firstElement) {
            e.preventDefault()
            lastElement.focus()
          }
        } else {
          if (document.activeElement === lastElement) {
            e.preventDefault()
            firstElement.focus()
          }
        }
      }
    }

    container.addEventListener('keydown', handleTab)
    firstElement.focus()

    return () => {
      container.removeEventListener('keydown', handleTab)
    }
  }, [])

  return {
    focusedElement,
    setFocusedElement,
    focusFirst,
    focusLast,
    trapFocus
  }
}

// Screen reader announcement hook
export function useScreenReader() {
  const [announcement, setAnnouncement] = useState<string>('')

  const announce = useCallback((message: string, priority: 'polite' | 'assertive' = 'polite') => {
    setAnnouncement(message)
    // Clear announcement after it's been read
    setTimeout(() => setAnnouncement(''), 1000)
  }, [])

  return {
    announcement,
    announce
  }
}

// Skip to content link component
interface SkipLinkProps {
  targetId: string
  children?: React.ReactNode
  className?: string
}

export function SkipLink({ targetId, children, className }: SkipLinkProps) {
  return React.createElement('a', {
    href: `#${targetId}`,
    className,
    style: {
      position: 'absolute',
      top: '0',
      left: '-9999px',
      zIndex: '9999',
      background: '#000',
      color: '#fff',
      padding: '1rem',
      textDecoration: 'none'
    },
    onFocus: (e: React.FocusEvent<HTMLAnchorElement>) => {
      e.currentTarget.style.left = '0'
    },
    onBlur: (e: React.FocusEvent<HTMLAnchorElement>) => {
      e.currentTarget.style.left = '-9999px'
    }
  }, children || 'Skip to main content')
}

// Focus visible indicator
export function FocusVisible() {
  useEffect(() => {
    const style = document.createElement('style')
    style.textContent = `
      *:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
      }
      
      button:focus-visible,
      [role="button"]:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
      }
      
      a:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
      }
      
      input:focus-visible,
      select:focus-visible,
      textarea:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
      }
    `
    document.head.appendChild(style)
    
    return () => {
      document.head.removeChild(style)
    }
  }, [])

  return null
}

// High contrast mode detection
export function useHighContrastMode() {
  const [isHighContrast, setIsHighContrast] = useState(false)

  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-contrast: high)')
    
    const handleChange = (e: MediaQueryListEvent) => {
      setIsHighContrast(e.matches)
    }
    
    setIsHighContrast(mediaQuery.matches)
    mediaQuery.addEventListener('change', handleChange)
    
    return () => {
      mediaQuery.removeEventListener('change', handleChange)
    }
  }, [])

  return isHighContrast
}

// Reduced motion detection
export function useReducedMotion() {
  const [isReducedMotion, setIsReducedMotion] = useState(false)

  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    
    const handleChange = (e: MediaQueryListEvent) => {
      setIsReducedMotion(e.matches)
    }
    
    setIsReducedMotion(mediaQuery.matches)
    mediaQuery.addEventListener('change', handleChange)
    
    return () => {
      mediaQuery.removeEventListener('change', handleChange)
    }
  }, [])

  return isReducedMotion
}

// Accessibility helper functions
export const a11yHelpers = {
  // Generate unique IDs for ARIA attributes
  generateId: (prefix: string = 'a11y') => {
    return `${prefix}-${Math.random().toString(36).substr(2, 9)}`
  },
  
  // Check if element is focusable
  isFocusable: (element: HTMLElement): boolean => {
    const focusableSelectors = [
      'button',
      'input',
      'select',
      'textarea',
      'a[href]',
      '[tabindex]:not([tabindex="-1"])',
      '[contenteditable="true"]'
    ]
    
    return focusableSelectors.some(selector => element.matches(selector))
  },
  
  // Get focusable children
  getFocusableChildren: (container: HTMLElement): HTMLElement[] => {
    const focusableSelectors = [
      'button',
      'input',
      'select',
      'textarea',
      'a[href]',
      '[tabindex]:not([tabindex="-1"])',
      '[contenteditable="true"]'
    ].join(', ')
    
    return Array.from(container.querySelectorAll(focusableSelectors)) as HTMLElement[]
  },
  
  // Set ARIA attributes safely
  setAriaAttribute: (element: HTMLElement, attribute: string, value: string | boolean) => {
    if (typeof value === 'boolean') {
      element.setAttribute(attribute, value.toString())
    } else {
      element.setAttribute(attribute, value)
    }
  }
}

export type {
  SkipLinkProps
}