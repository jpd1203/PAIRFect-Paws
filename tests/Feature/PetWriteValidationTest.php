<?php

namespace Tests\Feature;

use App\Enums\AvailabilityStatus;
use App\Enums\Role;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PetWriteValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pet_writes_reject_invalid_status_and_oversized_or_disguised_images(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create(['role' => Role::Administrator, 'email_verified_at' => now()]);
        $this->actingAs($staff);
        $valid = ['name' => 'Milo', 'species' => 'Dog', 'status' => AvailabilityStatus::Assessing->value];

        $this->post(route('admin.animals.store'), [...$valid, 'status' => 'Anything'])
            ->assertSessionHasErrors('status');
        $this->post(route('admin.animals.store'), [...$valid, 'photo' => UploadedFile::fake()->image('large.jpg')->size(10241)])
            ->assertSessionHasErrors('photo');
        $this->post(route('admin.animals.store'), [...$valid, 'photo' => UploadedFile::fake()->create('fake.jpg', 1, 'text/plain')])
            ->assertSessionHasErrors('photo');
        $this->assertSame(0, Pet::count());

        $this->post(route('admin.animals.store'), [...$valid, 'photo' => UploadedFile::fake()->image('valid.jpg')->size(10240)])
            ->assertSessionHasNoErrors();
        $pet = Pet::firstOrFail();
        Storage::disk('public')->assertExists($pet->photo_path);

        $this->put(route('admin.animals.update', $pet), [...$valid, 'version' => $pet->version, 'status' => 'Forged'])
            ->assertSessionHasErrors('status');
        $this->put(route('admin.animals.update', $pet), [...$valid, 'version' => $pet->version, 'photo' => UploadedFile::fake()->image('large-update.png')->size(10241)])
            ->assertSessionHasErrors('photo');
        $this->assertSame(AvailabilityStatus::Assessing, $pet->fresh()->availability_status);
    }
}
