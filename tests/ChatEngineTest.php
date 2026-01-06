<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\ChatEngineServiceProvider;

it('can load the package', function () {
    expect(class_exists(ChatEngineServiceProvider::class))->toBeTrue();
});

it('registers the chat singleton', function () {
    expect(app('chat'))->toBeInstanceOf(\Ritechoice23\ChatEngine\Chat::class);
});

it('has facade available', function () {
    expect(\Ritechoice23\ChatEngine\Facades\Chat::thread())
        ->toBeInstanceOf(\Ritechoice23\ChatEngine\Builders\ThreadBuilder::class);
});
