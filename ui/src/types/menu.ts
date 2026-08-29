/** User-menu item rendered by TopNavigation's dropdown. */
export interface MenuItem {
  label: string
  href?: string
  icon?: string
  divider?: boolean
  method?: 'get' | 'post' | 'put' | 'patch' | 'delete'
  action?: () => void
  badge?: string | number
  badgeColor?: 'primary' | 'success' | 'danger' | 'warning'
}
