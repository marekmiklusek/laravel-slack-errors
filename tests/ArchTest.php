<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use MarekMiklusek\SlackErrors\SlackErrorsServiceProvider;

arch('source code uses strict types')
    ->expect('MarekMiklusek\SlackErrors')
    ->toUseStrictTypes();

arch('source code declares no debug statements')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch('source classes are final')
    ->expect('MarekMiklusek\SlackErrors')
    ->classes()
    ->toBeFinal();

arch('the service provider extends the framework provider')
    ->expect(SlackErrorsServiceProvider::class)
    ->toExtend(ServiceProvider::class);
