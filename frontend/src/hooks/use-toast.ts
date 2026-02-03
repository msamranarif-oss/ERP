import { toast as sonnerToast } from "sonner"

type ToastProps = {
    title?: string
    description?: string
    variant?: "default" | "destructive" | "success"
    [key: string]: any
}

export function useToast() {
    return {
        toast: ({ title, description, variant, ...props }: ToastProps) => {
            if (variant === "destructive") {
                sonnerToast.error(title, { description, ...props })
            } else if (variant === "success") {
                sonnerToast.success(title, { description, ...props })
            } else {
                sonnerToast(title || "", { description, ...props })
            }
        },
        dismiss: (id?: string | number) => sonnerToast.dismiss(id),
    }
}
