<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2017 Matthias Noback.
 */

namespace SimpleBus\DoctrineORMBridge\Tests\PHPUnitTestServiceContainer\PHPUnit;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use SimpleBus\DoctrineORMBridge\Tests\PHPUnitTestServiceContainer\ServiceContainer;
use SimpleBus\DoctrineORMBridge\Tests\PHPUnitTestServiceContainer\ServiceProvider;

/**
 * Extend from this test case to make use of a service container in your tests.
 */
trait TestCaseWithServiceContainer
{
    protected ?ServiceContainer $container = null;

    #[Before]
    public function setUpContainer(): void
    {
        $this->container = $this->createServiceContainer();
        $this->container->setUp();
    }

    #[After]
    public function tearDownContainer(): void
    {
        if (null === $this->container) {
            return;
        }

        $this->container->tearDown();
        $this->container = null;
    }

    /**
     * Return an array of ServiceProviderInterface instances you want to use in this test case.
     *
     * @return ServiceProvider[]
     */
    abstract protected function getServiceProviders(): array;

    private function createServiceContainer(): ServiceContainer
    {
        $container = new ServiceContainer();

        foreach ($this->getServiceProviders() as $serviceProvider) {
            $container->register($serviceProvider);
        }

        return $container;
    }
}
