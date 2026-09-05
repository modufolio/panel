import { expect, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { BlueprintForm } from '../../src/index'

type Props = InstanceType<typeof BlueprintForm>['$props']

/**
 * Mount a BlueprintForm and wait until its fields are actually in the DOM.
 *
 * Field components resolve through `defineAsyncComponent`, whose dynamic
 * import goes through the module runner's transform — real I/O, not a
 * microtask. A fixed number of `flushPromises()` calls settles it on a warm
 * module cache and not on a cold one, which is exactly the difference between
 * a local run and the first spec of a CI run. So wait for the rendered
 * fields instead: `rendered` is how many roots the form's grid should hold
 * once every visible field, separators included, has mounted.
 */
export async function mountBlueprintForm(props: Props, rendered: number): Promise<VueWrapper> {
  const wrapper = mount(BlueprintForm, { props })

  await vi.waitFor(() => {
    expect(wrapper.find('.ui-field-grid').element.children).toHaveLength(rendered)
  }, { timeout: 4000 })
  await flushPromises()

  return wrapper
}
