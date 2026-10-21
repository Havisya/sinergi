<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckFingerprintLogs extends Command
{
    protected $signature = 'fingerprint:check';

    protected $description = 'Cek seluruh event fingerprint tanpa filter minor';

    public function handle()
    {
        $ip   = '192.168.1.20';
        $user = 'admin';
        $pass = '12345678adg';

        $this->info("==============================================");
        $this->info("  CHECK FINGERPRINT LOG");
        $this->info("==============================================");

        try {

            $response = Http::withDigestAuth($user, $pass)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post(
                    "http://{$ip}/ISAPI/AccessControl/AcsEvent?format=json",
                    [
                        'AcsEventCond' => [
                            'searchID' => 'CHECK' . time(),

                            'searchResultPosition' => 0,

                            'maxResults' => 1000,

                            /*
                             * HANYA MAJOR
                             * JANGAN PAKAI MINOR 38
                             */
                            'major' => 5,

                            'startTime' => '2026-08-26T00:00:00',

                            'endTime' => '2026-08-26T23:59:59',

                            'sort' => 'asc',
                        ],
                    ]
                );

            $this->info(
                "HTTP STATUS: " . $response->status()
            );

            if (!$response->successful()) {

                $this->error("GAGAL!");
                $this->error($response->body());

                return self::FAILURE;
            }

            $data = $response->json();

            $event = $data['AcsEvent'] ?? [];

            $logs = $event['InfoList'] ?? [];

            $total = $event['numOfMatches'] ?? count($logs);

            $this->info(
                "TOTAL EVENT: {$total}"
            );

            $this->info(
                "EVENT DITERIMA: " . count($logs)
            );

            $this->newLine();

            if (empty($logs)) {

                $this->warn(
                    "TIDAK ADA EVENT DITEMUKAN."
                );

                return self::SUCCESS;
            }

            foreach ($logs as $index => $log) {

                $nip =
                    $log['employeeNoString']
                    ?? '-';

                $time =
                    $log['time']
                    ?? '-';

                $major =
                    $log['major']
                    ?? '-';

                $minor =
                    $log['minor']
                    ?? '-';

                $eventType =
                    $log['eventType']
                    ?? '-';

                $name =
                    $log['name']
                    ?? '-';

                $card =
                    $log['cardNo']
                    ?? '-';

                $this->line(
                    sprintf(
                        "#%03d | NIP: %-6s | TIME: %-19s | MAJOR: %-4s | MINOR: %-4s | TYPE: %-4s | CARD: %s",
                        $index + 1,
                        $nip,
                        $time,
                        $major,
                        $minor,
                        $eventType,
                        $card
                    )
                );
            }

            $this->newLine();

            $this->info(
                "=============================================="
            );

            $this->info(
                "SELESAI"
            );

            return self::SUCCESS;

        } catch (\Throwable $e) {

            $this->error(
                "ERROR: " . $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
