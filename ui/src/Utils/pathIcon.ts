import { h, type FunctionalComponent } from 'vue'

/**
 * A stroke icon from one or more SVG path definitions, as a component.
 *
 * Built with render functions on purpose. The package used to declare these
 * as `{ template: '<svg…' }` objects, which need Vue's runtime compiler; a
 * host bundling the runtime-only build (the default of every bundler alias)
 * rendered them as nothing, silently — a boolean cell showed a coloured disc
 * with no check inside it.
 */
export function pathIcon(paths: string | string[], options: { strokeWidth?: number; class?: string } = {}): FunctionalComponent {
  const definitions = Array.isArray(paths) ? paths : [paths]

  const icon: FunctionalComponent = (_props, { attrs }) =>
    h(
      'svg',
      {
        xmlns: 'http://www.w3.org/2000/svg',
        fill: 'none',
        viewBox: '0 0 24 24',
        'stroke-width': options.strokeWidth ?? 1.5,
        stroke: 'currentColor',
        'aria-hidden': 'true',
        class: options.class,
        ...attrs,
      },
      definitions.map((d) => h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d })),
    )

  icon.inheritAttrs = false

  return icon
}
