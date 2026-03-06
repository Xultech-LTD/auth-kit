<?php

namespace Xul\AuthKit\Tests\Fixtures;

final class FakeLoginController
{
    public function __invoke()
    {
        return 'fake';
    }
}