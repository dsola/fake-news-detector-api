<?php

namespace App\Controller;

use App\Dto\CreateArticleRequest;
use App\Repository\ArticleRepository;
use App\Service\ArticleCreationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/articles')]
class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticleCreationService $articleCreationService,
        private readonly ArticleRepository $articleRepository,
    ) {}

    #[Route('', name: 'create_article', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateArticleRequest $request
    ): JsonResponse
    {
        $articleResource = $this->articleCreationService->create([
            'title' => $request->title,
            'url' => $request->url,
        ]);

        return $this->json($articleResource->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get_article', requirements: ['id' => '.+'], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $articleId = Uuid::fromString($id);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'error' => 'Invalid article ID format',
            ], Response::HTTP_BAD_REQUEST);
        }

        $article = $this->articleRepository->findWithVerifications($articleId);

        if ($article === null) {
            return $this->json([
                'error' => 'Article not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $article,
            Response::HTTP_OK,
            [],
            ['groups' => ['article:read', 'verification:read']]
        );
    }
}
