<?php

arch('controllers do not call the model or DB facades directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'App\Models']);

arch('domain layer does not depend on HTTP concerns')
    ->expect('App\Domain')
    ->not->toUse(['App\Http\Controllers']);

arch('debugging helpers are not used in application code')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
