<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| This file configures the shared Laravel test case for every Pest test.
| The Tests\TestCase class extends the Laravel foundation TestCase.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');
