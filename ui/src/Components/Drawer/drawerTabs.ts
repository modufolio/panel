/**
 * A section in a {@link DrawerTabs} bar.
 *
 * Declared here rather than inside the SFC so consumers can import the type
 * (`<script setup>` cannot export type declarations).
 */
export interface DrawerTab {
  /** Stable identifier — also the panel slot name. */
  key: string
  label: string
  /** Registered icon name, rendered before the label. */
  icon?: string
  /** Count or short string shown in a pill after the label. Hidden when null/undefined/''. */
  badge?: number | string | null
  disabled?: boolean
}
