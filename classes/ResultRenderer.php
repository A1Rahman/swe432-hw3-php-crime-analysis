<?php

/**
 * Responsible ONLY for producing HTML. It never reads files or computes
 * statistics. Every dynamic value goes through escape() (htmlspecialchars)
 * so uploaded data can never inject HTML or scripts (XSS).
 */
class ResultRenderer
{
    // The 10 questions, keyed by the same keys CrimeAnalyzer::analyze() returns.
    private const QUESTIONS = [
        "medianDelay"         => "What is the median delay, in hours, between when a crime started and when it was reported? (Reports dated before the start time are ignored.)",
        "mostFrequentOffense" => "What is the most frequently reported offense?",
        "offenseCounts"       => "How many incidents were reported for each offense type?",
        "topWard"             => "Which ward has the most reported incidents?",
        "dailyAverage"        => "On average, how many incidents were reported per day (counting only days with at least one report)?",
        "topShift"            => "Which shift has the most reported incidents?",
        "gunPercentage"       => "What percentage of incidents involved a gun?",
        "topMonth"            => "Which month has the most reported incidents?",
        "topWeekday"          => "Which day of the week has the most reports?",
        "weekendPercentage"   => "What percentage of incidents were reported on weekends?"
    ];

    // Convert special characters to HTML entities.
    public function escape(string $value, int $flags = ENT_QUOTES): string
    {
        return htmlspecialchars($value, $flags, "UTF-8");
    }

    // The error message shown under the upload form.
    public function renderError(?string $message): string
    {
        return $message === null ? "" : $this->escape($message);
    }

    /**
     * The numbered list of questions. If $results is given, each question
     * is followed by its computed answer.
     */
    public function renderQuestions(?array $results = null): string
    {
        $html = "<ol class=\"results-list\">\n";

        foreach (self::QUESTIONS as $key => $question) {
            $html .= "  <li>\n";
            $html .= "    <strong>" . $this->escape($question) . "</strong>\n";

            if ($results !== null) {
                $html .= $this->renderAnswer($key, $results[$key]);
            }

            $html .= "  </li>\n";
        }

        return $html . "</ol>\n";
    }

    // The "additional information" panel: timestamp, processing time, record count.
    public function renderProcessingInfo(string $timestamp, float $seconds, int $recordCount = 0, string $fileName = ""): string
    {
        $time  = $this->escape($timestamp);
        $secs  = $this->escape((string)$seconds);
        $count = $this->escape(number_format($recordCount));
        $file  = $this->escape($fileName);

        return "<p><strong>Timestamp:</strong> {$time}</p>\n"
             . "<p><strong>Total processing time:</strong> {$secs} seconds</p>\n"
             . "<p><strong>Records processed:</strong> {$count}</p>\n"
             . "<p><strong>File:</strong> {$file}</p>\n";
    }

    // Format one answer depending on its type.
    private function renderAnswer(string $key, $answer): string
    {
        // Q3: a list with one line per offense.
        if ($key === "offenseCounts") {
            $items = "";
            foreach ($answer as $offense => $count) {
                $items .= "      <li>" . $this->escape((string)$offense) . ": "
                        . $this->escape(number_format($count)) . "</li>\n";
            }
            return "    <ul>\n{$items}    </ul>\n";
        }

        // "Top" answers are ["value" => ..., "count" => ...].
        if (is_array($answer)) {
            $value = $this->escape($answer["value"]);
            $count = $this->escape(number_format($answer["count"]));
            return "    <p>{$value} ({$count} incidents)</p>\n";
        }

        // Plain numbers get a unit depending on the question.
        $number = $this->escape((string)$answer);

        $text = match ($key) {
            "medianDelay"   => "{$number} hours",
            "dailyAverage"  => "{$number} incidents per day",
            default         => "{$number}%"
        };

        return "    <p>{$text}</p>\n";
    }
}
