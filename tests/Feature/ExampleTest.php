<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_guests_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect();
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
