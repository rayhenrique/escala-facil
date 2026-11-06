<?php

namespace Tests\Feature;

use App\Livewire\MyProfile;
use App\Models\Category;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication_and_shows_only_the_current_account(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
        $other = User::factory()->create(['role' => 'manager']);
        $professional = User::factory()->for(Sector::factory())->for(Category::factory())->create();

        $this->actingAs($professional)->get(route('profile', ['user' => $other->id]))
            ->assertOk()->assertSee('Meu Perfil')->assertSee($professional->email)
            ->assertSee($professional->sector->name)->assertSee($professional->category->name)
            ->assertSee('Ver minha escala')->assertDontSee($other->email);

        $this->actingAs($other)->get(route('profile'))->assertOk()->assertSee($other->email)
            ->assertDontSee('Ver minha escala');
        $this->assertFalse(Gate::forUser($other)->allows('updateOwn', $professional));
        $this->assertFalse(Gate::forUser($professional)->allows('updateOwn', $other));
    }

    public function test_name_is_required_and_updates_the_authenticated_account_and_navigation(): void
    {
        $professional = User::factory()->create();
        $other = User::factory()->create();

        Livewire::actingAs($professional)->test(MyProfile::class)
            ->set('name', '  ')->call('saveName')->assertHasErrors(['name' => 'required'])
            ->set('name', '  Nome atualizado  ')->call('saveName', $other->id)
            ->assertHasNoErrors()->assertRedirect(route('profile'));

        $this->assertSame('Nome atualizado', $professional->fresh()->name);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->get(route('profile'))->assertOk()->assertSee('Nome atualizado')->assertSee('Nome atualizado com sucesso.');
    }

    public static function protectedProperties(): array
    {
        return [['id'], ['userId'], ['email'], ['role'], ['sector_id'], ['category_id']];
    }

    #[DataProvider('protectedProperties')]
    public function test_profile_rejects_changes_to_protected_properties(string $property): void
    {
        $professional = User::factory()->create();
        $this->expectException(PublicPropertyNotFoundException::class);

        Livewire::actingAs($professional)->test(MyProfile::class)->set($property, 'forjado');
    }

    public function test_password_requires_current_credentials_and_confirmation_then_keeps_the_session(): void
    {
        $professional = User::factory()->create(['password' => 'Senha-anterior']);
        $profile = Livewire::actingAs($professional)->test(MyProfile::class);
        $credentials = [
            'current_password' => 'Senha-anterior',
            'password' => 'Senha-nova-forte',
            'password_confirmation' => 'Senha-nova-forte',
        ];
        $profile->call('changePassword', ['current_password' => 'Senha-incorreta'] + $credentials)
            ->assertHasErrors('current_password');
        $profile->call('changePassword', ['password_confirmation' => 'Senha-diferente'] + $credentials)
            ->assertHasErrors(['password' => 'confirmed']);
        $this->assertTrue(Hash::check('Senha-anterior', $professional->fresh()->password));

        $profile->call('changePassword', $credentials)
            ->assertHasNoErrors()->assertDispatched('profile-password-changed')
            ->assertSee('Senha alterada com sucesso.')->assertDontSee('Senha-nova-forte');

        $this->assertTrue(Hash::check('Senha-nova-forte', $professional->fresh()->password));
        $this->assertAuthenticatedAs($professional);
        $this->get(route('profile'))->assertOk();
    }
}
