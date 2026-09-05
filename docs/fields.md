# Blueprint forms and fields

A resource's form is a **declaration**, not markup: a list of field definitions
that the package serialises to JSON and `@modufolio/panel` renders. The same
declaration drives the control, its layout, its validation and — where the
application wires them — its defaults and its per-field permissions.

One list, in display order:

```php
public function form(): Form
{
    return Form::make()->fields([
        'title'       => ['width' => '1/2'],
        Field::make('director_id')->width('1/2'),
        Separator::Line,
        'cast',
        'starts_on'   => ['default' => '@today'],
        'days_until'  => ['type' => ComputedType::class, 'accessor' => 'daysUntil'],
    ]);
}
```

Returning non-null is also the **opt-in for the generated write routes** — a
resource that declares no form is index-and-show only.

A bare key takes whatever the resource's `fields()` declares for it first —
label, type, options — and the form entry adds what the form alone needs.

---

## What an entry says

Most entries name a mapped field and state only what the mapping cannot know.
`FormFieldGuesser` reads the entity's mapping and derives the rest: the type
from the column type, `max` from its length, `required` from its nullability,
a BelongsTo select from a to-one association, a multiselect from a
many-to-many, a repeater over a one-to-many's own fields. Options always win,
so you state only layout, choices, bounds — whatever the schema cannot know.

An entry with a `type` is **declared outright**. The type is taken as written,
ahead of the column and of a `#[FormType]` attribute on the property, and the
key need not be mapped at all — a `SetType` over one stored object, an
`EmbedType`, a `ComputedType` reading an accessor, a `HiddenType` import
reference. What the mapping does know still applies to a mapped key: a
`TextareaType` pinned over a string column keeps the column's `max`.

Everything goes through one `BlueprintBuilder`, so option validation is the
same whether a field was guessed, pinned or invented, and an option the type
does not accept is refused where it is written.

### Separators

A long form reads better as runs of fields than as one grid. A `Separator`
entry between two keys draws a rule across the full row, or leaves the same
gap with nothing drawn in it:

```php
use Modufolio\Panel\Blueprint\Separator;

return [
    'first_name' => ['width' => '1/2'],
    'last_name'  => ['width' => '1/2'],
    Separator::Line,
    'email'      => ['width' => '1/2'],
    'phone'      => ['width' => '1/2'],
    Separator::Space,
    'note'       => [],
];
```

It is a plain list entry, not an option on a field, because a break is a thing
in the sequence rather than a property of whichever field happens to follow it.
A separator is never validated and never written.

A column mapped with `enumType` is a choice among the enum's cases: the
guesser makes it a select, labelled by the enum's own `getLabel()` where it
declares one, and the write path hands the setter the case rather than its
value. A resource's `options` override still wins.

A `string` column is only ever a text input from the mapping's point of view,
and some strings are emails, URLs or colours. That is a fact about the
property, true for every form over the entity, so it is declared beside the
column with `#[FormType]` rather than repeated in each resource:

```php
use Modufolio\Panel\Blueprint\FormType;
use Modufolio\Panel\Field\EmailType;

#[ORM\Column(length: 200, nullable: true)]
#[FormType(EmailType::class)]
private ?string $email = null;
```

The attribute names the type and nothing else — layout, access and conditions
describe one form, not the property, and stay in the resource. Precedence is
the attribute over the column mapping (including the `options` shorthand that
would otherwise make a select), and a resource's overrides over both. Doctrine
never sees it: the guesser reads it off the metadata's reflection.

A lookup needs a label for each option, and the guesser tries `name`, `title`
and `label` on the target. An entity with none of them, or whose `label` column
means something else, marks the column it is referred to by:

```php
use Modufolio\Panel\Blueprint\LabelField;

#[ORM\Column(length: 150)]
#[LabelField]
private ?string $addressLine1 = null;
```

A guessed repeater lays its row out by count — two fields share halves, three
share thirds, more take a row each — with a `text` column always taking the
whole row. To adjust one column without writing the row out, pass `fields` as
a map of sub-field key to overrides; a list of full specs still replaces the
row entirely:

```php
'connections' => [
    'label'  => 'Connections',
    'fields' => ['connection_type' => ['width' => '1/3']],
],
```

Every relation pointing at that entity, from any resource, uses it. It has to be
a mapped column, because the options endpoint orders and searches by it in DQL.

Its one hard limit: **every key must resolve to a mapped property.** A key that
names nothing throws (naming the class and the key), and there is no way to
force a type. So a field with no column behind it — a `computed` value, a `set`
of sub-fields stored as one JSON object, an `embed` — has to come from
`form()`.

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
| `DateType` | `date` | a day, `YYYY-MM-DD` |
| `DateTimeType` | `datetime` | a moment, `YYYY-MM-DDTHH:mm`; what a datetime column guesses as |
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
| `SeparatorType` | `separator` | a rule or a gap across the row; never a value — see [Separators](#separators) |
| `DataType` | `data` | shown, never edited — the importer's parking spot |
| `ComputedType` | `data` | server-computed; requires `accessor` |

`image`, `builder` and `sections` are **host-provided** components: the
client package does not ship them, and an application that emits those types
registers a component at boot (`createPanel({ fields: { image: … } })`).
`Field\FieldComponents::missing($fields, $registered)` reports any component a
form needs that neither the package ships nor the host registered, so this can
be a lint rather than a red block in a form.

`SetType` is the single-row sibling of `StructureType`: same `fields` option,
but the value is one object rather than a list of rows.

`ComputedType` refuses to be declared without an `accessor`, at build time. The
lesson is Keystone's, whose virtual field throws for the same reason: never
guess how to display a computed value.

---

## Field options

Every option below is accepted on a `form()` entry — as a `key => [options]`
array or a `Field` builder call of the same name — (and by
`BlueprintBuilder::add()`, which the guesser calls for each). Anything else is
rejected where it is written.

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

Who may read or write a field is not part of the form: it is a rule on the
resource's [`Permissions`](panel-resources.md#permissions) class, asked per
field with the viewer and, where there is one, the record.

```php
final class ScreeningPermissions extends Permissions
{
    public function readable(string $field, ?object $user, ?object $record = null): bool
    {
        return $field !== 'internal_notes' || $user?->isAdmin() === true;
    }

    public function writable(string $field, ?object $user, ?object $record = null): bool
    {
        return match ($field) {
            'published' => $user?->isSuperAdmin() === true,
            'price'     => $record === null || !$record->isClosed(),
            default     => true,
        };
    }
}
```

Two verbs, and hidden ≠ forbidden is enforced on both sides of the wire:

- **not readable** — the field is removed from the serialised definitions
  entirely. Never shipped, not merely not rendered.
- **not writable** — the field renders disabled *and* leaves the submission:
  its value is stripped and none of its rules run, so a required field frozen
  on a closed record still lets the rest of the record save. Disabling the
  input is presentation; the strip is the guard.

One method answers both questions the package used to ask apart — "frozen on
*this* record" and "off limits to *this* role" — because a request answers
neither by itself. Null record means the type, or a create form.

**Scope: the rules govern the form.** Both verbs act where the form is built
and where its submission is handled — the paths listed under [Wiring the
guards](#wiring-the-guards). They are *not* an application-wide rule about a
field's value, and no other read path consults them:

| Path | Honours `readable()`? |
|---|---|
| Form definitions (`FieldAccess::resolve`) | Yes |
| Form submission (`FieldAccess::stripDenied`) | Yes — and `writable()` |
| Presenters (`present()` / `presentOne()`) | **No** |
| Exports | **No** — see [panel-resources.md](panel-resources.md#exports) |
| Your own JSON responses | **No** |

So `readable()` keeps a field out of the *editor*; it does not keep the value
out of every payload. If a value must not reach a role at all, it also has to
be absent from what your presenter emits to that role.

---

## Wiring the guards

**The package's form services call these guards.** `SubmissionHandler::handle()`
runs the write side and `FormPresenter` the read side, so a host whose
controller hands the request body to `SubmissionHandler` and serialises forms
through `FormPresenter` has nothing further to wire — the reference
application's `ResourceController` does exactly that and calls none of the
helpers directly.

The helpers stay public for a write path the package does not own: a bespoke
controller that persists a form without `SubmissionHandler`. Such a path has
to reproduce the order below, because declaring `when` in a blueprint or a
`writable()` rule on the permissions and never applying them yields a form
that *looks* guarded and is not. `SubmissionHandler` runs them in this order,
because each step depends on the last:

```php
$values = FieldAccess::stripDenied($fields, $resource->permissions(), $values, $user, $entity);
$values = Defaults::resolve($fields, $values);
$values = FieldValidator::stripHidden($fields, $values);

$errors = FieldValidator::validate($fields, $values);
```

1. **Denied first** — the form disabled those inputs; a request is not a form.
2. **Then defaults** — the server fills what the user left blank.
3. **Then hidden** — after defaults, so a field a `when` hides cannot arrive by
   way of its own default either.

On the way out, `FieldAccess::resolve($fields, $resource->permissions(), $user, $record)`
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
