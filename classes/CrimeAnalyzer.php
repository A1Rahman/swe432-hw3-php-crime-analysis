<?php

require_once __DIR__ . "/BaseAnalyzer.php";

/**
 * Computes the 10 statistics for the DC crime incident dataset.
 * Inherits the generic helpers from BaseAnalyzer.
 */
class CrimeAnalyzer extends BaseAnalyzer
{
    // Cache of report timestamps so we only parse them once.
    private ?array $reportDates = null;

    // Calculate all 10 statistics. Keys match ResultRenderer::QUESTIONS.
    public function analyze(): array
    {
        return [
            "medianDelay"       => $this->getMedianDelay(),
            "mostFrequentOffense" => $this->topEntry($this->countValues($this->getValues("OFFENSE"))),
            "offenseCounts"     => $this->countValues($this->getValues("OFFENSE")),
            "topWard"           => $this->topEntry($this->countValues($this->getValues("WARD"))),
            "dailyAverage"      => $this->getDailyAverage(),
            "topShift"          => $this->topEntry($this->countValues($this->getValues("SHIFT"))),
            "gunPercentage"     => $this->getGunPercentage(),
            "topMonth"          => $this->getTopDatePart("F"),  // F = full month name
            "topWeekday"        => $this->getTopDatePart("l"),  // l = full day name
            "weekendPercentage" => $this->getWeekendPercentage()
        ];
    }

    // Local-time DateTimeImmutable for every record that has a valid report date.
    private function getReportDates(): array
    {
        if ($this->reportDates === null) {
            $dates = array_map(
                fn($feature) => $this->getLocalDate($feature["properties"]["REPORT_DAT"] ?? null),
                $this->features
            );

            // array_values re-indexes the array after filtering out nulls.
            $this->reportDates = array_values(array_filter($dates, fn($d) => $d !== null));
        }

        return $this->reportDates;
    }

    // Q1: median delay (hours) between START_DATE and REPORT_DAT.
    // Records reported BEFORE they started are ignored.
    private function getMedianDelay(): float
    {
        $delays = [];

        foreach ($this->features as $feature) {
            $start  = $this->getLocalDate($feature["properties"]["START_DATE"] ?? null);
            $report = $this->getLocalDate($feature["properties"]["REPORT_DAT"] ?? null);

            if ($start === null || $report === null || $report < $start) {
                continue;
            }

            $hours = ($report->getTimestamp() - $start->getTimestamp()) / 3600;
            array_push($delays, $hours);
        }

        return $this->median($delays);
    }

    // Q5: average incidents per day, counting only days with at least one report.
    private function getDailyAverage(): float
    {
        $days = array_map(fn($d) => $d->format("Y-m-d"), $this->getReportDates());

        $numberOfDays = count(array_unique($days));

        if ($numberOfDays === 0) {
            return 0.0;
        }

        return round(count($days) / $numberOfDays, 2);
    }

    // Q7: percentage of incidents whose METHOD is GUN.
    private function getGunPercentage(): float
    {
        $gunIncidents = array_filter(
            $this->features,
            fn($feature) => strtoupper(trim($feature["properties"]["METHOD"] ?? "")) === "GUN"
        );

        return $this->percentage(count($gunIncidents), count($this->features));
    }

    // Q8 and Q9: most common month ("F") or weekday ("l") of the report date.
    private function getTopDatePart(string $format): array
    {
        $parts = array_map(fn($d) => $d->format($format), $this->getReportDates());

        return $this->topEntry($this->countValues($parts));
    }

    // Q10: percentage of incidents reported on a Saturday or Sunday.
    private function getWeekendPercentage(): float
    {
        $weekendReports = 0;

        foreach ($this->getReportDates() as $date) {
            $dayNumber = (int)$date->format("N"); // 1 = Monday ... 7 = Sunday

            if ($dayNumber === 6 || $dayNumber === 7) {
                $weekendReports++;
            }
        }

        return $this->percentage($weekendReports, count($this->getReportDates()));
    }
}
