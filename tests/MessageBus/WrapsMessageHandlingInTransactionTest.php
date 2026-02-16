<?php

namespace SimpleBus\DoctrineORMBridge\Tests\MessageBus;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Error;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SimpleBus\DoctrineORMBridge\MessageBus\WrapsMessageHandlingInTransaction;
use Throwable;

class WrapsMessageHandlingInTransactionTest extends TestCase
{
    #[Test]
    public function itWrapsTheNextMiddlewareInATransaction(): void
    {
        $nextIsCalled = false;
        $message = $this->dummyMessage();
        $nextMiddlewareCallable = function ($actualMessage) use ($message, &$nextIsCalled) {
            $this->assertSame($message, $actualMessage);
            $nextIsCalled = true;
        };
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $entityManagerName = 'default';
        $entityManager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['wrapInTransaction'])
            ->getMock();
        $entityManager
            ->expects($this->once())
            ->method('wrapInTransaction')
            ->willReturnCallback(
                function (callable $transactionalCallback) {
                    $transactionalCallback();
                }
            );

        $managerRegistry
            ->expects($this->any())
            ->method('getManager')
            ->with($entityManagerName)
            ->willReturn($entityManager);

        $middleware = new WrapsMessageHandlingInTransaction($managerRegistry, $entityManagerName);

        $middleware->handle($message, $nextMiddlewareCallable);

        $this->assertTrue($nextIsCalled);
    }

    #[Test]
    #[DataProvider('errorProvider')]
    public function itResetsTheEntityManagerIfTheTransactionFails(Throwable $error): void
    {
        $message = $this->dummyMessage();
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $entityManagerName = 'default';
        $alwaysFailingEntityManager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['wrapInTransaction'])
            ->getMock();
        $alwaysFailingEntityManager
            ->expects($this->once())
            ->method('wrapInTransaction')
            ->willReturnCallback(
                function () use ($error) {
                    throw $error;
                }
            );

        $managerRegistry
            ->expects($this->any())
            ->method('getManager')
            ->with($entityManagerName)
            ->willReturn($alwaysFailingEntityManager);

        $managerRegistry
            ->expects($this->once())
            ->method('resetManager')
            ->with($entityManagerName);

        $middleware = new WrapsMessageHandlingInTransaction($managerRegistry, $entityManagerName);

        try {
            $middleware->handle(
                $message,
                function () {}
            );
            $this->fail('An exception should have been thrown');
        } catch (Throwable $actualError) {
            $this->assertSame($error, $actualError);
        }
    }

    /**
     * @return array<Throwable[]>
     */
    public static function errorProvider(): array
    {
        return [
            [
                new Exception(),
            ],
            [
                new Error(),
            ],
        ];
    }

    /**
     * @return DummyMessage|MockObject
     */
    private function dummyMessage()
    {
        return $this->createMock(DummyMessage::class);
    }
}

class DummyMessage {}
