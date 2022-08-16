<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    /**
     * @test
     */
    public function itUploadsAndStoresAnImageForAnOffice()
    {
        Storage::fake();
        $user = User::factory()->create();

        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->post("/api/offices/{$office->id}/images", [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);
        
        $response->assertStatus(201);
        Storage::assertExists($response->json('data.path'));
    }

    /**
     * @test
     */
    public function itDeletesAnImageForAnOffice()
    {
        Storage::put('/office_image.jpg', 'empty');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");
        $response->assertOk();
        $this->assertModelMissing($image);

        Storage::assertMissing('office_image.jpg');
    }

    /**
     * @test
     */
    public function itDoesntDeleteTheOnlyImageForAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image' => 'Cannot delete the only image!']);
    }

    /**
     * @test
     */
    public function itDoesntDeleteTheImageForAnotherOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");
        $response->assertNotFound();
    }

    /**
     * @test
     */
    public function itDoesntDeleteTheFeaturedImageForAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");
        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'Cannot delete featured image!']);
    }
}
