<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
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

        $response = $this->get('/api/reservations');

        // dd($response->json());

        $response->assertStatus(200);

        $response->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image_id', $image->id);
    }
}
