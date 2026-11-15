<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\JobOffer\Application\DTO\IngestJobOfferRequest;
use App\JobOffer\Application\IngestJobOfferHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class IngestJobOfferController extends AbstractController
{
    #[Route('/api/offers', name: 'api_offers_ingest', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] IngestJobOfferRequest $request,
        IngestJobOfferHandler $handler,
    ): JsonResponse {
        $offer = $handler->handle($request);

        return $this->json([
            'id' => $offer->getId(),
            'title' => $offer->getTitle(),
            'status' => $offer->getStatus(),
        ], Response::HTTP_CREATED);
    }
}