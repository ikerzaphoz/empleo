<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngestJobOfferControllerTest extends WebTestCase
{
    public function testIngestJobOfferSuccessfully(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/offers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => 'Backend Developer',
                'company' => 'Acme Corp',
                'url' => 'https://example.com/job/123',
                'description' => 'Descripción de prueba',
                'matchScore' => 85,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(201);

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertJson($content);
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('new', $payload['status']);
    }

    public function testIngestJobOfferValidationFailure(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/offers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => '',
                'company' => 'Acme Corp',
                'url' => 'not-a-valid-url',
                'description' => '',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(422);
    }
}
