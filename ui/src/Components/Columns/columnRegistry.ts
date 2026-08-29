import type { Component } from 'vue'

/**
 * The column-type → component registry: the table's extension seam, mirroring
 * {@see registerFieldType} for blueprint fields.
 *
 * Columns render inside a v-for over every visible cell, so this resolves
 * synchronously — an async loader would leave a hole in the grid on first
 * paint. Register the component itself, or a `defineAsyncComponent` if the
 * application wants the loading behaviour.
 *
 *   registerColumnType('sparkline', SparklineColumn)
 *
 * A registered type is consulted before the built-ins, so an application can
 * also replace one: registering 'badge' overrides the shipped badge column
 * everywhere the schema asks for it.
 */
const columnRegistry: Record<string, Component> = {}

export function registerColumnType(type: string, component: Component): void {
  columnRegistry[type] = component
}

/** The component for a type, or undefined when the built-in should handle it. */
export function resolveColumnComponent(type: string): Component | undefined {
  return columnRegistry[type]
}

/** Registered custom types, for diagnostics and tests. */
export function registeredColumnTypes(): string[] {
  return Object.keys(columnRegistry)
}
