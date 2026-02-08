<?php

namespace App\Controller;

use App\Dto\CreateArticleRequest;
use App\Service\ArticleCreationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/articles')]
class ArticleController extends AbstractController
{
    public function __construct(private readonly ArticleCreationService $articleCreationService) {}

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
}
