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
