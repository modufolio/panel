<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Form\Form;
use Modufolio\Panel\Resource\Drawer;
use Modufolio\Panel\Resource\DrawerTab;

/**
 * A movie whose drawer lists its cast and offers to add to it — the shape that
 * carries an add endpoint, here and on a frame stacked over another resource.
 */
class CastDrawerMovieResource extends DerivedMovieResource
{
    public function form(): Form
    {
        return Form::make()->fields(['title', 'synopsis', 'released_on', 'cast']);
    }

    public function drawer(): Drawer
    {
        return Drawer::make()->tabs([
            DrawerTab::record('details')->sections(
                DrawerTab::relation('cast')->primary('character')->addable(),
            ),
        ]);
    }
}
