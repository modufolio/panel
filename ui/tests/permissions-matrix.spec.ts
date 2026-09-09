import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PermissionsMatrix from '../src/Components/Permissions/PermissionsMatrix.vue'

/**
 * The keys here are `PermissionReport::toArray()`'s, not an approximation of
 * them: the page this component came from read `canView` / `scopeQuery` /
 * `frozen`, none of which the report emits, so every one of those markers
 * silently never fired. These tests exist to keep the two shapes married.
 */
const report = {
  roles: ['ROLE_ADMIN', 'ROLE_USER'],
  resources: {
    screenings: {
      key: 'screenings',
      class: 'App\\Panel\\ScreeningResource',
      permissions: 'App\\Panel\\ScreeningPermissions',
      prefix: '/panel',
      routes: ['screenings', 'screenings_export', 'screenings_edit', 'screenings_update'],
      overrides: {
        view: false,
        create: false,
        edit: true,
        delete: false,
        scope: true,
        readable: true,
        writable: true,
        move: false,
      },
      roles: {
        ROLE_ADMIN: {
          routes: { screenings: true, screenings_export: true, screenings_edit: true, screenings_update: true },
          can: { view: true, create: true, edit: true, delete: true },
          fields: { readable: ['title', 'published'], readDenied: [], writeDenied: ['published'] },
        },
        ROLE_USER: {
          // The edit page admits this role but the write behind it does not:
          // half an operation, which the matrix must not round to "no".
          routes: { screenings: true, screenings_export: true, screenings_edit: true, screenings_update: false },
          can: { view: true, create: false, edit: false, delete: false },
          fields: { readable: ['title'], readDenied: ['internal_notes'], writeDenied: ['published'] },
        },
      },
    },
  },
  notes: [
    {
      kind: 'route_admits_hook_denies',
      resource: 'screenings',
      role: 'ROLE_USER',
      message: 'The edit route admits ROLE_USER, but edit() refuses it.',
    },
  ],
}

describe('PermissionsMatrix', () => {
  function render(props: Record<string, unknown> = {}) {
    return mount(PermissionsMatrix, { props: { report, ...props } })
  }

  it('heads each resource with the class and the Permissions answering for it', () => {
    const text = render().text()

    expect(text).toContain('screenings')
    expect(text).toContain('App\\Panel\\ScreeningResource')
    expect(text).toContain('App\\Panel\\ScreeningPermissions')
  })

  it('stars the hooks the resource actually overrides', () => {
    const text = render().text()

    // `edit` is overridden, so its verdicts are type-level only; the others
    // are the base class answering, and carry no star.
    expect(text).toContain('edit() *')
    expect(text).toContain('view()')
    expect(text).not.toContain('view() *')
  })

  it('flags a resource that scopes the rows a viewer can see at all', () => {
    expect(render().text()).toContain('rows scoped')
  })

  it('reports a partly-admitted operation as partial, and an absent one as a dash', () => {
    const text = render().text()

    // ROLE_USER reaches the edit route but not the update behind it, and no
    // delete route is generated for this resource at all.
    expect(text).toContain('partial')
    expect(text).toContain('—')
  })

  it('names the fields each role may not read or write', () => {
    const text = render().text()

    expect(text).toContain('internal_notes')
    expect(text).toContain('published')
  })

  it('lists the divergences the inspector found', () => {
    const text = render().text()

    expect(text).toContain('route admits hook denies')
    expect(text).toContain('The edit route admits ROLE_USER, but edit() refuses it.')
  })

  it('says so plainly when every layer agrees', () => {
    const text = render({ report: { ...report, notes: [] } }).text()

    expect(text).toContain('None: every layer agrees for every role.')
  })

  it('explains itself by default, and stays quiet when told to', () => {
    expect(render().text()).toContain('What each role may do on every panel resource')
    expect(render({ description: null }).text()).not.toContain('What each role may do')
  })
})
