<?php

namespace App\Http\Controllers;

use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ServerMetricsController extends Controller
{
    #[OA\Get(
        path: '/metrics/current',
        summary: 'Get current server metrics',
        security: [['bearerAuth' => []]],
        tags: ['Server Metrics'],
        responses: [
            new OA\Response(response: 200, description: 'Current metrics', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ServerMetrics')],
            )),
        ],
    )]
    public function current(): JsonResponse
    {
        $ramData = file_get_contents("/proc/meminfo");
        preg_match("/MemTotal:\s+(\d+) kB/", $ramData, $total);
        preg_match("/MemAvailable:\s+(\d+) kB/", $ramData, $available);

        $ramTotal = (int)$total[1];
        $ramFree = (int)$available[1];
        $ramUsed = $ramTotal - $ramFree;
        $ramUsagePercent = ($ramUsed / $ramTotal) * 100;

        $diskTotal = disk_total_space("/");
        $diskFree = disk_free_space("/");
        $diskUsed = $diskTotal - $diskFree;
        $diskUsagePercent = ($diskUsed / $diskTotal) * 100;

        return new JsonResponse(['data' => [
            'cpu_usage_percent' => $this->getCpuUsage(),
            'ram_total' => $ramTotal,
            'ram_free' => $ramFree,
            'ram_used' => $ramUsed,
            'ram_usage_percent' => $ramUsagePercent,
            'disk_total' => $diskTotal,
            'disk_free' => $diskFree,
            'disk_used' => $diskUsed,
            'disk_usage_percent' => $diskUsagePercent,
        ]]);
    }

    private function getCpuUsage(): int|float
    {
        $cpuData = file_get_contents('/proc/stat');
        $lines = explode("\n", $cpuData);
        $cpuLine = preg_split('/\s+/', $lines[0]);
        $user = (int)$cpuLine[1];
        $nice = (int)$cpuLine[2];
        $system = (int)$cpuLine[3];
        $idle = (int)$cpuLine[4];
        $total = $user + $nice + $system + $idle;
        $idleTime = $idle;
        $cpuUsage = (($total - $idleTime) / $total) * 100;
        return $cpuUsage;
    }

    #[OA\Get(
        path: '/metrics/last-hour-averages',
        summary: 'Get last hour metric averages',
        security: [['bearerAuth' => []]],
        tags: ['Server Metrics'],
        responses: [
            new OA\Response(response: 200, description: 'Last hour averages', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'avg_cpu_percent', type: 'number', format: 'float'),
                    new OA\Property(property: 'avg_ram_percent', type: 'number', format: 'float'),
                ])],
            )),
        ],
    )]
    public function lastHourAverages(): JsonResponse
    {
        $query = <<<SQL
SELECT
  AVG(cpu_percent) AS avg_cpu_percent,
  AVG(ram_percent) AS avg_ram_percent
FROM server_metrics
WHERE timestamp >= NOW() - INTERVAL 1 HOUR;
SQL;
        /** 
         * @var non-empty-array<object{
         *   avg_cpu_percent: float,
         *   avg_ram_percent: float,
         * }>
         */
        $results = DB::select($query);

        return new JsonResponse(['data' => [
            'avg_cpu_percent' => $results[0]->avg_cpu_percent,
            'avg_ram_percent' => $results[0]->avg_ram_percent,
        ]]);
    }

    #[OA\Get(
        path: '/metrics/last-5-minutes',
        summary: 'Get metrics for the last 5 minutes',
        security: [['bearerAuth' => []]],
        tags: ['Server Metrics'],
        responses: [
            new OA\Response(response: 200, description: 'Last 5 minutes metrics', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ServerMetrics'))],
            )),
        ],
    )]
    public function last5Minutes(): JsonResponse
    {
        $bucketSeconds = 5;
        $end = $this->floorToNearestSeconds(Carbon::now('UTC')->subSeconds($bucketSeconds), $bucketSeconds);
        $start = $end->copy()->subSeconds(295);
        return $this->buildMetricsResponse($start, $end, $bucketSeconds);
    }

    #[OA\Get(
        path: '/metrics/last-hour',
        summary: 'Get metrics for the last hour',
        security: [['bearerAuth' => []]],
        tags: ['Server Metrics'],
        responses: [
            new OA\Response(response: 200, description: 'Last hour metrics', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ServerMetrics'))],
            )),
        ],
    )]
    public function lastHour(): JsonResponse
    {
        $bucketSeconds = 60;
        $end = $this->floorToNearestSeconds(Carbon::now('UTC')->subSeconds($bucketSeconds), $bucketSeconds);
        $start = $end->copy()->subMinutes(59);
        return $this->buildMetricsResponse($start, $end, $bucketSeconds);
    }

    #[OA\Get(
        path: '/metrics/last-12-hours',
        summary: 'Get metrics for the last 12 hours',
        security: [['bearerAuth' => []]],
        tags: ['Server Metrics'],
        responses: [
            new OA\Response(response: 200, description: 'Last 12 hours metrics', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ServerMetrics'))],
            )),
        ],
    )]
    public function last12Hours(): JsonResponse
    {
        $bucketSeconds = 12 * 60;
        $end = $this->floorToNearestSeconds(Carbon::now('UTC')->subSeconds($bucketSeconds), $bucketSeconds);
        $start = $end->copy()->subSeconds(59 * $bucketSeconds);
        return $this->buildMetricsResponse($start, $end, $bucketSeconds);
    }

    private function floorToNearestSeconds(Carbon $dt, int $seconds): Carbon
    {
        $timestamp = (int)$dt->timestamp;
        $floored = $timestamp - ($timestamp % $seconds);
        return Carbon::createFromTimestampUTC($floored);
    }

    private function buildMetricsResponse(Carbon $start, Carbon $end, int $bucketSeconds): JsonResponse
    {
        $query = <<<SQL
SELECT 
  FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(`timestamp`) / ?) * ?) AS bucket_time,
  ROUND(AVG(cpu_percent), 2) AS cpu_percent,
  ROUND(AVG(cpu_load_avg_1), 2) AS cpu_load_avg_1,
  ROUND(AVG(cpu_load_avg_5), 2) AS cpu_load_avg_5,
  ROUND(AVG(cpu_load_avg_15), 2) AS cpu_load_avg_15,
  ROUND(AVG(ram_percent), 2) AS ram_percent,
  ROUND(AVG(swap_percent), 2) AS swap_percent,
  ROUND(AVG(disk_read_bps), 2) AS disk_read_bps,
  ROUND(AVG(disk_write_bps), 2) AS disk_write_bps,
  ROUND(AVG(disk_read_iops), 2) AS disk_read_iops,
  ROUND(AVG(disk_write_iops), 2) AS disk_write_iops,
  ROUND(AVG(net_in_bps), 2) AS net_in_bps,
  ROUND(AVG(net_out_bps), 2) AS net_out_bps,
  ROUND(AVG(net_in_pps), 2) AS net_in_pps,
  ROUND(AVG(net_out_pps), 2) AS net_out_pps
FROM server_metrics
WHERE `timestamp` >= ?
GROUP BY bucket_time
SQL;

        /** 
         * @var array<object{
         *   bucket_time: string,
         *   ...
         * }>
         */
        $results = DB::select($query, [
            $bucketSeconds,
            $bucketSeconds,
            $start->format('Y-m-d H:i:s'),
        ]);

        $dataByPeriod = [];
        foreach ($results as $row) {
            $dataByPeriod[$row->bucket_time] = [
                "cpu_percent" => (float)$row->cpu_percent,
                "cpu_load_avg_1" => (float)$row->cpu_load_avg_1,
                "cpu_load_avg_5" => (float)$row->cpu_load_avg_5,
                "cpu_load_avg_15" => (float)$row->cpu_load_avg_15,
                "ram_percent" => (float)$row->ram_percent,
                "swap_percent" => (float)$row->swap_percent,
                "disk_read_bps" => (float)$row->disk_read_bps,
                "disk_write_bps" => (float)$row->disk_write_bps,
                "disk_read_iops" => (float)$row->disk_read_iops,
                "disk_write_iops" => (float)$row->disk_write_iops,
                "net_in_bps" => (float)$row->net_in_bps,
                "net_out_bps" => (float)$row->net_out_bps,
                "net_in_pps" => (float)$row->net_in_pps,
                "net_out_pps" => (float)$row->net_out_pps,
            ];
        }

        $format = 'Y-m-d H:i:s';
        $data = [];
        $period = CarbonPeriod::create($start, "{$bucketSeconds} seconds", $end);

        foreach ($period as $dt) {
            /** @var Carbon $dt */
            $key = $dt->format($format);
            $periodData = ['period' => $key];
            if (array_key_exists($key, $dataByPeriod)) {
                foreach($dataByPeriod[$key] as $metric => $value) {
                    $periodData[$metric] = $value;
                }
            }
            $data[] = $periodData;
        }

        return new JsonResponse(['data' => $data]);
    }
}
