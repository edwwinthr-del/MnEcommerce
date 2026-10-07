<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerResource\Pages\ManageBanners;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_upload_requires_admin_even_with_valid_signed_url(): void
    {
        $url = $this->signedUploadUrl();
        $this->postJson($url, ['files' => [UploadedFile::fake()->image('photo.png')]])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($url, [
            'files' => [UploadedFile::fake()->image('photo.png')],
        ])->assertForbidden();
    }

    public function test_admin_upload_requires_signature_and_rejects_executable_svg_and_oversized_files(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson(route('livewire.upload-file', absolute: false), [
            'files' => [UploadedFile::fake()->image('photo.png')],
        ])->assertUnauthorized();

        foreach ([
            UploadedFile::fake()->createWithContent('image.php', '<?php echo "blocked";'),
            UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('large.png')->size(5121),
        ] as $file) {
            $this->postJson($this->signedUploadUrl(), ['files' => [$file]])
                ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        }
    }

    public function test_admin_can_upload_raster_image_with_generated_storage_name(): void
    {
        Storage::fake('tmp-for-tests');
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson($this->signedUploadUrl(), ['files' => [UploadedFile::fake()->image('photo.png')]])
            ->assertOk()->assertJsonCount(1, 'paths');

        $this->assertNotSame('photo.png', $response->json('paths.0'));
        $images = array_filter(Storage::disk('tmp-for-tests')->allFiles('livewire-tmp'),
            fn (string $path): bool => ! str_ends_with($path, '.json'));
        $this->assertCount(1, $images);
    }

    public function test_banner_form_rejects_a_forged_existing_storage_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/another-record.png', 'not-this-record');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageBanners::class)
            ->callAction('create', data: [
                'title' => 'Forged banner',
                'image' => ['banners/another-record.png'],
                'position' => 'hero',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertHasActionErrors(['image']);

        $this->assertDatabaseCount('banners', 0);
    }

    private function signedUploadUrl(): string
    {
        return URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);
    }
}
