import { defineComponent, h, type PropType } from 'vue'
import TextColumn from '../Columns/TextColumn.vue'
import TextInputColumn from '../Columns/TextInputColumn.vue'
import BadgeColumn from '../Columns/BadgeColumn.vue'
import DateColumn from '../Columns/DateColumn.vue'
import BooleanColumn from '../Columns/BooleanColumn.vue'
import ImageColumn from '../Columns/ImageColumn.vue'
import IconColumn from '../Columns/IconColumn.vue'
import ColorColumn from '../Columns/ColorColumn.vue'
import SelectColumn from '../Columns/SelectColumn.vue'
import ToggleColumn from '../Columns/ToggleColumn.vue'
import CopyButton from '../Columns/CopyButton.vue'
import { resolveColumnComponent } from '../Columns/columnRegistry'
import {
  isEmptyValue,
  cellClasses,
  truncate,
  formatValue,
  type SchemaColumn,
} from './tableSchema'

/** The component each read-only column type renders through. */
const componentForType: Record<string, any> = {
  text: TextColumn,
  badge: BadgeColumn,
  date: DateColumn,
  boolean: BooleanColumn,
  image: ImageColumn,
  icon: IconColumn,
  color: ColorColumn,
}

type CellHandler = ((record: any, column: string, value: any) => unknown) | undefined

/** A column flag resolved against the row it is rendered for. */
function flag(record: Record<string, any>, key: string | undefined): boolean {
  return key ? Boolean(record[key]) : false
}

/** Resolve an option's label for a value rendered read-only. */
function labelForOption(column: SchemaColumn, value: unknown): string {
  return column.options?.find((option) => option.value === String(value))?.label ?? String(value)
}

/** Props every in-place editor takes; they all save through the same handler. */
function editorProps(
  column: SchemaColumn,
  record: Record<string, any>,
  handler: CellHandler,
): Record<string, any> {
  return {
    record,
    column: column.key,
    disabled: flag(record, column.disabledWhen),
    onUpdate: handler ?? null,
  }
}

function textInput(
  column: SchemaColumn,
  record: Record<string, any>,
  value: unknown,
  handler: CellHandler,
) {
  // SelectColumn and friends declare both an `onUpdate` *prop* and an `update`
  // *emit*, so Vue's generated types read `onUpdate` as the emit listener and
  // reject the callback. The runtime contract is the prop; the casts below are
  // only to get past that ambiguity.
  return h(TextInputColumn, {
    ...editorProps(column, record, handler),
    value: (value ?? '') as string,
    label: column.label,
    placeholder: column.placeholder ?? '',
  } as any)
}

/**
 * Renders one cell from its column definition.
 *
 * A render function rather than markup: the prop set differs per column type,
 * and a chain of v-ifs in the template obscured which props each type takes.
 */
export default defineComponent({
  name: 'SchemaCell',
  props: {
    column: { type: Object as PropType<SchemaColumn>, required: true },
    record: { type: Object as PropType<Record<string, any>>, required: true },
    value: { type: null, default: undefined },
    handler: { type: Function as PropType<CellHandler>, default: undefined },
  },
  setup(cellProps) {
    return () => {
      const { column, record, value, handler } = cellProps
      const empty = isEmptyValue(value)

      // An application-registered type owns its cell completely — including
      // how it renders an empty value — so it is consulted before the built-in
      // handling below. Registering a built-in type's name replaces it.
      const registered = resolveColumnComponent(column.type)
      if (registered) {
        return h(registered, {
          value,
          record,
          column,
          label: formatValue(column, value),
          onUpdate: handler ?? null,
        } as any)
      }

      // An editable text cell renders its input even when empty — that is the
      // state someone opens the cell to fill in.
      if (empty && column.type === 'text' && column.editable) {
        return textInput(column, record, '', handler)
      }

      // A select keeps rendering when empty so the control stays usable, and
      // an image renders its own placeholder — a dash where a thumbnail is
      // expected breaks the row's rhythm.
      if (empty && column.type !== 'boolean' && column.type !== 'select' && column.type !== 'image') {
        return h(TextColumn, { label: column.placeholder ?? '—' })
      }

      const component = componentForType[column.type] ?? TextColumn

      switch (column.type) {
        case 'select': {
          if (flag(record, column.readOnlyWhen) || !column.editable) {
            return h(BadgeColumn, {
              label: labelForOption(column, value),
              color: column.colors?.[String(value)] ?? 'gray',
            })
          }

          return h(SelectColumn, {
            ...editorProps(column, record, handler),
            value: value as string,
            options: column.options ?? [],
          } as any)
        }

        case 'date':
          return h(component, {
            value,
            format: column.format,
            relative: column.relative ?? false,
          })

        case 'badge':
          return h(component, {
            label: String(value),
            color: column.colors?.[String(value)] ?? 'gray',
          })

        case 'boolean':
          // An editable boolean is an in-place toggle, saved through the same
          // handler an editable select uses; otherwise a read-only tick/cross.
          if (column.editable) {
            return h(ToggleColumn, {
              ...editorProps(column, record, handler),
              value: value as boolean,
            } as any)
          }

          return h(component, { value })

        // The value carries the content to render; each component names that
        // prop differently, so map it rather than passing a generic `value`
        // the component ignores (which rendered nothing).
        case 'image':
          return h(component, {
            // Empty stays empty: `String(null)` would ask the browser to load
            // "null" and render a broken image where the placeholder belongs.
            src: empty ? '' : String(value),
            alt: column.descriptionKey ? (record[column.descriptionKey] ?? '') : '',
            // Undefined rather than a default, so the component's own defaults
            // stand for a column that declares neither.
            size: column.size ?? undefined,
            rounded: column.rounded ?? undefined,
          })

        case 'icon':
          return h(component, {
            icon: value,
            label: column.descriptionKey ? (record[column.descriptionKey] ?? '') : '',
            color: column.color ?? 'gray',
          })

        case 'color':
          return h(component, { color: String(value), copyable: column.copyable ?? false })

        case 'money':
        case 'numeric':
          return h(TextColumn, {
            label: formatValue(column, value),
            labelClass: cellClasses({ ...column, align: column.align ?? 'right' }),
          })

        case 'text':
        default: {
          // Inline text editing, saved through the same handler an editable
          // select or toggle uses.
          if (column.type === 'text' && column.editable && !flag(record, column.readOnlyWhen)) {
            return textInput(column, record, value, handler)
          }

          const text = formatValue(column, value)

          const cell = h(component, {
            label: truncate(text, column.limit),
            title: column.limit && text.length > column.limit ? text : undefined,
            labelClass: cellClasses(column),
            icon: column.icon,
            description: column.descriptionKey ? (record[column.descriptionKey] ?? '') : '',
          })

          // A copyable column pairs the value with a click-to-copy affordance —
          // the natural use is copying an email or phone straight from the row.
          if (column.copyable) {
            return h('div', { class: 'inline-flex items-center gap-1.5' }, [
              cell,
              h(CopyButton, { value: text }),
            ])
          }

          return cell
        }
      }
    }
  },
})
