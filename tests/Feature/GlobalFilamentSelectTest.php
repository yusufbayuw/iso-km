<?php

namespace Tests\Feature;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Tests\TestCase;

class GlobalFilamentSelectTest extends TestCase
{
    public function test_form_and_table_filter_selects_are_searchable_and_preloaded_by_default(): void
    {
        $select = Select::make('status')->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ]);

        $this->assertTrue($select->isSearchable());
        $this->assertTrue($select->isPreloaded());
        $this->assertFalse($select->isNative());
        $this->assertSame(50, $select->getOptionsLimit());

        $filter = SelectFilter::make('status')->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ]);

        $this->assertTrue($filter->getSearchable());
        $this->assertTrue($filter->isPreloaded());
        $this->assertFalse($filter->isNative());
        $this->assertSame(50, $filter->getOptionsLimit());

        $filterField = $filter->getFormField();
        $this->assertTrue($filterField->isSearchable());
        $this->assertTrue($filterField->isPreloaded());
    }
}
