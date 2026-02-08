<?php

namespace App\EventListener;

use App\Exception\CorruptedArticleContentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Psr\Log\LoggerInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onException')]
class ExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof CorruptedArticleContentException) {
            $this->handleCorruptedContent($event, $exception);
        } elseif ($exception instanceof ValidationFailedException) {
            $this->handleValidationFailed($event, $exception);
        }
    }

    private function handleValidationFailed(ExceptionEvent $event, ValidationFailedException $exception): void
    {
        $this->logger->warning('Validation failed', [
            'errors' => $exception->getViolations(),
        ]);

        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        $response = new JsonResponse(
            [
                'error' => 'Validation Failed',
                'message' => 'The provided data is invalid',
                'violations' => $errors,
            ],
            Response::HTTP_BAD_REQUEST
        );

        $event->setResponse($response);
    }

    private function handleCorruptedContent(ExceptionEvent $event, CorruptedArticleContentException $exception): void
    {
        $this->logger->warning('Corrupted article content', [
            'message' => $exception->getMessage(),
        ]);

        $response = new JsonResponse(
            [
                'error' => 'Bad Request',
                'message' => $exception->getMessage(),
            ],
            Response::HTTP_BAD_REQUEST
        );

        $event->setResponse($response);
    }
}
