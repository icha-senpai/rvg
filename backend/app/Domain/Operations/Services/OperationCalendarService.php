<?php

namespace App\Domain\Operations\Services;

use App\Models\Operation;

class OperationCalendarService
{
    public function makeIcs(Operation $operation): string
    {
        $startsAt = $operation->starts_at?->copy()->utc();

        if (! $startsAt) {
            throw new \RuntimeException('Operation has no start time.');
        }

        $endsAt = $operation->ends_at?->copy()->utc() ?? $startsAt->copy()->addHour();

        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $uid = "operation-{$operation->id}@{$host}";

        $summary = $this->escapeIcsText($operation->title ?: "Operation #{$operation->id}");

        $descriptionParts = array_values(array_filter([
            $operation->description,
            $operation->notes,
            route('operations.show', $operation->id),
        ]));

        $description = $this->escapeIcsText(implode("\n\n", $descriptionParts));

        $dtstamp = now()->utc()->format('Ymd\\THis\\Z');
        $dtstart = $startsAt->format('Ymd\\THis\\Z');
        $dtend = $endsAt->format('Ymd\\THis\\Z');

        $created = $operation->created_at?->copy()->utc()->format('Ymd\\THis\\Z');
        $lastModified = $operation->updated_at?->copy()->utc()->format('Ymd\\THis\\Z');

        $status = $operation->isCanceled() ? 'CANCELLED' : 'CONFIRMED';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//RVG//Operations//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtstamp}",
            "DTSTART:{$dtstart}",
            "DTEND:{$dtend}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            'CLASS:PUBLIC',
            "STATUS:{$status}",
        ];

        if ($created) {
            $lines[] = "CREATED:{$created}";
        }

        if ($lastModified) {
            $lines[] = "LAST-MODIFIED:{$lastModified}";
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        $folded = [];
        foreach ($lines as $line) {
            foreach ($this->foldIcsLine($line) as $l) {
                $folded[] = $l;
            }
        }

        return implode("\r\n", $folded) . "\r\n";
    }

    public function makeFilename(Operation $operation): string
    {
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($operation->title ?: "operation-{$operation->id}"));
        $base = trim($base, '-');

        if ($base === '') {
            $base = "operation-{$operation->id}";
        }

        return $base . '.ics';
    }

    private function escapeIcsText(?string $value): string
    {
        $value = $value ?? '';
        $value = str_replace("\r\n", "\n", $value);
        $value = str_replace("\r", "\n", $value);
        $value = str_replace("\\", "\\\\", $value);
        $value = str_replace(";", "\\;", $value);
        $value = str_replace(",", "\\,", $value);
        $value = str_replace("\n", "\\n", $value);

        return $value;
    }

    private function foldIcsLine(string $line): array
    {
        $max = 75;

        if (strlen($line) <= $max) {
            return [$line];
        }

        $out = [];
        $out[] = substr($line, 0, $max);
        $rest = substr($line, $max);

        while ($rest !== '') {
            $out[] = ' ' . substr($rest, 0, $max - 1);
            $rest = substr($rest, $max - 1);
        }

        return $out;
    }
}
