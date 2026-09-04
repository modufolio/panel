# Blueprint forms and fields

A resource's form is a **declaration**, not markup: a list of field definitions
that the package serialises to JSON and `@modufolio/panel` renders. The same
declaration drives the control, its layout, its validation and — where the
application wires them — its defaults and its per-field permissions.

Two ways to write one:

```php
// Name the columns and let Doctrine's metadata fill in the rest.
public function formFieldKeys(): array
{
    return [
        'title'       => ['width' => '1/2'],
        'director_id' => ['width' => '1/2'],
        'cast'        => [],
    ];
}

// Or declare every field yourself.
public function formFields(): array
{
    return (new BlueprintBuilder())
        ->add('title', TextType::class, ['rules' => ['required' => true, 'max' => 160]])
        ->add('starts_on', DateType::class, ['default' => '@today'])
        ->fields();
}
```

Returning non-null from either is also the **opt-in for the generated write
routes** — a resource that declares no form is index-and-show only. When both
are implemented, `formFields()` wins.

---

## Which one to use

`formFieldKeys()` is the right default. `FormFieldGuesser` reads the entity's
mapping and derives what the schema already knows: the type from the column
type, `max` from its length, `required` from its nullability, a BelongsTo
select from a to-one association, a multiselect from a many-to-many, a repeater
over a one-to-many's own fields. Overrides always win, so you state only what
the schema cannot know — layout, choices, bounds.

Its one hard limit: **every key must resolve to a mapped property.** A key that
names nothing throws (naming the class and the key), and there is no way to
force a type. So a field with no column behind it — a `computed` value, a `set`
of sub-fields stored as one JSON object, an `embed` — has to come from
`formFields()`. That is also the only path that can declare per-field access.

---

## Field types

| Type | Component | Notes |
|---|---|---|
| `TextType` | `text` | |
| `TextareaType` | `textarea` | |
| `EmailType` / `UrlType` | `text` | plus the matching validation rule |
| `NumberType` / `DecimalType` | `text` | numeric input; `integer` rule on the former |
| `SelectType` | `select` | needs `options` |
| `TemplateSelectType` | `select` | choices are the application's templates |
| `ToggleType` | `toggle` | |
| `DateType` | `date` | |
| `ColorType` | `color` | |
| `TagsType` | `tags` | |
| `ImageType` | `image` | media-library reference |
| `BelongsToType` | `belongs-to` | to-one; carries a `relation` |
| `ManyToManyType` | `multiselect` | |
| `HasManyType` / `StructureType` | `repeater` | rows sharing one sub-schema |
| `SectionsType` | `sections` | |
| `BuilderType` | `builder` | block editor |
| `SetType` | `set` | several sub-fields, **one** stored object |
| `EmbedType` | `embed` | external URL; oEmbed resolution stays server-side |
| `HiddenType` | `hidden` | stored, never rendered |
| `DataType` | `data` | shown, never edited — the importer's parking spot |
| `ComputedType` | `data` | server-computed; requires `accessor` |

`SetType` is the single-row sibling of `StructureType`: same `fields` option,
but the value is one object rather than a list of rows.

`ComputedType` refuses to be declared without an `accessor`, at build time. The
lesson is Keystone's, whose virtual field throws for the same reason: never
guess how to display a computed value.

---

## Field options

Every option below is accepted by `BlueprintBuilder::add()` and by a
`formFieldKeys()` override. Anything else is rejected where it is written.

### Presentation

| Option | Effect |
|---|---|
| `label` | Defaults to a humanised key |
| `help` | Description under the control |
| `width` | `1/4`, `1/3`, `1/2`, `2/3`, `3/4`, `full` — a twelve-column grid |
| `placeholder` | |
| `prefix` / `postfix` | Inline affordances around the control (a unit, a URL stem) |
| `group` | The editor tab this field renders under |

### Behaviour

| Option | Effect |
|---|---|
| `required`, `disabled`, `readonly`, `autofocus` | Control state |
| `default` | Value for a blank submission — see [Defaults](#defaults) |
| `options` | Choice list for select-shaped types |
| `props` | Passed through to the Vue component untouched |
| `rules` | Validation — see below |
| `fields` | Sub-field declarations, for `set` and repeater types |
| `relation` | A `RelationOptions` for a relation field |
| `accessor` | A `ComputedType`'s source method |
| `when` / `requiredWhen` | Conditions — see below |
| `access` | Per-field read/write — see below |

### Validation rules

`required`, `min`, `max`, `email`, `url`, `integer`, `pattern`. `min`/`max`
measure a string's length or a number's value. The first failing rule is the
message shown.

`rules.messages` overrides the wording per rule:

```php
'year' => ['rules' => [
    'min' => 1888,
    'max' => 2100,
    'messages' => ['min' => 'Cinema starts at 1888 (Roundhay Garden Scene).'],
]],
```

A declaration that can never pass — `min` above `max`, a `pattern` that is not
a valid regex — throws where the blueprint is written rather than on the first
submission.

---

## Conditions

`when` hides a field; `requiredWhen` makes its emptiness conditional. Both take
the same shape, and both are evaluated **twice**: by the panel against the live
form, and again on the server by `Blueprint\Condition`. The client copy saves a
round trip; the server one decides.

```php
['status', 'published']              // implicit ==
['status', '!=', 'draft']
['cover', 'not_empty']
['all' => [ … ]]  ['any' => [ … ]]  ['not' => … ]
```

```php
'synopsis'  => ['requiredWhen' => ['genre', 'Drama']],
'studio_id' => ['requiredWhen' => ['not' => ['genre', 'Comedy']]],
'tags'      => ['when' => ['genre', 'not_empty']],
```

A field whose `when` does not hold is not merely unrendered: its submitted
value is dropped by `FieldValidator::stripHidden()`, so a hidden input cannot
smuggle data past the form. That call is the **application's** — see
[Wiring the guards](#wiring-the-guards).

---

## Defaults

`Defaults::resolve()` fills blank values from the declaration, resolving two
sentinels as it goes:

| Default | Becomes |
|---|---|
| `'@now'` | `Y-m-d H:i:s` at resolution time |
| `'@today'` | `Y-m-d` at resolution time |

Use a sentinel rather than a literal for anything time-shaped. A literal is
evaluated when the blueprint is *built*, which under a worker runtime happens
once per process — freezing the worker's boot time into every record it writes.

---

## Per-field access

```php
->add('published', ToggleType::class, [
    'access' => ['write' => static fn (?object $user): bool => $user?->isSuperAdmin()],
])
->add('internal_notes', TextareaType::class, [
    'access' => ['read' => static fn (?object $user): bool => $user?->isAdmin()],
])
```

Two verbs, and hidden ≠ forbidden is enforced on both sides of the wire:

- **read denied** — the field is removed from the serialised definitions
  entirely. Never shipped, not merely not rendered.
- **write denied** — the field renders read-only *and* its submitted value is
  stripped. Disabling the input is presentation; the strip is the guard.

**Scope: `access` governs the form.** Both verbs act where the form is built
and where its submission is handled — the paths listed under [Wiring the
guards](#wiring-the-guards). They are *not* an application-wide rule about a
field's value, and no other read path consults them:

| Path | Honours `access.read`? |
|---|---|
| Form definitions (`FieldAccess::resolve`) | Yes |
| Form submission (`FieldAccess::stripDenied`) | Yes — the `write` half |
| Presenters (`present()` / `presentOne()`) | **No** |
| Exports | **No** — see [panel-resources.md](panel-resources.md#exports) |
| Your own JSON responses | **No** |

So `access.read` keeps a field out of the *editor*; it does not keep the value
out of every payload. If a value must not reach a role at all, it also has to
be absent from what your presenter emits to that role — declaring `access` and
assuming the rest is the same mistake as declaring it and never wiring it.

Callables receive `($user, $record)`, either possibly null — a create form has
no record yet. They never travel to the client, which is why they are held
apart from the field definitions:

| Path | Fields | Access |
|---|---|---|
| `formFields()` | the return value | `formAccess()` |
| `formFieldKeys()` | `FormFieldGuesser::guess()` | `guessForm()->access` |

`guessForm()` returns a `Blueprint\FormDefinition` carrying both halves, since
`guess()` can only return what is serialisable. A hand-written form has no
builder for the caller to ask, so it hands the map over through `formAccess()`
— build both from the same builder and the two cannot disagree:

```php
public function formFields(): array  { return $this->blueprint()->fields(); }
public function formAccess(): array  { return $this->blueprint()->access(); }
```

This is a different question from `readonlyFields($record, $user)`, which
answers "which fields are frozen on *this* record" and drops them from the
submission wholesale. Use that for row-dependent freezing, `access` for
role-dependent visibility.

---

## Wiring the guards

**The package declares these guards; it does not call them.** It owns no
request cycle, so `stripHidden()`, `stripDenied()` and `Defaults::resolve()`
are static helpers an application's controller invokes. Declaring `when` or
`access` in a blueprint and never calling them yields a form that *looks*
guarded and is not.

The reference wiring (appkit-playground's `ResourceController`), in this order,
because each step depends on the last:

```php
$values = FieldAccess::stripDenied($access, $values, $user, $entity);
$values = Defaults::resolve($fields, $values);
$values = FieldValidator::stripHidden($fields, $values);

$errors = FieldValidator::validate($fields, $values);
```

1. **Denied first** — the form disabled those inputs; a request is not a form.
2. **Then defaults** — the server fills what the user left blank.
3. **Then hidden** — after defaults, so a field a `when` hides cannot arrive by
   way of its own default either.

On the way out, `FieldAccess::resolve($fields, $access, $user, $record)`
decides which definitions are serialised at all, and a `ComputedType`'s
`accessor` is invoked on the record to give the field its value.

---

## See also

- [adding-a-resource.md](adding-a-resource.md) — the whole recipe, of which the
  form is step seven
- [panel-resources.md](panel-resources.md) — what else a resource declares
- [table-schema.md](table-schema.md) — the listing side, including the filter
  operators the same field types declare
- `ui/docs/custom-fields.md` — writing a field *component* for a type of your own
