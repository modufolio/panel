<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Table;

use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Table\BulkAction;
use Modufolio\Panel\Table\RowAction;
use PHPUnit\Framework\TestCase;

/**
 * An action may ask before it runs — a confirmation, or a small form whose
 * values travel with it. The fields are declared like a form's and reach
 * the client in the same shape, so the dialog is the blueprint form.
 */
final class ActionFormTest extends TestCase
{
    public function testARowActionWithAFormCarriesItsFieldsMethodAndLabel(): void
    {
        $action = RowAction::form('reject', '/panel/issues/{id}/reject')
            ->label('Reject')
            ->fields([
                Field::make('reason')->textarea()->label('Reason')->required(),
                Field::make('notify')->toggle()->label('Notify the reporter'),
            ])
            ->method('patch')
            ->submitLabel('Reject issue')
            ->toArray();

        self::assertSame('form', $action['behaviour']);
        self::assertSame('/panel/issues/{id}/reject', $action['urlTemplate']);
        self::assertSame(['reason', 'notify'], array_column($action['fields'], 'key'));
        self::assertSame('textarea', $action['fields'][0]['type']);
        self::assertSame('Reason', $action['fields'][0]['label']);
        self::assertSame('toggle', $action['fields'][1]['type']);
        self::assertSame('patch', $action['method']);
        self::assertSame('Reject issue', $action['submitLabel']);
    }

    public function testAConfirmationOnlyActionCarriesNoFieldsAndNoMethod(): void
    {
        $action = RowAction::form('archive', '/panel/issues/{id}/archive')->confirm('Archive this issue?')->toArray();

        self::assertArrayNotHasKey('fields', $action);
        self::assertArrayNotHasKey('method', $action, 'POST is the default and goes unsaid.');
        self::assertTrue($action['confirm']);
        self::assertSame('Archive this issue?', $action['confirmMessage']);
    }

    public function testABulkActionMayAskTheSame(): void
    {
        $action = BulkAction::make('assign')
            ->url('/panel/issues/assign')
            ->fields([Field::make('assignee')->text()->label('Assignee')])
            ->toArray();

        self::assertSame(['assignee'], array_column($action['fields'], 'key'));
        self::assertSame('post', $action['method']);
    }

    public function testTheMethodMustBeAWrite(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RowAction::form('x', '/x')->method('get');
    }
}
