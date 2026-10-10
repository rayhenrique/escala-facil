<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Escala Fácil');
        $response->assertSeeInOrder(['O seu plantão,', 'sob o seu controle.']);
    }

    public function test_landing_page_route_renders_successfully(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Recursos do aplicativo');
        $response->assertSee('Solicitar e acompanhar');
        $response->assertSee('Consultar a agenda');
        $response->assertSee('Conferir e registrar');
    }
}
