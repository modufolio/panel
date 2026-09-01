/**
 * @modufolio/panel — schema-driven admin panel components for
 * Inertia.js + Vue 3.
 */
export const VERSION = '0.2.0'

// Plugin / configuration
export { createPanel, type CreatePanelOptions } from './plugin'

// Utils
export { apiFetch, ApiError, type ApiFetchOptions } from './Utils/apiFetch'
export * from './Utils/optimistic'
export * from './Utils/writeGate'
export * from './Utils/moduleSingleton'
export * from './Utils/reconcile'
export * from './Utils/tagsChanged'
export { escapeHtml, sanitizeUrl, normalizeUrl, panelUrl, setPanelBaseUrl, getPanelBaseUrl } from './Utils/url'
export { getCsrfToken, setCsrfToken } from './Utils/csrf'
export * from './Utils/dates'

// Overlay + keyboard primitives
export { useId } from './Primitives/useId'
export { useBodyScrollLock } from './Primitives/useBodyScrollLock'
export {
  useDismissableLayer,
  type DismissReason,
  type DismissableLayerOptions,
} from './Primitives/useDismissableLayer'
export { hideOthers, isHiddenBehindOverlay } from './Primitives/hideOthers'
export {
  useAnchoredPosition,
  type AnchoredPositionOptions,
  type AnchoredPosition,
} from './Primitives/useAnchoredPosition'
export {
  useArrowNavigation,
  resolveNavigationIndex,
  type ArrowNavigationOptions,
  type NavigationOrientation,
} from './Primitives/useArrowNavigation'
export { useTypeahead, isTypeaheadKey } from './Primitives/useTypeahead'
export { setTeleportTarget, getTeleportTarget } from './Primitives/teleportTarget'

// Generic composables
export * from './Composables/usePagination'
export * from './Composables/usePendingKeys'
export * from './Composables/useQuery'
export * from './Composables/useAsyncData'
export * from './Composables/useFieldSaver'
export * from './Composables/useFocusTrap'
export * from './Composables/useDragReorder'
export * from './Composables/useLocalStoragePersistence'
export * from './Composables/useUnsavedChangesWarning'
export * from './Composables/useNestedDrawerForm'
export { useDrawerDirtyGuard } from './Components/Drawer/useDrawerDirtyGuard'
export * from './Composables/useDeleteConfirmation'
export * from './Composables/useReconciled'

// Core Primitives
export { default as Button } from './Components/Core/Button.vue'
export { default as Tag } from './Components/Core/Tag.vue'
export { default as Badge } from './Components/Core/Badge.vue'
export { default as Empty } from './Components/Core/Empty.vue'
export { default as ErrorBoundary } from './Components/Core/ErrorBoundary.vue'
export { default as Icon } from './Components/Core/Icon.vue'
export { registerIcons } from './Components/Core/iconRegistry'
export { default as Label } from './Components/Core/Label.vue'
export { default as Modal } from './Components/Core/Modal.vue'
export { default as Dropdown } from './Components/Core/Dropdown.vue'
export { default as Pagination } from './Components/Core/Pagination.vue'
export { default as LoadingButton } from './Components/Core/LoadingButton.vue'
export { default as FlashMessages } from './Components/Core/FlashMessages.vue'
export { default as TextInput } from './Components/Core/TextInput.vue'
export { default as SelectInput } from './Components/Core/SelectInput.vue'

// Table Components
export { default as Table } from './Components/Table/Table.vue'
export { default as SchemaTable } from './Components/Table/SchemaTable.vue'
export {
  resolveRecordUrl,
  isEmptyValue,
  getPath,
  cellClasses,
  truncate,
  formatValue,
  emptyFilterValue,
  filterDefaults,
  visibleColumnDefaults,
  visibleRowActions,
  type TableSchema,
  type SchemaColumn,
  type SchemaColumnType,
  type SchemaFilter,
  type SchemaFilterType,
  type SchemaConstraint,
  type SchemaConstraintOperator,
  type SchemaRowAction,
  type SchemaBulkAction,
  type QueryCondition,
} from './Components/Table/tableSchema'
export { default as TablePagination } from './Components/Table/TablePagination.vue'
export { default as ColumnToggle } from './Components/Table/ColumnToggle.vue'
export {
  registerColumnType,
  resolveColumnComponent,
  registeredColumnTypes,
} from './Components/Columns/columnRegistry'
export { default as ExportButton } from './Components/Table/ExportButton.vue'
export { useTableExport } from './Components/Table/useTableExport'

// Column Components
export { default as TextColumn } from './Components/Columns/TextColumn.vue'
export { default as BadgeColumn } from './Components/Columns/BadgeColumn.vue'
export { default as ImageColumn } from './Components/Columns/ImageColumn.vue'
export { default as DateColumn } from './Components/Columns/DateColumn.vue'
export { default as BooleanColumn } from './Components/Columns/BooleanColumn.vue'
export { default as IconColumn } from './Components/Columns/IconColumn.vue'
export { default as SelectColumn } from './Components/Columns/SelectColumn.vue'
export { default as ToggleColumn } from './Components/Columns/ToggleColumn.vue'
export { default as ColorColumn } from './Components/Columns/ColorColumn.vue'
export { default as CopyButton } from './Components/Columns/CopyButton.vue'
export { default as TreeColumn } from './Components/Columns/TreeColumn.vue'
export { default as TextInputColumn } from './Components/Columns/TextInputColumn.vue'

// Filter Components
export { default as FilterPopover } from './Components/Filters/FilterPopover.vue'
export { default as FilterIndicators, type FilterIndicator } from './Components/Filters/FilterIndicators.vue'
export { default as FacetedFilter } from './Components/Filters/FacetedFilter.vue'
export { default as SelectFilter } from './Components/Filters/SelectFilter.vue'
export { default as TernaryFilter } from './Components/Filters/TernaryFilter.vue'
export { default as DateRangeFilter } from './Components/Filters/DateRangeFilter.vue'
export { default as MultiSelectFilter } from './Components/Filters/MultiSelectFilter.vue'
export { default as NumberFilter } from './Components/Filters/NumberFilter.vue'
export { default as QueryBuilder } from './Components/Filters/QueryBuilder.vue'

// Action Components
export { default as Action } from './Components/Actions/Action.vue'
export { default as ActionGroup } from './Components/Actions/ActionGroup.vue'
export { default as ActionGroupItem } from './Components/Actions/ActionGroupItem.vue'

// Widget Components
export { default as StatsWidget } from './Components/Widgets/StatsWidget.vue'
export { default as StatCard } from './Components/Widgets/StatCard.vue'

// Field Grid + Blueprint
export { default as FieldGrid } from './Components/Fields/FieldGrid.vue'
export { fieldsFromSpec, initialValues } from './Components/Fields/fieldsFromSpec'
export { default as BlueprintForm } from './Components/Fields/BlueprintForm.vue'
export { useFieldWidth, fieldWidthProp } from './Components/Fields/useFieldWidth'

// The field frame and its parts. A consumer writing a field type of its own
// composes these rather than reproducing the label/help/error markup, which is
// how the built-in fields drifted into three different spacings for the same
// gap.
export { default as FieldPrimitive } from './Components/Fields/FieldPrimitive.vue'
export { default as FieldLabel } from './Components/Fields/FieldLabel.vue'
export { default as FieldDescription } from './Components/Fields/FieldDescription.vue'
export { default as FieldMessage } from './Components/Fields/FieldMessage.vue'
export {
  useBlueprint,
  defineBlueprint,
  evaluateCondition,
  registerFieldType,
  resolveFieldComponent,
  type FieldDef,
  type FieldType,
  type BuiltinFieldType,
  type OptionItem,
  type Condition,
  type ConditionTuple,
  type ConditionOperator,
} from './Components/Fields/useBlueprint'
export {
  required,
  min,
  max,
  email,
  url,
  pattern,
  integer,
  same,
  firstError,
  rulesFromSpec,
  type ValidationRule,
} from './Components/Fields/validation'

// Field Components (app-specific types — writer, rich-text, block-editor —
// are registered by applications via registerFieldType())
export { default as TextField } from './Components/Fields/TextField.vue'
export { default as TextareaField } from './Components/Fields/TextareaField.vue'
export { default as SelectField } from './Components/Fields/SelectField.vue'
export { default as CheckboxField } from './Components/Fields/CheckboxField.vue'
export { default as ToggleField } from './Components/Fields/ToggleField.vue'
export { default as RangeField } from './Components/Fields/RangeField.vue'
export { default as DatePickerField } from './Components/Fields/DatePickerField.vue'
export { default as DateTimePickerField } from './Components/Fields/DateTimePickerField.vue'
export { default as TimePickerField } from './Components/Fields/TimePickerField.vue'
export { default as TagsField } from './Components/Fields/TagsField.vue'
export { default as FileUploadField } from './Components/Fields/FileUploadField.vue'
export { default as MultiSelectField } from './Components/Fields/MultiSelectField.vue'
export { default as ColorPickerField } from './Components/Fields/ColorPickerField.vue'
export { default as DateRangePickerField } from './Components/Fields/DateRangePickerField.vue'
export { default as BelongsToSelect } from './Components/Fields/BelongsToSelect.vue'
export { default as ToggleButtonsField } from './Components/Fields/ToggleButtonsField.vue'
export { default as RepeaterField } from './Components/Fields/RepeaterField.vue'

// Section Components
export { default as Section } from './Components/Sections/Section.vue'
export { default as FormSection } from './Components/Sections/FormSection.vue'
export { default as FieldsSection } from './Components/Sections/FieldsSection.vue'
export { default as InfoSection } from './Components/Sections/InfoSection.vue'
export { default as FilesSection } from './Components/Sections/FilesSection.vue'

// Dialog Components
export { default as Dialog } from './Components/Dialogs/Dialog.vue'
export { default as ConfirmDialog } from './Components/Dialogs/ConfirmDialog.vue'
export { default as DeleteConfirmDialog } from './Components/Dialogs/DeleteConfirmDialog.vue'

// Drawer Components (Hierarchical overlay navigation)
export { default as Drawer } from './Components/Drawer/Drawer.vue'
export { default as DrawerStack } from './Components/Drawer/DrawerStack.vue'
export { default as DrawerLink } from './Components/Drawer/DrawerLink.vue'
export { visitDrawer, drawerVisitOptions, withDrawerParams, type VisitDrawerOptions } from './Components/Drawer/visitDrawer'
export { default as DrawerTabs } from './Components/Drawer/DrawerTabs.vue'
export type { DrawerTab } from './Components/Drawer/drawerTabs'
export { default as DrawerFieldGrid } from './Components/Drawer/DrawerFieldGrid.vue'
export type { DrawerField } from './Components/Drawer/drawerFieldGrid'
export { default as DrawerRelationList } from './Components/Drawer/DrawerRelationList.vue'
export { default as NestedDrawerForm } from './Components/Drawer/NestedDrawerForm.vue'
export { useDrawerStack, type StackItem, type StackItemTab } from './Components/Drawer/useDrawerStack'
export { useFocusedStackRow } from './Components/Drawer/useFocusedStackRow'
export { useIsDrawer, useDrawerStackContext } from './Components/Drawer/useIsDrawer'
export { useDrawerPage } from './Components/Drawer/useDrawerPage'

// Notification Components
export { default as Toast } from './Components/Notifications/Toast.vue'
export { useToast, useToastStore } from './Components/Notifications/useToast'

// Relation Components
export { default as RelationManager } from './Components/Relations/RelationManager.vue'

// Wizard Components
export { default as Wizard } from './Components/Wizard/Wizard.vue'

// Resource composables
export { useInlineEdit } from './Components/Composables/useInlineEdit'
export { useRelationship } from './Components/Composables/useRelationship'

// Layout & app shell
export { default as AppLayout } from './Components/Layout/AppLayout.vue'
export { default as Sidebar } from './Components/Layout/Sidebar.vue'
export { default as TopNavigation } from './Components/Layout/TopNavigation.vue'
export { default as PageHeader } from './Components/Layout/PageHeader.vue'
export { default as Breadcrumbs } from './Components/Layout/Breadcrumbs.vue'
export { default as Container } from './Components/Layout/Container.vue'
export { default as Grid } from './Components/Layout/Grid.vue'
export { default as Stack } from './Components/Layout/Stack.vue'
export { default as Cluster } from './Components/Layout/Cluster.vue'
export { SidebarCollapsedKey } from './injectionKeys'
export type { MenuItem } from './types/menu'

// Auth screens (Inertia pages; assign a persistent layout where you register them)
export { default as Login } from './Components/Auth/Login.vue'
export { default as ForgotPassword } from './Components/Auth/ForgotPassword.vue'
export { default as ResetPassword } from './Components/Auth/ResetPassword.vue'
export { default as TwoFactor } from './Components/Auth/TwoFactor.vue'
export { default as TwoFactorVerify } from './Components/Auth/TwoFactorVerify.vue'

// Media picker (endpoints configurable via createPanel({ media }))
export { default as MediaPickerDialog } from './Components/Media/MediaPickerDialog.vue'
export type { MediaEndpoints } from './Components/Media/mediaEndpoints'

// Rich text / block editor
export { default as RichTextEditorField } from './Components/Fields/RichTextEditorField.vue'
export { default as ProseMirrorBuilderField } from './Components/Fields/ProseMirrorBuilderField.vue'
// Document round-trip, for reading or producing stored block content outside
// the editor (server-side rendering, migrations, custom toolbars). The plugin
// internals (drag handle, link sanitiser) stay private.
export { schema, HEADING_LEVELS, CODE_LANGUAGES, IMAGE_WIDTHS } from './Builder/schema'
export { emptyDoc, parseStoredValue, serializeDoc } from './Builder/document'
