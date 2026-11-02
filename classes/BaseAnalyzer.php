<?php

/**
 * Abstract parent class for all analyzers.
 *
 * It holds the data and the reusable helpers (date conversion, counting,
 * median, percentage). Child classes implement analyze() with the
 * statistics that are specific to their dataset.
 */
abstract class BaseAnalyzer
{
    // Records (GeoJSON features). protected => visible to child classes.
    protected array $features;
    protected DateTimeZone $timezone;

    /**
     * @param array  $features     The records to analyze
     * @param string $timezoneName Timezone used to interpret timestamps (default: DC)
     */
    public function __construct(array $features, string $timezoneName = "America/New_York")
    {
        $this->features = $features;
        $this->timezone = new DateTimeZone($timezoneName);
    }

    // Every analyzer must return its statistics as an associative array.
    abstract public function analyze(): array;

    // Convert a UTC timestamp string (e.g. 2026-01-05T20:55:52Z) to local time.
    protected function getLocalDate(?string $date): ?DateTimeImmutable
    {
        if ($date === null || trim($date) === "") {
            return null;
        }

        try {
            return (new DateTimeImmutable($date))->setTimezone($this->timezone);
        } catch (Exception $e) {
            return null; // unparseable dates are skipped, not fatal
        }
    }

    // Collect the non-empty values of one property across all records.
    protected function getValues(string $field): array
    {
        $values = array_map(
            fn($feature) => $feature["properties"][$field] ?? null,
            $this->features
        );

        return array_filter(
            $values,
            fn($value) => is_scalar($value) && trim((string)$value) !== ""
        );
    }

    // Frequency table: value => number of occurrences, highest first.
    protected function countValues(array $values): array
    {
        $counts = [];

        foreach ($values as $value) {
            if (!isset($counts[$value])) {
                $counts[$value] = 0;
            }
            $counts[$value]++;
        }

        arsort($counts);

        return $counts;
    }

    // The first (largest) entry of a frequency table.
    protected function topEntry(array $counts): array
    {
        if (empty($counts)) {
            return ["value" => "N/A", "count" => 0];
        }

        $value = array_key_first($counts);

        return ["value" => (string)$value, "count" => $counts[$value]];
    }

    // Percentage of $part out of $total, rounded to $decimals places.
    protected function percentage(int $part, int $total, int $decimals = 2): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, $decimals);
    }

    // Median of a list of numbers (average of the two middle values if even).
    protected function median(array $numbers, int $decimals = 2): float
    {
        if (empty($numbers)) {
            return 0.0;
        }

        sort($numbers);

        $count = count($numbers);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return round(($numbers[$middle - 1] + $numbers[$middle]) / 2, $decimals);
        }

        return round($numbers[$middle], $decimals);
    }
}
