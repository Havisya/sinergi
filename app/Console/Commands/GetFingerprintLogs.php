<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Absensi;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\GuzzleException;

class GetFingerprintLogs extends Command
{
    protected $signature = 'fingerprint:pull';

    protected $description = 'Sinkronisasi log fingerprint Hikvision ke database';

    public function handle()
    {
        $ip   = '192.168.1.20';
        $user = 'admin';
        $pass = '12345678adg';

        $url = "http://{$ip}/ISAPI/AccessControl/AcsEvent?format=json";

        $this->newLine();
        $this->line('==============================================');
        $this->line('       FINGERPRINT SYNC HIKVISION');
        $this->line('==============================================');

        $this->info("Menghubungkan ke mesin: {$ip}");

        try {

            /*
            |--------------------------------------------------------------------------
            | CLIENT HTTP
            |--------------------------------------------------------------------------
            */

            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 10,
                'http_errors' => false,
            ]);


            /*
            |--------------------------------------------------------------------------
            | WAKTU PENCARIAN
            |--------------------------------------------------------------------------
            |
            | Device memberikan waktu seperti:
            |
            | 2026-08-27T07:24:09+08:00
            |
            | Tetapi query yang terbukti berhasil dari curl menggunakan:
            |
            | 2026-08-27T00:00:00
            | 2026-08-27T23:59:59
            |
            | Jadi gunakan format tersebut.
            |--------------------------------------------------------------------------
            */

            $tanggal = now('Asia/Jakarta')->format('Y-m-d');

            $startTime = $tanggal . 'T00:00:00';
            $endTime   = $tanggal . 'T23:59:59';


            $this->line(
                'Waktu aplikasi : '
                . now('Asia/Jakarta')->format('Y-m-d H:i:s P')
            );

            $this->line(
                'Tanggal target : '
                . $tanggal
            );


            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

            $position  = 0;
            $maxResult = 100;

            $allLogs = [];


            /*
            |--------------------------------------------------------------------------
            | SEARCH ID
            |--------------------------------------------------------------------------
            |
            | Harus konsisten selama proses pagination.
            |
            */

            $searchID = 'PATRA_' . $tanggal;


            do {

                $this->newLine();

                $this->line(
                    "Mengambil data mulai posisi {$position}..."
                );


                /*
                |--------------------------------------------------------------------------
                | PAYLOAD
                |--------------------------------------------------------------------------
                |
                | JANGAN tambahkan timezone +07 atau +08.
                |
                | JANGAN gunakan minor 38.
                |
                | Kita ambil seluruh major 5 terlebih dahulu.
                |--------------------------------------------------------------------------
                */

                $payload = [
                    'AcsEventCond' => [
                        'searchID' => $searchID,

                        'searchResultPosition' => $position,

                        'maxResults' => $maxResult,

                        'major' => 5,

                        'minor' => 0,

                        'startTime' => $startTime,

                        'endTime' => $endTime,

                        'sort' => 'asc',
                    ],
                ];


                $this->line('PAYLOAD:');

                $this->line(
                    json_encode(
                        $payload,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    )
                );


                /*
                |--------------------------------------------------------------------------
                | REQUEST
                |--------------------------------------------------------------------------
                |
                | Gunakan Guzzle Digest Auth.
                |
                */

                $response = $client->post(
                    $url,
                    [
                        'auth' => [
                            $user,
                            $pass,
                            'digest',
                        ],

                        'headers' => [
                            'Content-Type' => 'application/json',

                            'Accept' => 'application/json',
                        ],

                        RequestOptions::JSON => $payload,
                    ]
                );


                $status = $response->getStatusCode();

                $body = (string) $response->getBody();


                /*
                |--------------------------------------------------------------------------
                | ERROR HTTP
                |--------------------------------------------------------------------------
                */

                if ($status !== 200) {

                    $this->error(
                        'Gagal mengambil data fingerprint.'
                    );

                    $this->error(
                        'HTTP Status: ' . $status
                    );

                    $this->line($body);


                    Log::error(
                        'Fingerprint API Error',
                        [
                            'status' => $status,

                            'url' => $url,

                            'payload' => $payload,

                            'body' => $body,
                        ]
                    );


                    return self::FAILURE;
                }


                /*
                |--------------------------------------------------------------------------
                | DECODE RESPONSE
                |--------------------------------------------------------------------------
                */

                $data = json_decode(
                    $body,
                    true
                );


                if (!is_array($data)) {

                    $this->error(
                        'Response fingerprint bukan JSON valid.'
                    );

                    $this->line($body);

                    return self::FAILURE;
                }


                /*
                |--------------------------------------------------------------------------
                | CEK ERROR DARI DEVICE
                |--------------------------------------------------------------------------
                */

                if (
                    isset($data['statusCode'])
                    && $data['statusCode'] != 1
                ) {

                    $this->error(
                        'Device mengembalikan error.'
                    );

                    $this->line(
                        json_encode(
                            $data,
                            JSON_PRETTY_PRINT
                        )
                    );

                    Log::error(
                        'Fingerprint Device Error',
                        [
                            'payload' => $payload,
                            'response' => $data,
                        ]
                    );

                    return self::FAILURE;
                }


                /*
                |--------------------------------------------------------------------------
                | ACS EVENT
                |--------------------------------------------------------------------------
                */

                $eventData =
                    $data['AcsEvent']
                    ?? [];


                $logs =
                    $eventData['InfoList']
                    ?? [];


                $numOfMatches =
                    (int) (
                        $eventData['numOfMatches']
                        ?? count($logs)
                    );


                $totalMatches =
                    (int) (
                        $eventData['totalMatches']
                        ?? count($logs)
                    );


                $responseStatus =
                    $eventData['responseStatusStrg']
                    ?? '';


                /*
                |--------------------------------------------------------------------------
                | OUTPUT
                |--------------------------------------------------------------------------
                */

                $this->info(
                    'Data diterima: '
                    . count($logs)
                );

                $this->line(
                    'Total hasil device: '
                    . $totalMatches
                );

                $this->line(
                    'Status device: '
                    . $responseStatus
                );


                /*
                |--------------------------------------------------------------------------
                | SIMPAN LOG
                |--------------------------------------------------------------------------
                */

                foreach ($logs as $log) {

                    $allLogs[] = $log;
                }


                /*
                |--------------------------------------------------------------------------
                | PAGINATION
                |--------------------------------------------------------------------------
                */

                if (empty($logs)) {

                    break;
                }


                $position += $numOfMatches;


                if (
                    $responseStatus === 'OK'
                    || $position >= $totalMatches
                ) {

                    break;
                }


                if ($responseStatus !== 'MORE') {

                    break;
                }


            } while (true);


            /*
            |--------------------------------------------------------------------------
            | TOTAL
            |--------------------------------------------------------------------------
            */

            $this->newLine();

            $this->info(
                'TOTAL LOG DITERIMA: '
                . count($allLogs)
            );


            /*
            |--------------------------------------------------------------------------
            | TIDAK ADA DATA
            |--------------------------------------------------------------------------
            */

            if (empty($allLogs)) {

                $this->warn(
                    'Tidak ada log fingerprint ditemukan.'
                );

                $this->warn(
                    'Jika curl manual menemukan data, '
                    . 'bandingkan payload Laravel dengan payload curl.'
                );

                return self::SUCCESS;
            }


            /*
            |--------------------------------------------------------------------------
            | SORT
            |--------------------------------------------------------------------------
            */

            usort(
                $allLogs,
                function ($a, $b) {

                    return strcmp(
                        $a['time'] ?? '',
                        $b['time'] ?? ''
                    );
                }
            );


            /*
            |--------------------------------------------------------------------------
            | COUNTER
            |--------------------------------------------------------------------------
            */

            $countNew    = 0;
            $countUpdate = 0;
            $countSkip   = 0;


            /*
            |--------------------------------------------------------------------------
            | PROSES LOG
            |--------------------------------------------------------------------------
            */

            foreach ($allLogs as $log) {

                /*
                |--------------------------------------------------------------------------
                | VALIDASI
                |--------------------------------------------------------------------------
                */

                if (
                    empty($log['employeeNoString'])
                    || empty($log['time'])
                ) {

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | NIP
                |--------------------------------------------------------------------------
                */

                $nipRaw = trim(
                    (string) $log['employeeNoString']
                );


                $nip = str_pad(
                    $nipRaw,
                    3,
                    '0',
                    STR_PAD_LEFT
                );


                /*
                |--------------------------------------------------------------------------
                | WAKTU DEVICE
                |--------------------------------------------------------------------------
                |
                | Device mengirim contoh:
                |
                | 2026-08-27T07:24:09+08:00
                |
                | Waktu tersebut secara angka harus diperlakukan
                | sebagai WIB pada aplikasi Anda.
                |
                | Jadi kita TIDAK melakukan:
                |
                | Carbon::parse($time)->setTimezone(...)
                |
                | karena itu akan mengurangi 1 jam.
                |--------------------------------------------------------------------------
                */

                $rawTime = trim(
                    (string) $log['time']
                );


                /*
                | Ambil 19 karakter pertama:
                |
                | 2026-08-27T07:24:09
                */

                $localTime = substr(
                    $rawTime,
                    0,
                    19
                );


                $eventTime = Carbon::createFromFormat(
                    'Y-m-d\TH:i:s',
                    $localTime,
                    'Asia/Jakarta'
                );


                $tanggalLog =
                    $eventTime->format('Y-m-d');


                $jamAbsen =
                    $eventTime->format('Y-m-d H:i:s');


                /*
                |--------------------------------------------------------------------------
                | TAMPILKAN
                |--------------------------------------------------------------------------
                */

                $this->line(
                    'LOG | '
                    . 'NIP ' . $nip
                    . ' | '
                    . $jamAbsen
                    . ' | '
                    . 'minor='
                    . ($log['minor'] ?? '-')
                );


                /*
                |--------------------------------------------------------------------------
                | CARI ABSENSI
                |--------------------------------------------------------------------------
                */

                $attendance = Absensi::where(
                    'nip',
                    $nip
                )
                ->whereDate(
                    'clock_in_time',
                    $tanggalLog
                )
                ->first();


                /*
                |--------------------------------------------------------------------------
                | CLOCK-IN BARU
                |--------------------------------------------------------------------------
                */

                if (!$attendance) {

                    $jamHanya =
                        $eventTime->format('H:i');


                    $statusAwal =
                        $jamHanya > '08:00'
                        ? 'Terlambat'
                        : 'Tepat Waktu';


                    Absensi::create([
                        'nip' =>
                            $nip,

                        'clock_in_time' =>
                            $jamAbsen,

                        'clock_out_time' =>
                            null,

                        'status' =>
                            $statusAwal
                            . ' | Belum Keluar',
                    ]);


                    $this->info(
                        'INSERT CLOCK-IN | '
                        . 'NIP '
                        . $nip
                        . ' | '
                        . $jamAbsen
                    );


                    $countNew++;

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | CLOCK-IN SUDAH ADA
                |--------------------------------------------------------------------------
                */

                $clockIn = Carbon::parse(
                    $attendance->clock_in_time,
                    'Asia/Jakarta'
                );


                /*
                |--------------------------------------------------------------------------
                | EVENT LEBIH BARU
                |--------------------------------------------------------------------------
                */

                if ($eventTime->gt($clockIn)) {

                    /*
                    |--------------------------------------------------------------------------
                    | BELUM ADA CLOCK-OUT
                    |--------------------------------------------------------------------------
                    */

                    if (
                        empty(
                            $attendance->clock_out_time
                        )
                    ) {

                        $currentStatus =
                            $attendance->status
                            ?? 'Tepat Waktu';


                        $cleanStatus =
                            trim(
                                explode(
                                    '|',
                                    $currentStatus
                                )[0]
                            );


                        $attendance->update([
                            'clock_out_time' =>
                                $jamAbsen,

                            'status' =>
                                $cleanStatus,
                        ]);


                        $this->comment(
                            'UPDATE CLOCK-OUT | '
                            . 'NIP '
                            . $nip
                            . ' | '
                            . $jamAbsen
                        );


                        $countUpdate++;

                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CLOCK-OUT SUDAH ADA
                    |--------------------------------------------------------------------------
                    */

                    $clockOut = Carbon::parse(
                        $attendance->clock_out_time,
                        'Asia/Jakarta'
                    );


                    if ($eventTime->gt($clockOut)) {

                        $attendance->update([
                            'clock_out_time' =>
                                $jamAbsen,
                        ]);


                        $this->comment(
                            'UPDATE CLOCK-OUT TERBARU | '
                            . 'NIP '
                            . $nip
                            . ' | '
                            . $jamAbsen
                        );


                        $countUpdate++;

                        continue;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | SKIP
                |--------------------------------------------------------------------------
                */

                $this->warn(
                    'SKIP | '
                    . 'NIP '
                    . $nip
                    . ' | '
                    . 'Data duplikat / lebih lama.'
                );


                $countSkip++;
            }


            /*
            |--------------------------------------------------------------------------
            | SUMMARY
            |--------------------------------------------------------------------------
            */

            $this->newLine();

            $this->line(
                '=============================================='
            );

            $this->info(
                '       SINKRONISASI SELESAI'
            );

            $this->line(
                '=============================================='
            );

            $this->line(
                'Total log       : '
                . count($allLogs)
            );

            $this->line(
                'Clock-in baru   : '
                . $countNew
            );

            $this->line(
                'Clock-out baru  : '
                . $countUpdate
            );

            $this->line(
                'Data dilewati   : '
                . $countSkip
            );

            $this->line(
                '=============================================='
            );


            return self::SUCCESS;


        } catch (GuzzleException $e) {

            $this->error(
                'Guzzle HTTP Error: '
                . $e->getMessage()
            );


            Log::error(
                'Fingerprint Guzzle Error',
                [
                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );


            return self::FAILURE;


        } catch (\Throwable $e) {

            $this->error(
                'Terjadi kesalahan: '
                . $e->getMessage()
            );


            Log::error(
                'Fingerprint Command Crash',
                [
                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );


            return self::FAILURE;
        }
    }
}
