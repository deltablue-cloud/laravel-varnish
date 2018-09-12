<?php

namespace DeltaBlue\Varnish\Test;

use DeltaBlue\Varnish\VarnishServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            VarnishServiceProvider::class,
        ];
    }
}
