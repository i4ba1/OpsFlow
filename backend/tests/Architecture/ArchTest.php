<?php

arch('domain layer does not depend on HTTP concerns')
    ->expect('App\Domain')
    ->not->toUse(['App\Http\Controllers']);

arch('debugging helpers are not used in application code')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
