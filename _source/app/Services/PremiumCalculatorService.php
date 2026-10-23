<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * PremiumCalculatorService
 *
 * Pricing Rules:
 *   Base daily rate by plan:
 *     basic    → $1.50 / day
 *     standard → $3.00 / day
 *     premium  → $6.00 / day
 *
 *   Destination multiplier:
 *     domestic      → 1.0x
 *     international → 1.5x
 *     schengen      → 2.0x
 *
 *   Formula: premium = max(days × daily_rate × multiplier, MIN_PREMIUM)
 *   Minimum premium: $5.00
 */
class PremiumCalculatorService
{
    private const DAILY_RATES = [
        'basic'    => 1.50,
        'standard' => 3.00,
        'premium'  => 6.00,
    ];

    private const DESTINATION_MULTIPLIERS = [
        'domestic'      => 1.0,
        'international' => 1.5,
        'schengen'      => 2.0,
    ];

    private const MINIMUM_PREMIUM = 5.00;

    public function calculate(
        string $plan,
        string $destination,
        string $startDate,
        string $endDate
    ): float {
        if (!array_key_exists($plan, self::DAILY_RATES)) {
            throw new InvalidArgumentException("Unsupported plan: {$plan}");
        }

        if (!array_key_exists($destination, self::DESTINATION_MULTIPLIERS)) {
            throw new InvalidArgumentException("Unsupported destination: {$destination}");
        }

        $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));

        if ($days <= 0) {
            throw new InvalidArgumentException("End date must be after start date.");
        }

        $premium = round(
            $days * self::DAILY_RATES[$plan] * self::DESTINATION_MULTIPLIERS[$destination],
            2
        );

        return max($premium, self::MINIMUM_PREMIUM);
    }
}
