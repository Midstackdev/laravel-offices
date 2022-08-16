<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    /**
     * @test
     */
    public function itListsReservationsThatBelongsToTheUser()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create();
        $image = $reservation->office->images()->create([
            'path' => 'image.png'
        ]);
        $reservation->office()->update(['featured_image_id' => $image->id]);

        Reservation::factory(2)->for($user)->create();
        Reservation::factory(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        // dd($response->json());

        $response->assertStatus(200);

        $response->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image_id', $image->id);
    }

    /**
     * @test
     */
    public function itListsReservationsFilteredByDateRange()
    {
        $user = User::factory()->create();

        $fromDate = '2022-03-03';
        $toDate = '2022-04-04';

        // Within the date range
        $reservation1 = Reservation::factory()->for($user)->create([
            'start_date' => '2022-03-01',
            'end_date' => '2022-03-15',
        ]);


        $reservation2 = Reservation::factory()->for($user)->create([
            'start_date' => '2022-03-25',
            'end_date' => '2022-04-15',
        ]);

        $reservation3 = Reservation::factory()->for($user)->create([
            'start_date' => '2022-03-25',
            'end_date' => '2022-03-29',
        ]);

        Reservation::factory()->create([
            'start_date' => '2022-03-25',
            'end_date' => '2022-03-29',
        ]);

        // Outside the date range
        Reservation::factory()->for($user)->create([
            'start_date' => '2022-02-25',
            'end_date' => '2022-03-01',
        ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2022-05-25',
            'end_date' => '2022-05-01',
        ]);

        $this->actingAs($user);
        // DB::enableQueryLog();

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]));

        // dd(DB::getQueryLog());

        // dd($response->json('data'));

        $response->assertStatus(200);

        $response->assertJsonCount(3, 'data');
        $this->assertEquals([$reservation1->id, $reservation2->id, $reservation3->id], collect($response->json('data'))->pluck('id')->toArray());
    }

    /**
     * @test
     */
    public function itListsReservationsFilteredByStatus()
    {
        $user = User::factory()->create();

        $reservation1 = Reservation::factory()->for($user)->create();

        $reservation2 = Reservation::factory()->for($user)->cancelled()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'status' => Reservation::STATUS_ACTIVE,
        ]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id);
    }

    /**
     * @test
     */
    public function itListsReservationsFilteredByOfficeId()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $reservation1 = Reservation::factory()->for($office)->for($user)->create();

        $reservation2 = Reservation::factory()->for($user)->cancelled()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'office_id' => $office->id,
        ]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id);
    }
}
