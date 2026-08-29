# Relation fields: lookups and repeaters

Two field types talk to the server while the form is open: `belongs-to`
(pick one related record) and `repeater` (edit a list of child rows). Both
work from props the backend sends, so a page declares nothing.

## `belongs-to` — a lookup

Small relations ship their whole option list and behave like a select.
Larger ones ship no options and a `searchUrl` instead:

```js
{
  type: 'belongs-to',
  key: 'author_id',
  label: 'Author',
  props: {
    searchUrl: '/panel/posts/relations/author_id',
    valueKey: 'value',
    labelKey: 'label',
    clearable: true,      // offer a way back to "no selection"
    allowCreate: true,    // offer "Create …" for an unmatched name
  },
}
```

### Endpoint contract

One path answers three things:

| Request | Returns |
|---|---|
| `GET ?q=term` | `{ data: [{ value, label }], meta: { total, limit, truncated } }` |
| `GET ?values=id[,id]` | `{ data: [...] }` — labels for identifiers the field already holds |
| `POST { label }` | `{ data: { value, label } }` — create from a typed name |

`?values=` exists because a searchable field arrives with an identifier but
no label: the list it came from was never sent, so the control asks what to
call its current value before rendering.

`meta.truncated` is rendered as a visible notice. A silently capped list
reads as "that is everything", which is how someone concludes a record does
not exist.

### Behaviour worth knowing

**The input always names what is actually held.** Typing filters the list,
but typed-and-never-chosen text is not a selection: dismissing the dropdown
(Escape, blur, clicking away) reverts the input to the held value's label.
Without that, the display and the value disagree and the next submit proves
it. Emptying the input still means deselect.

**`allowCreate` is an offer, not a permission.** The row is hidden when the
exact name is already listed, and the `POST` must re-check server-side that
the target really can be created from a label alone — a forged offer buys
nothing. A refusal is shown verbatim under the field. Without a `searchUrl`,
the field emits a `create` event instead for the page to handle.

## `repeater` — child rows

```js
{
  type: 'repeater',
  key: 'lines',
  label: 'Lines',
  fields: [                        // the child's own fields
    { key: 'item_id', type: 'belongs-to', label: 'Item', width: '1/2' },
    { key: 'quantity', type: 'text', label: 'Quantity', width: '1/2',
      props: { type: 'number', step: '0.001' } },
  ],
}
```

Rows are plain objects keyed by the sub-field keys, plus an `id` for rows
that already exist. **A row with no `id` is new** — that is how the server
tells create from update, and how it knows which rows were removed.

Sub-fields render through the same registry as top-level fields, so a
`belongs-to` inside a row behaves exactly like one outside it (including
its own `searchUrl`, usually addressed with a dotted path such as
`lines.item_id`).

## Row-addressed errors

Server validation for a child row comes back under a dotted key:

```json
{ "errors": { "lines.1.quantity": "Another row already uses this." } }
```

`BlueprintForm` fans those out to the container field as `nestedErrors`,
keyed relative to it (`1.quantity`), and the repeater pins each message to
its row. A container field type that wants this only has to accept a
`nestedErrors` prop — see [custom-fields.md](custom-fields.md).

Two lifetime rules, both deliberate:

- **Editing the container clears its row messages.** Any edit may have been
  the fix, and a stale message pinned to a reordered list points at the
  wrong row.
- **A fresh batch of server errors resets what counts as "already edited".**
  Server errors only show on untouched fields — but every field the user
  filled in before submitting is touched, so without this reset the server's
  answer *about that edit* would be suppressed on arrival. After the message
  appears, editing again hides it: that edit is a response to it.

## Field widths

Sub-fields honour the same `width` values as top-level fields (`1/4`,
`1/3`, `1/2`, `2/3`, `3/4`, `full`) against a twelve-column grid, so a
two-field row reads as halves and a three-field row as thirds. Past three,
columns get too narrow for a label and each field takes its own row.
