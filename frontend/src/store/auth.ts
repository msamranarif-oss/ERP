import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { authApi } from '@/lib/api'

export interface User {
  id: number
  name: string
  email: string
  phone?: string
  avatar?: string
  tenant: {
    id: number
    name: string
    slug: string
    logo?: string
    settings?: Record<string, unknown>
  }
  branch?: {
    id: number
    name: string
    code?: string
  }
  roles: string[]
  permissions: string[]
  settings?: Record<string, unknown>
}

interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  error: string | null
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  fetchUser: () => Promise<void>
  setUser: (user: User) => void
  clearError: () => void
  hasRole: (role: string) => boolean
  hasPermission: (permission: string) => boolean
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,
      error: null,

      login: async (email: string, password: string) => {
        set({ isLoading: true, error: null })
        try {
          const response = await authApi.login({ email, password })
          const { user, token } = response.data.data
          localStorage.setItem('auth_token', token)
          set({
            user,
            token,
            isAuthenticated: true,
            isLoading: false,
          })
        } catch (error: unknown) {
          const err = error as { response?: { data?: { message?: string } } }
          const errorMessage = err.response?.data?.message || 'Login failed'
          set({
            error: errorMessage,
            isLoading: false,
          })
          throw new Error(errorMessage)
        }
      },

      logout: async () => {
        set({ isLoading: true })
        try {
          await authApi.logout()
        } catch (error) {
          console.error('Logout API error:', error)
          // Continue with logout even if API call fails
        } finally {
          localStorage.removeItem('auth_token')
          set({
            user: null,
            token: null,
            isAuthenticated: false,
            isLoading: false,
            error: null,
          })
        }
      },

      fetchUser: async () => {
        set({ isLoading: true, error: null })
        try {
          const response = await authApi.getUser()
          set({
            user: response.data.data,
            isAuthenticated: true,
            isLoading: false,
          })
        } catch (error) {
          const err = error as { response?: { status?: number } }
          if (err.response?.status === 401) {
            localStorage.removeItem('auth_token')
            set({
              user: null,
              token: null,
              isAuthenticated: false,
            })
          }
          set({
            isLoading: false,
            error: 'Failed to fetch user data',
          })
        }
      },

      setUser: (user: User) => set({ user }),

      clearError: () => set({ error: null }),

      hasRole: (role: string) => {
        const { user } = get()
        return user?.roles?.includes(role) || false
      },

      hasPermission: (permission: string) => {
        const { user } = get()
        return user?.permissions?.includes(permission) || false
      },
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({ token: state.token }),
    }
  )
)