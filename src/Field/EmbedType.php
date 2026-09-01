<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * An external embed, stored as its URL. The editor renders a URL input with
 * a preview link; when the application wires an oEmbed endpoint into
 * `props.endpoint`, the component resolves title/thumbnail through it —
 * resolution is deliberately server-side, so the panel's CSP never needs to
 * allow third-party origins.
 */
final class EmbedType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'embed';
    }

    public static function defaults(): array
    {
        return [
            'rules' => ['url' => true],
        ];
    }
}
