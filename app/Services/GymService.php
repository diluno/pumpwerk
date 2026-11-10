<?php

namespace App\Services;

use App\Models\GymSample;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class GymService
{
    /**
     * Ask every gym for its current visitor count and store the answers.
     * Gyms that fail or answer with something that is not a number are skipped.
     *
     * @return int number of samples stored
     */
    public function poll(): int
    {
        $locations = config('gyms.locations');
        $now = now();
        $local = $now->copy()->setTimezone(config('gyms.timezone'));

        try {
            $responses = Http::pool(fn (Pool $pool) => collect($locations)->map(
                fn ($location, $key) => $pool->as($key)
                    ->withUserAgent(config('gyms.user_agent'))
                    ->timeout(6)
                    ->get(config('gyms.endpoint'), [
                        'action' => 'single_park_update_visitors',
                        'park_id' => $location['park_id'],
                        'location_id' => $location['location_id'],
                        'location_name' => $location['location_name'],
                    ])
            )->all());
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        $stored = 0;
        foreach ($responses as $key => $response) {
            $visitors = $response instanceof Response && $response->successful()
                ? trim($response->body())
                : null;

            // The endpoint answers with a bare number; anything else is an error page.
            if ($visitors === null || ! ctype_digit($visitors) || (int) $visitors > 5000) {
                continue;
            }

            GymSample::create([
                'gym' => $key,
                'visitors' => (int) $visitors,
                'weekday' => $local->dayOfWeekIso,
                'hour' => $local->hour,
                'recorded_at' => $now,
            ]);
            $stored++;
        }

        return $stored;
    }

    /**
     * The latest count per gym, refreshed first if it has gone stale.
     *
     * @return Collection<int, array{key: string, name: string, visitors: ?int, minutes_ago: ?int}>
     */
    public function current(): Collection
    {
        $latest = $this->latest();
        $freshFrom = now()->subMinutes(config('gyms.fresh_minutes'));

        $stale = collect(config('gyms.locations'))->keys()
            ->contains(fn ($key) => ! isset($latest[$key]) || $latest[$key]->recorded_at->lt($freshFrom));
        if ($stale) {
            $this->poll();
            $latest = $this->latest();
        }

        return collect(config('gyms.locations'))->map(fn ($location, $key) => [
            'key' => $key,
            'name' => $location['name'],
            'visitors' => $latest[$key]->visitors ?? null,
            'minutes_ago' => isset($latest[$key]) ? (int) $latest[$key]->recorded_at->diffInMinutes(now()) : null,
        ])->values();
    }

    /**
     * Typical visitors per gym, weekday and hour over the last weeks.
     *
     * @return array<string, array{days: int, typical: array<int, array<int, int>>}> typical[weekday][hour]
     */
    public function statistics(): array
    {
        $since = now()->subWeeks(config('gyms.stats_weeks'));

        $averages = GymSample::query()
            ->where('recorded_at', '>=', $since)
            ->groupBy('gym', 'weekday', 'hour')
            ->selectRaw('gym, weekday, hour, AVG(visitors) as average')
            ->get()
            ->groupBy('gym');

        // How many different days have been sampled, to say how solid the picture is
        $days = GymSample::query()
            ->where('recorded_at', '>=', $since)
            ->selectRaw('gym, COUNT(DISTINCT DATE(recorded_at)) as days')
            ->groupBy('gym')
            ->pluck('days', 'gym');

        return collect(config('gyms.locations'))->map(function ($location, $key) use ($averages, $days) {
            $typical = [];
            foreach ($averages[$key] ?? [] as $row) {
                $typical[$row->weekday][$row->hour] = (int) round($row->average);
            }

            return ['days' => (int) ($days[$key] ?? 0), 'typical' => (object) $typical];
        })->all();
    }

    public function prune(): int
    {
        return GymSample::where('recorded_at', '<', now()->subDays(config('gyms.keep_days')))->delete();
    }

    /**
     * The gyms' local time right now.
     */
    public function localNow(): Carbon
    {
        return now()->setTimezone(config('gyms.timezone'));
    }

    /**
     * @return Collection<string, GymSample> newest sample per gym
     */
    private function latest(): Collection
    {
        return GymSample::query()
            ->whereIn('id', GymSample::query()->selectRaw('MAX(id)')->groupBy('gym'))
            ->get()
            ->keyBy('gym');
    }
}
