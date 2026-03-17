<?php

namespace App\Tests\TestDouble;

use PHPUnit\Framework\Assert;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessageBusTestDouble implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    private array $dispatchedMessages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = $message instanceof Envelope
            ? $message
            : new Envelope($message, $stamps);

        $this->dispatchedMessages[] = $envelope->getMessage();

        return $envelope;
    }

    /**
     * @return list<object>
     */
    public function getDispatchedMessages(): array
    {
        return $this->dispatchedMessages;
    }

    public function assertEventDispatched(string $eventClass): void
    {
        foreach ($this->dispatchedMessages as $message) {
            if ($message instanceof $eventClass) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail(sprintf(
            'Expected event "%s" to be dispatched, but got: [%s].',
            $eventClass,
            implode(', ', array_map(static fn (object $message): string => $message::class, $this->dispatchedMessages))
        ));
    }
}