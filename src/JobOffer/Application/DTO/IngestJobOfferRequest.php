<?php

declare(strict_types=1);

namespace App\JobOffer\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class IngestJobOfferRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'El título es obligatorio')]
        #[Assert\Length(max: 255)]
        public string $title,

        #[Assert\NotBlank(message: 'La empresa es obligatoria')]
        #[Assert\Length(max: 255)]
        public string $company,

        #[Assert\NotBlank(message: 'La URL es obligatoria')]
        #[Assert\Url(requireTld: true, message: 'Debe ser una URL válida')]
        #[Assert\Length(max: 500)]
        public string $url,

        #[Assert\NotBlank(message: 'La descripción no puede estar vacía')]
        public string $description,

        #[Assert\Range(min: 0, max: 100)]
        public ?int $matchScore = null,
    ) {
    }
}
