<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Prospect;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeocodingService
{
    private const API_URL = 'https://api-adresse.data.gouv.fr/search/';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $adresse): ?array
    {
        if ('' === trim($adresse)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => ['q' => $adresse, 'limit' => 1],
                'timeout' => 4,
            ]);

            $data = $response->toArray();
            $coordinates = $data['features'][0]['geometry']['coordinates'] ?? null;

            if (!\is_array($coordinates) || 2 !== \count($coordinates)) {
                return null;
            }

            return [
                'lat' => (float) $coordinates[1],
                'lng' => (float) $coordinates[0],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function geocodeProspect(Prospect $prospect): void
    {
        $coords = $this->geocode((string) $prospect->getAdresse());

        if (null !== $coords) {
            $prospect->setLatitude($coords['lat']);
            $prospect->setLongitude($coords['lng']);
        }
    }
}
