<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Utility;

class HelperFunctions
{
    /**
     * Splits a comma separated list and trims the values.
     *
     * @return array<string>
     */
    public static function trimExplode(string|array|null $csvText): array
    {
        if (is_array($csvText)) {
            $csvText = implode(',', $csvText);
        }
        return array_values(array_filter(
            array_map('trim', explode(',', (string)$csvText)),
            static fn(string $value): bool => $value !== '',
        ));
    }
}
