<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\BlueprintBuilder;
use Modufolio\Panel\Field\DataType;
use Modufolio\Panel\Field\EmbedType;
use Modufolio\Panel\Field\HiddenType;
use Modufolio\Panel\Field\SetType;
use Modufolio\Panel\Field\TemplateSelectType;
use Modufolio\Panel\Field\TextType;
use PHPUnit\Framework\TestCase;

/**
 * The newer catalog entries and the editor-help metadata, through the
 * builder: the serialized shape is the contract the client renders.
 */
final class FieldCatalogTest extends TestCase
{
    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function build(string $key, string $type, array $options = []): array
    {
        $builder = new BlueprintBuilder();
        $builder->add($key, $type, $options);

        return $builder->fields()[0];
    }

    public function testHiddenAndDataSerialize(): void
    {
        $this->assertSame('hidden', $this->build('legacy_id', HiddenType::class)['type']);

        $data = $this->build('import_source', DataType::class);
        $this->assertSame('data', $data['type']);
        $this->assertTrue($data['props']['readonly'], 'Data fields are read-only by construction.');
    }

    public function testEmbedCarriesItsUrlRule(): void
    {
        $field = $this->build('video', EmbedType::class);

        $this->assertSame('embed', $field['type']);
        $this->assertSame(['url' => true], $field['rules']);
    }

    public function testSetCarriesSubFieldsLikeTheRepeater(): void
    {
        $builder = new BlueprintBuilder();
        $sub = new BlueprintBuilder();
        $sub->add('meta_title', TextType::class);
        $sub->add('meta_description', TextType::class);

        $builder->add('seo', SetType::class, ['fields' => $sub->fields()]);
        $field = $builder->fields()[0];

        $this->assertSame('set', $field['type']);
        $this->assertSame(['meta_title', 'meta_description'], array_column($field['fields'], 'key'));
    }

    public function testTemplateSelectListsMatchingFilesAsOptions(): void
    {
        $dir = sys_get_temp_dir().'/panel-tpl-'.getmypid();
        @mkdir($dir);
        file_put_contents($dir.'/portfolio.php', '');
        file_put_contents($dir.'/fine-art.php', '');
        file_put_contents($dir.'/_partial.php', '');

        try {
            $options = TemplateSelectType::optionsFromDirectory($dir, '/^(?!_)/');

            $this->assertSame(['fine-art' => 'Fine Art', 'portfolio' => 'Portfolio'], $options);

            $field = $this->build('template', TemplateSelectType::class, ['options' => $options]);
            $this->assertSame('select', $field['type'], 'Renders as an ordinary select.');
        } finally {
            array_map('unlink', glob($dir.'/*.php') ?: []);
            rmdir($dir);
        }
    }

    public function testPrefixPostfixAndGroupFlowToTheClient(): void
    {
        $field = $this->build('price', TextType::class, [
            'prefix' => '€',
            'postfix' => 'per print',
            'group' => 'Pricing',
        ]);

        $this->assertSame('€', $field['props']['prefix'], 'Prefix reaches the component as a prop.');
        $this->assertSame('per print', $field['props']['postfix']);
        $this->assertSame('Pricing', $field['group'], 'The editor tab is a top-level fact about the field.');
    }

    public function testAMisspelledMetadataOptionFailsAtBuildTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BlueprintBuilder())->add('price', TextType::class, ['prefixx' => '€']);
    }
}
