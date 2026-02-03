import { Outlet } from 'react-router-dom'

export function InventoryLayout() {
  return (
    <div className="space-y-6">
      <Outlet />
    </div>
  )
}
