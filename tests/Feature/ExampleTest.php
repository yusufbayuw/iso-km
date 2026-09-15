<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_the_filament_portal(): void
    {
        $this->get('/')->assertRedirect('/portal');
    }
}
