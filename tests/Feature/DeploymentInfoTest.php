<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\SystemCommands;
use App\Models\User;
use App\Support\DeploymentInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sunucudaki kodun sürümü.
 *
 * Bir kez yalnız public/build yüklendi, kod eski kaldı: site ikonsuz, teklif formu
 * çalışmaz hâle geldi ve terminal olmadığı için fark edilmedi. Panel bunu göstermeli.
 */
class DeploymentInfoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->dir = sys_get_temp_dir().'/deploy-test-'.uniqid();
        File::makeDirectory($this->dir.'/.git/refs/heads', 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function info(): DeploymentInfo
    {
        return new DeploymentInfo($this->dir.'/.git', $this->dir.'/manifest.json');
    }

    public function test_reads_the_commit_of_the_current_branch(): void
    {
        File::put($this->dir.'/.git/HEAD', "ref: refs/heads/main\n");
        File::put($this->dir.'/.git/refs/heads/main', str_repeat('a1', 20)."\n");

        $this->assertSame(str_repeat('a1', 20), $this->info()->commit());
        $this->assertSame('a1a1a1a', $this->info()->shortCommit());
    }

    public function test_reads_packed_refs_after_a_fetch(): void
    {
        File::put($this->dir.'/.git/HEAD', "ref: refs/heads/main\n");
        File::put($this->dir.'/.git/packed-refs', "# pack-refs with: peeled fully-peeled sorted\n".str_repeat('b2', 20)." refs/heads/main\n");

        $this->assertSame(str_repeat('b2', 20), $this->info()->commit());
    }

    public function test_detached_head_is_read_directly(): void
    {
        File::put($this->dir.'/.git/HEAD', str_repeat('c3', 20)."\n");

        $this->assertSame(str_repeat('c3', 20), $this->info()->commit());
    }

    public function test_missing_repository_is_not_an_error(): void
    {
        $info = new DeploymentInfo($this->dir.'/yok', $this->dir.'/yok.json');

        $this->assertNull($info->commit());
        $this->assertNull($info->codeUpdatedAt());
        $this->assertNull($info->buildUploadedAt());
    }

    public function test_page_warns_when_the_build_is_newer_than_the_code(): void
    {
        $this->app->instance(DeploymentInfo::class, new class extends DeploymentInfo
        {
            public function shortCommit(): ?string
            {
                return 'a16cc0b';
            }

            public function codeUpdatedAt(): ?Carbon
            {
                return now()->subHours(3);
            }

            public function buildUploadedAt(): ?Carbon
            {
                return now()->subMinutes(5);
            }
        });

        $this->actingAs($this->admin())->get(SystemCommands::getUrl())
            ->assertOk()
            ->assertSee('a16cc0b')
            ->assertSee('Derlenmiş dosyalar koddan daha yeni.', false);
    }

    public function test_no_warning_when_code_and_build_arrive_together(): void
    {
        $this->app->instance(DeploymentInfo::class, new class extends DeploymentInfo
        {
            public function codeUpdatedAt(): ?Carbon
            {
                return now()->subMinutes(10);
            }

            public function buildUploadedAt(): ?Carbon
            {
                return now()->subMinutes(5);
            }
        });

        $this->actingAs($this->admin())->get(SystemCommands::getUrl())
            ->assertOk()
            ->assertDontSee('Derlenmiş dosyalar koddan daha yeni.', false);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Yönetici', 'email' => 'surum@test.test', 'password' => 'parola1234',
            'role' => UserRole::SuperAdmin, 'is_active' => true,
        ]);
    }
}
