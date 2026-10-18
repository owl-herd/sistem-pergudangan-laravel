<?php
namespace Tests\Feature;
use Tests\TestCase;
class ExampleTest extends TestCase { public function test_application_is_available(): void { $this->get('/')->assertOk(); } }
