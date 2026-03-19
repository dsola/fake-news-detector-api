<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClientRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/token', name: 'api_token', methods: ['POST'])]
class TokenController extends AbstractController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            return $this->json(
                ['error' => 'client_id and client_secret are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $client = $this->clientRepository->findOneByClientId($clientId);

        if ($client === null || !$this->passwordHasher->isPasswordValid($client, $clientSecret)) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtManager->create($client);

        return $this->json(['token' => $token]);
    }
}
