<?php

declare(strict_types=1);

namespace App\JobOffer\Application;

use App\Entity\JobOffer;
use App\JobOffer\Application\DTO\IngestJobOfferRequest;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class IngestJobOfferHandler
{
    public function __construct(
        private JobOfferRepository $jobOfferRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(IngestJobOfferRequest $request): JobOffer
    {
        // 1. Prevenir ofertas duplicadas verificando la existencia previa de la URL
        $existingOffer = $this->jobOfferRepository->findOneBy(['url' => $request->url]);
        if ($existingOffer !== null) {
            return $existingOffer;
        }

        // 2. Mapear los datos del DTO a la entidad de dominio
        $offer = new JobOffer();
        $offer->setTitle($request->title);
        $offer->setCompany($request->company);
        $offer->setUrl($request->url);
        $offer->setDescription($request->description);
        $offer->setMatchScore($request->matchScore);
        $offer->setStatus('new');
        $offer->setCreatedAt(new \DateTimeImmutable());

        // 3. Persistir la entidad a través del EntityManager
        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        return $offer;
    }
}
