/**
 * Shapes the generated form routes send.
 *
 * Lives beside ResourceForm rather than inside it so the create and edit
 * pages can be typed by the same declaration the form validates against —
 * a second copy is a second chance for the two to drift.
 */

/** Self-description from FormPresenter::props() — see its `resource` key. */
export interface ResourceFormMeta {
  key: string
  baseUrl: string
  /** Where this form submits and deletes, from the router; see FormPresenter. */
  urls?: { index?: string | null; store?: string | null; update?: string | null; destroy?: string | null }
  drawerType: string
  label: string
  canDelete?: boolean
}
