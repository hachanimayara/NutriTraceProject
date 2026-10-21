<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\HarvestPlan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProducerWeatherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00', 'Africa/Tunis'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function farm(User $owner, array $attributes = []): Farm
    {
        return Farm::create(array_merge([
            'user_id' => $owner->id, 'name' => 'Ferme Test', 'reference' => 'FARM-'.uniqid(),
            'location' => 'Tunis', 'country' => 'Tunisie', 'latitude' => 36.8065,
            'longitude' => 10.1815, 'status' => 'active',
        ], $attributes));
    }

    private function payload(): array
    {
        $times = [];
        for ($hour = 0; $hour < 48; $hour++) $times[] = Carbon::now('Africa/Tunis')->startOfHour()->addHours($hour)->format('Y-m-d\\TH:i');
        $days = [];
        for ($day = 0; $day < 7; $day++) $days[] = Carbon::now('Africa/Tunis')->addDays($day)->toDateString();

        return [
            'latitude' => 36.8, 'longitude' => 10.18, 'timezone' => 'Africa/Tunis', 'timezone_abbreviation' => 'GMT+1',
            'current' => ['time' => Carbon::now('Africa/Tunis')->format('Y-m-d\\TH:i'), 'temperature_2m' => 32, 'relative_humidity_2m' => 86, 'apparent_temperature' => 34, 'precipitation' => 0, 'rain' => 0, 'weather_code' => 95, 'wind_speed_10m' => 38, 'wind_direction_10m' => 90, 'wind_gusts_10m' => 72],
            'hourly' => ['time' => $times, 'temperature_2m' => array_fill(0, 48, 33), 'relative_humidity_2m' => array_fill(0, 48, 86), 'precipitation_probability' => array_fill(0, 48, 80), 'precipitation' => array_fill(0, 48, 1), 'rain' => array_fill(0, 48, 1), 'weather_code' => array_fill(0, 48, 95), 'wind_speed_10m' => array_fill(0, 48, 38), 'wind_gusts_10m' => array_fill(0, 48, 72), 'et0_fao_evapotranspiration' => array_fill(0, 48, 0.3)],
            'daily' => ['time' => $days, 'weather_code' => array_fill(0, 7, 95), 'temperature_2m_max' => array_fill(0, 7, 38), 'temperature_2m_min' => array_fill(0, 7, 0), 'precipitation_sum' => array_fill(0, 7, 24), 'precipitation_probability_max' => array_fill(0, 7, 80), 'wind_speed_10m_max' => array_fill(0, 7, 38), 'wind_gusts_10m_max' => array_fill(0, 7, 72), 'et0_fao_evapotranspiration' => array_fill(0, 7, 6)],
        ];
    }

    public function test_producer_weather_dashboard_lists_only_their_own_farms(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $other = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->farm($producer, ['name' => 'Ma ferme']);
        $this->farm($other, ['name' => 'Ferme privée']);

        $this->actingAs($producer)->get(route('producer.weather.index'))->assertOk()->assertSee('Ma ferme')->assertDontSee('Ferme privée');
    }

    public function test_forecast_uses_open_meteo_timezone_and_returns_advice_and_harvest_impacts(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload(), 200)]);
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = $this->farm($producer);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Tomates', 'reference' => 'PROD-'.uniqid(), 'category' => 'Légumes', 'unit' => 'kg', 'status' => 'published']);
        $plan = HarvestPlan::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-WEATHER', 'starts_at' => today('Africa/Tunis'), 'ends_at' => today('Africa/Tunis')->addDay(), 'estimated_quantity' => 10, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 1]);

        $response = $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id]))->assertOk()
            ->assertJsonPath('status', 'ok')->assertJsonPath('forecast.timezone', 'Africa/Tunis')
            ->assertJsonPath('forecast.source', 'Open-Meteo')->assertJsonCount(7, 'forecast.daily.time')
            ->assertJsonFragment(['type' => 'storm'])->assertJsonFragment(['type' => 'heat'])
            ->assertJsonFragment(['type' => 'frost'])->assertJsonFragment(['type' => 'et0'])
            ->assertJsonFragment(['reference' => $plan->reference]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com') && $request['timezone'] === 'Africa/Tunis' && (float) $request['latitude'] === 36.8065 && (int) $request['forecast_days'] === 7);
        $response->assertJsonPath('forecast.current.temperature_2m', 32);
    }

    public function test_calendar_harvest_is_shown_for_forecast_dates_even_without_weather_risk(): void
    {
        $forecast = $this->payload();
        $forecast['current'] = array_merge($forecast['current'], ['temperature_2m' => 22, 'apparent_temperature' => 22, 'relative_humidity_2m' => 50, 'weather_code' => 0, 'wind_speed_10m' => 10, 'wind_gusts_10m' => 15]);
        $forecast['hourly']['precipitation'] = array_fill(0, 48, 0);
        $forecast['hourly']['relative_humidity_2m'] = array_fill(0, 48, 50);
        $forecast['hourly']['precipitation_probability'] = array_fill(0, 48, 0);
        $forecast['hourly']['wind_speed_10m'] = array_fill(0, 48, 10);
        $forecast['hourly']['wind_gusts_10m'] = array_fill(0, 48, 15);
        $forecast['daily']['weather_code'] = array_fill(0, 7, 0);
        $forecast['daily']['temperature_2m_max'] = array_fill(0, 7, 25);
        $forecast['daily']['temperature_2m_min'] = array_fill(0, 7, 15);
        $forecast['daily']['precipitation_sum'] = array_fill(0, 7, 0);
        $forecast['daily']['precipitation_probability_max'] = array_fill(0, 7, 0);
        $forecast['daily']['wind_speed_10m_max'] = array_fill(0, 7, 10);
        $forecast['daily']['wind_gusts_10m_max'] = array_fill(0, 7, 15);
        $forecast['daily']['et0_fao_evapotranspiration'] = array_fill(0, 7, 2);
        Http::fake(['api.open-meteo.com/*' => Http::response($forecast, 200)]);

        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = $this->farm($producer);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Olives', 'reference' => 'PROD-'.uniqid(), 'category' => 'Fruits', 'unit' => 'kg', 'status' => 'published']);
        $plan = HarvestPlan::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-NO-RISK', 'starts_at' => today('Africa/Tunis')->addDay(), 'ends_at' => today('Africa/Tunis')->addDay(), 'estimated_quantity' => 25, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 1]);

        $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id]))
            ->assertOk()->assertJsonPath('harvest_impacts.0.reference', $plan->reference)
            ->assertJsonPath('harvest_impacts.0.product', 'Olives')->assertJsonPath('harvest_impacts.0.estimated_quantity', '25.000')
            ->assertJsonCount(0, 'harvest_impacts.0.risks');
    }

    public function test_missing_coordinates_are_reported_without_calling_the_api(): void
    {
        Http::fake();
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = $this->farm($producer, ['latitude' => null, 'longitude' => null]);

        $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id]))
            ->assertStatus(422)->assertJsonPath('status', 'missing_coordinates')->assertJsonPath('edit_url', route('workspace.resources.edit', ['farms', $farm]));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'geocoding-api.open-meteo.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com/v1/forecast'));
    }

    public function test_location_name_provides_labeled_approximate_weather_when_gps_is_missing(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 34.7406, 'longitude' => 10.7603, 'name' => 'Sfax', 'admin1' => 'Sfax', 'country' => 'Tunisie']]], 200),
            'api.open-meteo.com/*' => Http::response($this->payload(), 200),
        ]);
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = $this->farm($producer, ['location' => 'Sfax', 'latitude' => null, 'longitude' => null]);

        $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id]))
            ->assertOk()->assertJsonPath('location_accuracy', 'approximate')->assertJsonPath('matched_location', 'Sfax, Tunisie')
            ->assertJsonPath('forecast.current.temperature_2m', 32);
        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'latitude' => null, 'longitude' => null]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com/v1/forecast') && (float) $request['latitude'] === 34.7406);
    }

    public function test_producer_cannot_request_another_producers_farm_forecast(): void
    {
        Http::fake();
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $other = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = $this->farm($other);

        $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id]))->assertUnprocessable()->assertJsonValidationErrors('farm_id');
        Http::assertNothingSent();
    }

    public function test_api_errors_and_incomplete_payloads_return_clear_unavailable_responses(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = $this->farm($producer);
        Http::fake(['api.open-meteo.com/*' => Http::response(['error' => true], 503)]);
        $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id, 'refresh' => 1]))->assertStatus(503)->assertJsonPath('status', 'unavailable');

        Http::fake(['api.open-meteo.com/*' => Http::response(['current' => [], 'hourly' => [], 'daily' => []], 200)]);
        $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id, 'refresh' => 1]))->assertStatus(503)->assertJsonPath('status', 'unavailable');
    }

    public function test_forecasts_are_cached_and_manual_refresh_bypasses_cache(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload(), 200)]);
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = $this->farm($producer);

        $this->actingAs($producer)->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id]))->assertOk();
        $this->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id]))->assertOk();
        Http::assertSentCount(1);
        $this->getJson(route('producer.weather.forecast', ['farm_id' => $farm->id, 'refresh' => 1]))->assertOk();
        Http::assertSentCount(2);
    }
}
