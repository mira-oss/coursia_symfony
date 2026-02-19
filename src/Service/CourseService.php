<?php

namespace App\Service;

/**
 * Service métier pour gérer la logique liée aux Course et Journey
 * Ce service contient toute la logique "intelligente" de l'application
 */
class CourseService
{
    /**
     * Détermine automatiquement le type de course en fonction des adresses
     * 
     * @param string $departureAddress Adresse de départ
     * @param string $deliveryAddress Adresse d'arrivée
     * @return string 'national' | 'regional' | 'international'
     */
    public function determineCourseType(string $departureAddress, string $deliveryAddress): string
    {
        $departureCountry = $this->extractCountryFromAddress($departureAddress);
        $deliveryCountry = $this->extractCountryFromAddress($deliveryAddress);

        // Même pays = National
        if ($departureCountry === $deliveryCountry) {
            return 'national';
        }

        $departureContinent = $this->getContinent($departureCountry);
        $deliveryContinent = $this->getContinent($deliveryCountry);

        // Même continent = Regional
        if ($departureContinent === $deliveryContinent) {
            return 'regional';
        }

        // Différents continents = International
        return 'international';
    }

    /**
     * Extrait le code pays depuis une adresse complète
     * Utilise une heuristique simple (dernier mot de l'adresse)
     * TODO: Améliorer avec une API de géocodage pour plus de précision
     */
    private function extractCountryFromAddress(string $address): string
    {
        // Liste des pays connus (à enrichir)
        $knownCountries = [
            'france' => 'FR',
            'bénin' => 'BJ',
            'benin' => 'BJ',
            'togo' => 'TG',
            'nigeria' => 'NG',
            'côte d\'ivoire' => 'CI',
            'ghana' => 'GH',
            'sénégal' => 'SN',
            'senegal' => 'SN',
            'mali' => 'ML',
            'burkina faso' => 'BF',
            'niger' => 'NE',
            'cameroun' => 'CM',
            'cameroon' => 'CM',
        ];

        $addressLower = strtolower($address);

        foreach ($knownCountries as $countryName => $countryCode) {
            if (str_contains($addressLower, $countryName)) {
                return $countryCode;
            }
        }

        // Par défaut, on suppose que c'est le dernier mot
        $parts = explode(',', $address);
        $lastPart = trim(end($parts));

        return strtoupper(substr($lastPart, 0, 2));
    }

    /**
     * Détermine le continent d'un code pays
     */
    private function getContinent(string $countryCode): string
    {
        $continents = [
            'europe' => ['FR', 'DE', 'IT', 'ES', 'BE', 'NL', 'UK', 'GB', 'PT', 'CH', 'AT', 'SE', 'NO', 'DK', 'FI', 'IE', 'PL', 'GR'],
            'africa' => ['BJ', 'TG', 'NG', 'CI', 'GH', 'SN', 'ML', 'BF', 'NE', 'CM', 'MA', 'DZ', 'TN', 'EG', 'ZA', 'KE', 'ET', 'TZ', 'UG'],
            'north_america' => ['US', 'CA', 'MX'],
            'south_america' => ['BR', 'AR', 'CO', 'PE', 'CL', 'VE', 'EC'],
            'asia' => ['CN', 'JP', 'IN', 'KR', 'ID', 'SA', 'AE', 'TH', 'VN', 'MY', 'PH', 'PK', 'BD'],
            'oceania' => ['AU', 'NZ'],
        ];

        foreach ($continents as $continent => $countries) {
            if (in_array($countryCode, $countries)) {
                return $continent;
            }
        }

        return 'unknown';
    }

    /**
     * Calcule le prix estimé d'une course
     * Cette fonction peut être utilisée pour proposer un prix de base aux Chevaliers
     * 
     * @param string $type Type de course (national, regional, international)
     * @param float $weight Poids du colis en kg
     * @return float Prix estimé
     */
    public function calculateEstimatedPrice(string $type, float $weight): float
    {
        $baseRates = [
            'national' => 2.0,      // 2€/kg pour national
            'regional' => 5.0,      // 5€/kg pour régional
            'international' => 10.0  // 10€/kg pour international
        ];

        $rate = $baseRates[$type] ?? 5.0;

        return $weight * $rate;
    }

    /**
     * Valide si une course peut être acceptée par un Chevalier
     * Vérifie la compatibilité géographique et horaire
     */
    public function canAcceptCourse($journey, $course): bool
    {
        // TODO: Implémenter la logique de validation
        // - Vérifier si le trajet passe par les points de la course
        // - Vérifier si les horaires sont compatibles
        // - Vérifier si le poids est acceptable

        return true;
    }
}
