<?php

namespace Tests\Feature\Investor;

use App\Models\Role;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StockDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_investor_sees_market_catalog_without_fabricated_quotes(): void
    {
        $this->seed();
        $user = $this->investor();

        $this->actingAs($user)
            ->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('Stocks')
            ->assertSee('Dow Jones Industrial Average')
            ->assertSee('S&P 500')
            ->assertSee('AAPL')
            ->assertSee('My Stocks')
            ->assertSee('Quote unavailable')
            ->assertSee('A market-data provider has not been configured.')
            ->assertDontSee('344.63')
            ->assertDontSee('5.030T');
    }

    public function test_market_search_matches_ticker_and_company_name(): void
    {
        $this->seed();
        $user = $this->investor();

        $this->actingAs($user)
            ->get(route('investor.dashboard', ['q' => 'berkshire']))
            ->assertOk()
            ->assertSee('BRK-B')
            ->assertSee('Berkshire Hathaway Inc.')
            ->assertDontSee('The Home Depot, Inc.');
    }

    public function test_new_investor_keeps_email_verification_then_reaches_dashboard_and_saved_stocks_survive_sign_in(): void
    {
        $this->seed();
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Jordan Davis',
            'email' => 'jordan@example.test',
            'password' => 'SecurePassphrase!2026',
            'password_confirmation' => 'SecurePassphrase!2026',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'jordan@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);

        // Start the next request with the persisted user record and database defaults.
        $this->app['auth']->forgetGuards();
        $this->get(route('investor.dashboard'))->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );
        $this->get($verificationUrl)->assertRedirect(route('investor.dashboard'));
        $this->get(route('investor.dashboard'))->assertOk()->assertSee('My Stocks');

        $this->post(route('investor.watchlist.store'), ['symbol' => 'AAPL'])->assertRedirect();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'SecurePassphrase!2026'])
            ->assertRedirect(route('investor.dashboard'));
        $this->get(route('investor.dashboard'))->assertOk()->assertSee('AAPL');
    }

    public function test_investor_can_create_update_reopen_and_delete_their_watchlist_item(): void
    {
        $this->seed();
        $user = $this->investor();

        $this->actingAs($user)
            ->post(route('investor.watchlist.store'), ['symbol' => 'AAPL'])
            ->assertRedirect();

        $item = WatchlistItem::query()->where('user_id', $user->id)->where('symbol', 'AAPL')->firstOrFail();
        $this->assertSame('Apple Inc.', $item->company_name);

        $this->patch(route('investor.watchlist.update', ['watchlistItem' => $item->id]), [
            'notes' => 'Personal research note',
            'sort_order' => 20,
        ])->assertRedirect();

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('investor.dashboard'));

        $this->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertSee('Personal research note');

        $this->delete(route('investor.watchlist.destroy', ['watchlistItem' => $item->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('watchlist_items', ['id' => $item->id]);
    }

    public function test_duplicate_instrument_is_rejected_for_one_user_but_can_be_saved_by_another(): void
    {
        $this->seed();
        $firstUser = $this->investor();
        $this->actingAs($firstUser)->post(route('investor.watchlist.store'), ['symbol' => 'AAPL'])->assertRedirect();
        $this->post(route('investor.watchlist.store'), ['symbol' => 'AAPL'])->assertSessionHasErrors('symbol');
        $this->assertSame(1, $firstUser->watchlistItems()->count());

        $secondUser = $this->investor();
        $this->actingAs($secondUser)->post(route('investor.watchlist.store'), ['symbol' => 'AAPL'])->assertRedirect();

        $this->assertDatabaseCount('watchlist_items', 2);
        $this->assertSame(1, $firstUser->watchlistItems()->count());
        $this->assertSame(1, $secondUser->watchlistItems()->count());
    }

    public function test_database_unique_constraint_rejects_duplicate_user_instrument_rows(): void
    {
        $this->seed();
        $user = $this->investor();
        $attributes = [
            'symbol' => 'AAPL',
            'company_name' => 'Apple Inc.',
            'exchange' => 'NASDAQ',
            'instrument_type' => 'stock',
            'sort_order' => 10,
        ];

        $user->watchlistItems()->create($attributes);

        $this->expectException(QueryException::class);
        $user->watchlistItems()->create($attributes);
    }

    public function test_user_cannot_read_private_watchlist_notes_or_update_another_users_item(): void
    {
        $this->seed();
        $owner = $this->investor();
        $otherUser = $this->investor();
        $item = $owner->watchlistItems()->create([
            'symbol' => 'AAPL',
            'company_name' => 'Apple Inc.',
            'exchange' => 'NASDAQ',
            'instrument_type' => 'stock',
            'sort_order' => 10,
            'notes' => 'private owner note',
        ]);

        $this->actingAs($otherUser)
            ->get(route('investor.dashboard'))
            ->assertOk()
            ->assertDontSee('private owner note');

        $this->patch(route('investor.watchlist.update', ['watchlistItem' => $item->id]), [
            'notes' => 'attempted edit',
            'sort_order' => 1,
        ])->assertNotFound();

        $this->delete(route('investor.watchlist.destroy', ['watchlistItem' => $item->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('watchlist_items', [
            'id' => $item->id,
            'user_id' => $owner->id,
            'notes' => 'private owner note',
        ]);
    }

    public function test_unverified_investor_cannot_open_dashboard_or_change_watchlist(): void
    {
        $this->seed();
        $user = $this->investor(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get(route('investor.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->post(route('investor.watchlist.store'), ['symbol' => 'AAPL'])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_stock_detail_is_clickable_and_truthfully_reports_missing_market_data(): void
    {
        $this->seed();
        $user = $this->investor();

        $this->actingAs($user)
            ->get(route('investor.stocks.show', ['symbol' => 'AAPL']))
            ->assertOk()
            ->assertSee('Apple Inc.')
            ->assertSee('Latest price')
            ->assertSee('No quote timestamp')
            ->assertSee('Chart unavailable until a market-data source is configured.')
            ->assertDontSee('344.63');
    }

    private function investor(array $attributes = []): User
    {
        $role = Role::query()->where('slug', 'investor')->firstOrFail();
        $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attributes));
        $user->forceFill(['role_id' => $role->id])->save();
        $user->refresh();

        return $user;
    }
}
