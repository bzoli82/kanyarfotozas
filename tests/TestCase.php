<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A kézbesítési gyorsítótár (delivery cache) tesztekben soha nem a valódi
        // lemezre írjon — a fizetést lezáró/letöltő tesztek ezt a diskre másolnak.
        Storage::fake('delivery');
    }
}
