<?php

namespace Tests\Feature;

use App\Models\GymSample;
use App\Models\User;
use App\Services\GymService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GymVisitorsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_poll_stores_one_sample_per_gym_in_local_time(): void
    {
        // 17:30 UTC on a Tuesday in winter is 18:30 in Zurich
        Carbon::setTestNow('2026-11-10 17:30:00');
        Http::fake(['www.fitnesspark.ch/*' => Http::response("42\n")]);

        $this->artisan('gyms:poll')->assertSuccessful();

        $this->assertSame(4, GymSample::count());
        $sample = GymSample::where('gym', 'sihlcity')->first();
        $this->assertSame([42, 2, 18], [$sample->visitors, $sample->weekday, $sample->hour]);

        Http::assertSent(fn ($request) => $request['action'] === 'single_park_update_visitors'
            && $request['park_id'] == 704
            && $request['location_name'] === 'FP_Sihlcity');
    }

    public function test_poll_ignores_answers_that_are_not_a_number(): void
    {
        Http::fake([
            '*FP_Sihlcity*' => Http::response('<html>Just a moment…</html>'),
            '*FP_Puls5*' => Http::response('', 500),
            '*' => Http::response('17'),
        ]);

        $this->assertSame(2, app(GymService::class)->poll());
        $this->assertEqualsCanonicalizing(['stockerhof', 'stadelhofen'], GymSample::pluck('gym')->all());
    }

    public function test_current_reuses_fresh_counts_and_refreshes_stale_ones(): void
    {
        Carbon::setTestNow('2026-11-10 17:30:00');
        Http::fake(['www.fitnesspark.ch/*' => Http::response('60')]);
        $gyms = app(GymService::class);

        $this->assertSame(60, $gyms->current()->firstWhere('key', 'puls5')['visitors']);
        Http::assertSentCount(4);

        Carbon::setTestNow('2026-11-10 17:35:00');
        $this->assertSame(5, $gyms->current()->firstWhere('key', 'puls5')['minutes_ago']);
        Http::assertSentCount(4);

        Carbon::setTestNow('2026-11-10 18:00:00');
        $gyms->current();
        Http::assertSentCount(8);
    }

    public function test_closed_gyms_are_not_asked(): void
    {
        Http::fake(['www.fitnesspark.ch/*' => Http::response('12')]);
        $gyms = app(GymService::class);

        // Tuesday 05:10 UTC is 06:10 in Zurich: Sihlcity opens at 06:30, the others at 06:00
        Carbon::setTestNow('2026-11-10 05:10:00');
        $this->assertSame(3, $gyms->poll());
        $sihlcity = $gyms->current()->firstWhere('key', 'sihlcity');
        $this->assertSame([false, '06:30', null], [$sihlcity['open'], $sihlcity['opens'], $sihlcity['visitors']]);

        // Saturday 20:30 in Zurich: everything is closed, nothing is requested or stored
        Carbon::setTestNow('2026-11-14 19:30:00');
        Http::fake(['www.fitnesspark.ch/*' => Http::response('99')]);
        $before = GymSample::count();
        $current = $gyms->current();
        $this->assertSame($before, GymSample::count());
        $this->assertSame(['tomorrow 09:00', 'tomorrow 09:00', 'tomorrow 08:00', 'tomorrow 09:00'], $current->pluck('opens')->all());
        $this->assertSame([null, null, null, null], $current->pluck('visitors')->all());
    }

    public function test_gyms_page_shows_typical_visitors_per_weekday_and_hour(): void
    {
        Carbon::setTestNow('2026-11-10 17:30:00');
        foreach ([40, 60] as $i => $visitors) {
            GymSample::create([
                'gym' => 'sihlcity', 'visitors' => $visitors, 'weekday' => 2, 'hour' => 18,
                'recorded_at' => now()->subWeeks($i),
            ]);
        }
        Http::fake(['www.fitnesspark.ch/*' => Http::response('55')]);

        $this->actingAs(User::factory()->create())
            ->get('/gyms')
            ->assertInertia(fn ($page) => $page
                ->where('statistics.sihlcity.typical.2.18', 50)
                ->where('statistics.sihlcity.days', 2)
                ->where('statistics.puls5.days', 0)
                ->where('now', ['weekday' => 2, 'hour' => 18])
                ->missing('gyms')
                ->loadDeferredProps(fn ($reload) => $reload->where('gyms.0.visitors', 55)));
    }
}
