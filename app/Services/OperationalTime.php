<?php

namespace App\Services;

class OperationalTime
{
    public function fromLogs(iterable $visits, array $otpByVisit): array
    {
        $rows = [];
        foreach ($visits as $visit) $rows[] = [
            'start' => $this->timestamp($visit->logstartdate, $visit->logstarttime),
            'end' => $this->timestamp($visit->logenddate, $visit->logendtime),
            'otp' => !empty($otpByVisit[$visit->routekey.':'.$visit->logkey]),
            'customer' => $visit->customercode,
        ];
        return $this->summarize($rows);
    }

    public function fromTracking(iterable $visits): array
    {
        $rows = [];
        foreach ($visits as $visit) $rows[] = [
            'start' => $this->timestamp($visit['visit_start_date'] ?? null, $visit['visit_start_time'] ?? null),
            'end' => $this->timestamp($visit['visit_end_date'] ?? null, $visit['visit_end_time'] ?? null),
            'otp' => $visit['operational_otp'] ?? !empty($visit['otp_logs']),
            'customer' => $visit['customercode'] ?? null,
        ];
        return $this->summarize($rows);
    }

    private function summarize(array $rows): array
    {
        $eligible = array_values(array_filter($rows, fn ($row) => !$row['otp'] && $row['start'] !== null));
        usort($eligible, fn ($a, $b) => $a['start'] <=> $b['start']);
        $first = $eligible[0] ?? null;
        $last = $eligible ? $eligible[array_key_last($eligible)] : null;
        $start = $first['start'] ?? null;
        $end = $last['end'] ?? null;
        // An unfinished final visit must not silently fall back to an earlier checkout.
        $valid = $start !== null && $end !== null && $end >= $last['start'];
        return [
            'start' => $start === null ? null : date('Y-m-d H:i:s', $start),
            'end' => $valid ? date('Y-m-d H:i:s', $end) : null,
            'minutes' => $valid ? ($end - $start) / 60 : null,
            'first_customer' => $first['customer'] ?? null,
            'last_customer' => $last['customer'] ?? null,
        ];
    }

    private function timestamp(mixed $date, mixed $time): ?int
    {
        if (!$date || !$time || str_starts_with((string) $date, '0000-')) return null;
        $value = strtotime(substr((string) $date, 0, 10).' '.$time);
        return $value === false ? null : $value;
    }
}
