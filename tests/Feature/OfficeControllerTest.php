<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListsOfficesWithPagination()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(["meta"]);
        $response->assertJsonStructure(["links"]);
        $this->assertNotNull($response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itOnlyListsOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();
        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => Office::APPPROVAL_PENDING]);
        $response = $this->get('/api/offices');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function itFiltersByUserId()
    {
        Office::factory(3)->create();

        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $response = $this->get('/api/offices?user_id='.$host->id);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get('/api/offices?visitor_id='.$user->id);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.png']);

        $response = $this->get('/api/offices');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }

    /**
     * @test
     */
    public function itReturnsTheNumberOfActiveReservations()
    {
        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
    }

    /**
     * @test
     */
    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        $office1 = Office::factory()->create([
            'lat' => '39.74941243566344',
            'lng' => '-8.807528387452857',
            'title' => 'Leiria'
        ]);

        $office2 = Office::factory()->create([
            'lat' => '39.087852929110774',
            'lng' => '-9.256226312659056',
            'title' => 'Torres Vadras'
        ]);

        $response = $this->get('/api/offices?lat=38.733172738449944&lng=-9.159315739200155');

        $response->assertOk();
        $this->assertEquals('Torres Vadras', $response->json('data')[0]['title']);
        $this->assertEquals('Leiria', $response->json('data')[1]['title']);

        $response = $this->get('/api/offices');
        
        $response->assertOk();
        $this->assertEquals('Leiria', $response->json('data')[0]['title']);
        $this->assertEquals('Torres Vadras', $response->json('data')[1]['title']);
    }

    /**
     * @test
     */
    public function itShowsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.png']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices/'.$office->id);
        
        $response->assertOk();
        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }

    /**
     * @test
     */
    public function itCreatesAnOffice()
    {
        $admin = User::factory()->create(['name' => 'Midstacks']);
        Notification::fake();

        $user = User::factory()->createQuietly();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/offices', [
            'title' => 'Office in Durban',
            'description' => 'super nice',
            'lat' => -29.8605202758709,
            'lng' => 31.024853069755064,
            'address_line1' => 'downtown miles',
            'price_per_day' => 10_000,
            'monthly_discount' => 5,
            'tags' => [
                $tag1->id, $tag2->id
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Office in Durban')
            ->assertJsonPath('data.approval_status', Office::APPPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseHas('offices', [
            'title' => 'Office in Durban'
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }


    /**
     * @test
     */
    public function itDoesntAllowToCreateOfficeIfScopeIsNotProvided()
    {
        $user = User::factory()->createQuietly();

        $token = $user->createToken('test', []);

        $response = $this->postJson('/api/offices', [], [
            'Authorization' => 'Bearer ' .$token->plainTextToken
        ]);

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function itUpdatesAnOffice()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(3)->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $anotherTag = Tag::factory()->create();

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Office in Durban with sea view',
            'tags' => [$tags[0]->id, $anotherTag->id]
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Office in Durban with sea view')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $anotherTag->id)
            ->assertJsonCount(2, 'data.tags');
    }

    /**
     * @test
     */
    public function itDoesntUpdatesAnOfficeThatBelongsToAnotherUser()
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $office = Office::factory()->for($anotherUser)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Office in Durban with sea view',
        ]);

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function itMarksTheOfficeAsPendingIfDirty()
    {
        $admin = User::factory()->create(['name' => 'Midstacks']);
        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'lat' => -29.9605902758709,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::APPPROVAL_PENDING,
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
     * @test
     */
    public function itDoesntAllowToUpdateOfficeIfScopeIsNotProvided()
    {
        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->create();

        $token = $user->createToken('test', []);

        $response = $this->putJson('/api/offices/'.$office->id, [], [
            'Authorization' => 'Bearer ' .$token->plainTextToken
        ]);

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    public function itDeletesAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user); 

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertOk();
        $this->assertSoftDeleted($office);
    }

    /**
     * @test
     */
    public function itCanotDeletesAnOfficeWithActiveReservation()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user); 

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
