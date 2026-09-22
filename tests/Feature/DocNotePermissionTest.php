<?php

namespace Tests\Feature;

use App\Http\Controllers\DocumentAndNoteController;
use Tests\TestCase;

class DocNotePermissionTest extends TestCase
{
    public function test_getPermission_unknown_type_returns_empty_array()
    {
        $c = $this->app->make(DocumentAndNoteController::class);
        $m = new \ReflectionMethod($c, '__getPermission');
        $m->setAccessible(true);
        $this->assertEquals([], $m->invoke($c, 1, 1, 'App\FooUnknown'));
    }

    public function test_getPermission_legacy_contact_returns_permissions()
    {
        $c = $this->app->make(DocumentAndNoteController::class);
        $m = new \ReflectionMethod($c, '__getPermission');
        $m->setAccessible(true);
        $this->assertEquals(
            ['view', 'create', 'delete'],
            $m->invoke($c, 1, 1320, 'App\Contact')
        );
    }
}
