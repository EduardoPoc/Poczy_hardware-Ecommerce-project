<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CartMergeService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class CartMergePresentationTest extends TestCase
{
    private function customer(): User
    {
        $user = new User(['name' => 'Cliente', 'email' => 'client@example.com']);
        $user->id = 1;
        $user->role = 'CUSTOMER';
        $user->setHidden(['password', 'remember_token']);

        return $user;
    }

    public function test_login_returns_cart_merge_warnings_without_changing_the_user_response_shape(): void
    {
        $user = $this->customer();
        $cart = new Cart(['user_id' => $user->id]);
        $cart->id = 2;
        $cart->setRelation('items', new Collection);
        $adjustment = [
            'product_id' => 3, 'requested_quantity' => 2, 'quantity' => 0,
            'reason' => 'unavailable', 'message' => 'O produto foi removido porque está indisponível.',
        ];
        $merger = $this->mock(CartMergeService::class);
        $merger->shouldReceive('validateGuest')->once()->with(Mockery::type(Request::class))->ordered();
        $merger->shouldReceive('merge')->once()->with($user, Mockery::type(Request::class))
            ->andReturn(['cart' => $cart, 'adjustments' => [$adjustment]])->ordered();
        $this->mock(AuthService::class)->shouldReceive('login')->once()
            ->with(['email' => 'client@example.com', 'password' => 'test-password'], Mockery::type(Session::class))
            ->andReturn($user);

        $this->postJson('/api/login', ['email' => 'CLIENT@example.com', 'password' => 'test-password'], ['X-Cart-Token' => str_repeat('a', 64)])
            ->assertOk()->assertJsonPath('id', 1)->assertJsonPath('email', 'client@example.com')
            ->assertJsonPath('cart_merge.cart.id', 2)->assertJsonPath('cart_merge.cart.products_total', '0.00')
            ->assertJsonPath('cart_merge.adjustments.0', $adjustment)
            ->assertJsonMissingPath('password')->assertJsonMissingPath('cart_merge.cart.cart_token');
    }

    public function test_login_without_a_guest_token_does_not_access_cart_storage(): void
    {
        $user = $this->customer();
        $this->mock(AuthService::class)->shouldReceive('login')->once()->andReturn($user);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-password'])
            ->assertOk()->assertJsonPath('id', $user->id)->assertJsonMissingPath('cart_merge');
    }

    public function test_a_guest_token_without_its_session_is_rejected_before_login(): void
    {
        $this->mock(AuthService::class)->shouldNotReceive('login');
        $this->postJson('/api/login', ['email' => 'client@example.com', 'password' => 'test-password'], ['X-Cart-Token' => str_repeat('a', 64)])
            ->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_missing_or_malformed_credentials_are_validated_before_login_or_merge(): void
    {
        $this->mock(AuthService::class)->shouldNotReceive('login');
        $this->mock(CartMergeService::class)->shouldNotReceive('validateGuest');
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/login', ['email' => [], 'password' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/user', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_failed_merge_restores_the_guest_session_and_does_not_leave_a_partial_login(): void
    {
        $user = $this->customer();
        $this->withSession(['guest_cart_id' => 5, 'guest_cart_binding' => 'visitor-secret']);
        $merger = $this->mock(CartMergeService::class);
        $merger->shouldReceive('validateGuest')->once();
        $merger->shouldReceive('merge')->once()->andThrow(new \RuntimeException('Injected merge failure'));
        $this->mock(AuthService::class)->shouldReceive('login')->once()->andReturnUsing(function () use ($user): User {
            $guard = Auth::guard('web');
            $guard->setUser($user);
            session()->put($guard->getName(), $user->id);

            return $user;
        });

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-password'], ['X-Cart-Token' => str_repeat('a', 64)])
            ->assertStatus(500);
        $this->assertGuest('web');
        $this->assertSame(5, session('guest_cart_id'));
        $this->assertSame('visitor-secret', session('guest_cart_binding'));
    }

    public function test_failed_merge_preserves_a_previously_authenticated_account(): void
    {
        $previous = $this->customer();
        $this->actingAs($previous, 'web');
        $guardKey = Auth::guard('web')->getName();
        $this->withSession([$guardKey => $previous->id]);
        $next = new User(['name' => 'Outra conta', 'email' => 'other@example.com']);
        $next->id = 2;
        $merger = $this->mock(CartMergeService::class);
        $merger->shouldReceive('validateGuest')->once();
        $merger->shouldReceive('merge')->once()->andThrow(new \RuntimeException('Injected merge failure'));
        $this->mock(AuthService::class)->shouldReceive('login')->once()->andReturnUsing(function () use ($next, $guardKey): User {
            Auth::guard('web')->setUser($next);
            session()->put($guardKey, $next->id);

            return $next;
        });

        $this->postJson('/api/login', ['email' => $next->email, 'password' => 'test-password'])
            ->assertStatus(500);
        $this->assertAuthenticatedAs($previous, 'web');
        $this->assertSame($previous->id, session($guardKey));
    }
}
