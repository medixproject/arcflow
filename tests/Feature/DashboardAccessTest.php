<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery-staple';

    private int $counter = 0;

    private function makeUser(string $email = 'owner@example.test', bool $withMerchant = true, string $merchantName = 'Test Merchant'): array
    {
        $this->counter++;
        $user = User::create(['name' => 'Owner ' . $this->counter, 'email' => $email, 'password' => self::PASSWORD]);
        $merchant = $withMerchant
            ? Merchant::create([
                'user_id' => $user->id,
                'name' => $merchantName,
                'slug' => 'merchant-' . $this->counter,
                'status' => 'active',
                'default_network' => config('arc.network'),
            ])
            : null;

        return [$user, $merchant];
    }

    private function makeInvoice(Merchant $merchant, string $amount, string $status, $expiresAt = null, ?string $address = null): Invoice
    {
        $this->counter++;

        return Invoice::create([
            'merchant_id' => $merchant->id,
            'amount' => $amount,
            'currency' => 'USDC',
            'network' => config('arc.network'),
            'payment_address' => $address ?? '0x' . str_pad((string) $this->counter, 40, '0', STR_PAD_LEFT),
            'status' => $status,
            'expires_at' => $expiresAt ?? now()->addHour(),
        ]);
    }

    private function makePayment(Invoice $invoice, string $amount, string $status): Payment
    {
        $this->counter++;

        return Payment::create([
            'invoice_id' => $invoice->id,
            'network' => $invoice->network,
            'token_contract' => '0x3600000000000000000000000000000000000000',
            'from_address' => '0x2222222222222222222222222222222222222222',
            'to_address' => $invoice->payment_address,
            'amount' => $amount,
            'currency' => 'USDC',
            'transaction_hash' => '0x' . str_pad((string) $this->counter, 64, 'a', STR_PAD_LEFT),
            'log_index' => 0,
            'block_number' => 100 + $this->counter,
            'confirmations' => 1,
            'status' => $status,
            'detected_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- sign in

    public function test_guests_are_sent_to_the_login_page(): void
    {
        foreach (['/dashboard', '/dashboard/invoices', '/dashboard/payments', '/dashboard/review'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_login_page_renders_with_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in to Arcflow.')
            ->assertSee('name="password"', false)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_wrong_password_is_rejected(): void
    {
        [$user] = $this->makeUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'not-the-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_correct_credentials_sign_the_user_in(): void
    {
        [$user] = $this->makeUser();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_repeated_failures_lock_the_account_even_for_the_right_password(): void
    {
        [$user] = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-' . $i]);
        }

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_signed_in_users_are_sent_away_from_login_and_can_sign_out(): void
    {
        [$user] = $this->makeUser();

        $this->actingAs($user)->get('/login')->assertRedirect('/dashboard');
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    // ------------------------------------------------------------------ pages

    public function test_dashboard_overview_shows_counts_and_partial_progress(): void
    {
        [$user, $merchant] = $this->makeUser('owner@example.test', true, 'Acme Coffee');
        $partial = $this->makeInvoice($merchant, '2.000000', 'underpaid');
        $this->makePayment($partial, '0.500000', 'underpaid');
        $this->makeInvoice($merchant, '5.000000', 'paid');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Good to see you, Acme Coffee.')
            ->assertSee($partial->invoice_number)
            ->assertSee('1.50 remaining')
            ->assertSee('<b>1</b> partially paid', false)
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_user_without_a_merchant_sees_an_empty_state_on_every_page(): void
    {
        [$user] = $this->makeUser('nomerchant@example.test', false);

        foreach (['/dashboard', '/dashboard/invoices', '/dashboard/payments', '/dashboard/review'] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('No merchant profile is linked');
        }
    }

    public function test_invoice_list_can_be_filtered(): void
    {
        [$user, $merchant] = $this->makeUser();
        $paid = $this->makeInvoice($merchant, '1.000000', 'paid');
        $pending = $this->makeInvoice($merchant, '1.000000', 'pending');
        $expired = $this->makeInvoice($merchant, '1.000000', 'pending', now()->subHour());

        $this->actingAs($user)->get('/dashboard/invoices?filter=paid')
            ->assertOk()->assertSee($paid->invoice_number)
            ->assertDontSee($pending->invoice_number)->assertDontSee($expired->invoice_number);

        $this->actingAs($user)->get('/dashboard/invoices?filter=open')
            ->assertOk()->assertSee($pending->invoice_number)
            ->assertDontSee($paid->invoice_number)->assertDontSee($expired->invoice_number);

        $this->actingAs($user)->get('/dashboard/invoices?filter=expired')
            ->assertOk()->assertSee($expired->invoice_number)
            ->assertDontSee($paid->invoice_number)->assertDontSee($pending->invoice_number);

        $this->actingAs($user)->get('/dashboard/invoices?filter=nonsense')
            ->assertOk()->assertSee($paid->invoice_number)->assertSee($pending->invoice_number);
    }

    public function test_invoice_detail_shows_progress_and_every_payment(): void
    {
        [$user, $merchant] = $this->makeUser();
        $invoice = $this->makeInvoice($merchant, '2.000000', 'underpaid');
        $this->makePayment($invoice, '0.500000', 'underpaid');

        $this->actingAs($user)->get('/dashboard/invoices/' . $invoice->public_id)
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Payment progress')
            ->assertSee('1.50')
            ->assertSee('0.50')
            ->assertSee('can still be sent to the same address');
    }

    public function test_invoice_detail_is_not_visible_to_other_merchants(): void
    {
        [$owner, $ownerMerchant] = $this->makeUser('owner@example.test', true, 'Owner Co');
        [$other] = $this->makeUser('other@example.test', true, 'Other Co');
        $invoice = $this->makeInvoice($ownerMerchant, '1.000000', 'pending');

        $this->actingAs($other)->get('/dashboard/invoices/' . $invoice->public_id)->assertNotFound();
        $this->actingAs($owner)->get('/dashboard/invoices/' . $invoice->public_id)->assertOk();
    }

    public function test_payments_page_lists_only_own_payments(): void
    {
        [$user, $merchant] = $this->makeUser('owner@example.test', true, 'Owner Co');
        [, $otherMerchant] = $this->makeUser('other@example.test', true, 'Other Co');
        $mine = $this->makeInvoice($merchant, '1.000000', 'paid');
        $theirs = $this->makeInvoice($otherMerchant, '1.000000', 'paid');
        $this->makePayment($mine, '1.000000', 'confirmed');
        $this->makePayment($theirs, '1.000000', 'confirmed');

        $this->actingAs($user)->get('/dashboard/payments')
            ->assertOk()
            ->assertSee($mine->invoice_number)
            ->assertDontSee($theirs->invoice_number);
    }

    public function test_review_page_lists_late_payments_overpayments_and_legacy_transfers(): void
    {
        [$user, $merchant] = $this->makeUser();
        $late = $this->makeInvoice($merchant, '1.000000', 'cancelled');
        $this->makePayment($late, '1.000000', 'late_review');
        $over = $this->makeInvoice($merchant, '1.000000', 'overpaid');
        $this->makePayment($over, '1.250000', 'overpaid');
        $legacy = $this->makeInvoice($merchant, '3.000000', 'pending', now()->subDay(), '0x9999999999999999999999999999999999999999');

        DB::table('legacy_transfer_reviews')->insert([
            'network' => config('arc.network'),
            'token_contract' => '0x3600000000000000000000000000000000000000',
            'from_address' => '0x2222222222222222222222222222222222222222',
            'to_address' => '0x9999999999999999999999999999999999999999',
            'amount' => '3.000000',
            'transaction_hash' => '0x' . str_repeat('b', 64),
            'log_index' => 0,
            'block_number' => 500,
            'confirmations' => 1,
            'candidate_invoice_ids' => json_encode([$legacy->id]),
            'review_reason' => 'expired_invoice',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard/review')
            ->assertOk()
            ->assertSee($late->invoice_number)
            ->assertSee($over->invoice_number)
            ->assertSee($legacy->invoice_number)
            ->assertSee('expired invoice')
            ->assertSee('0.25');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('item(s) need a manual decision');
    }

    // ------------------------------------------------- public payment page

    public function test_payment_page_shows_partial_progress_and_the_remaining_amount(): void
    {
        [, $merchant] = $this->makeUser();
        $invoice = $this->makeInvoice($merchant, '2.000000', 'underpaid');
        $this->makePayment($invoice, '0.500000', 'underpaid');

        $this->get('/pay/' . $invoice->public_id)
            ->assertOk()
            ->assertSee('Partial payment received')
            ->assertSee('Remaining')
            ->assertSee('1.50 USDC')
            ->assertSee($invoice->payment_address)
            ->assertSee('http-equiv="refresh"', false);
    }

    public function test_expired_payment_page_hides_the_address_and_stops_refreshing(): void
    {
        [, $merchant] = $this->makeUser();
        $invoice = $this->makeInvoice($merchant, '1.000000', 'pending', now()->subMinute());

        $this->get('/pay/' . $invoice->public_id)
            ->assertOk()
            ->assertSee('This payment request has expired')
            ->assertSee('Do not send funds')
            ->assertDontSee($invoice->payment_address)
            ->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_paid_payment_page_is_final(): void
    {
        [, $merchant] = $this->makeUser();
        $invoice = $this->makeInvoice($merchant, '1.000000', 'paid', now()->subDay());
        $this->makePayment($invoice, '1.000000', 'confirmed');

        $this->get('/pay/' . $invoice->public_id)
            ->assertOk()
            ->assertSee('Payment complete')
            ->assertDontSee($invoice->payment_address)
            ->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_fresh_payment_page_asks_for_the_exact_amount(): void
    {
        [, $merchant] = $this->makeUser();
        $invoice = $this->makeInvoice($merchant, '12.500000', 'pending');

        $this->get('/pay/' . $invoice->public_id)
            ->assertOk()
            ->assertSee('Complete your payment')
            ->assertSee('12.50 USDC')
            ->assertSee($invoice->payment_address);
    }

    // --------------------------------------------------------------- command

    public function test_password_command_sets_a_new_password_and_signs_out_old_sessions(): void
    {
        [$user] = $this->makeUser();

        $this->artisan('arcflow:dashboard-password', ['email' => $user->email])
            ->expectsQuestion('New password (min 12 characters)', 'a-brand-new-passphrase')
            ->expectsQuestion('Repeat password', 'a-brand-new-passphrase')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('a-brand-new-passphrase', $user->fresh()->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'a-brand-new-passphrase'])->assertRedirect('/dashboard');
    }

    public function test_password_command_rejects_short_or_mismatched_passwords(): void
    {
        [$user] = $this->makeUser();

        $this->artisan('arcflow:dashboard-password', ['email' => $user->email])
            ->expectsQuestion('New password (min 12 characters)', 'short')
            ->expectsQuestion('Repeat password', 'short')
            ->assertExitCode(1);

        $this->artisan('arcflow:dashboard-password', ['email' => $user->email])
            ->expectsQuestion('New password (min 12 characters)', 'long-enough-password-1')
            ->expectsQuestion('Repeat password', 'long-enough-password-2')
            ->assertExitCode(1);

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_password_command_lists_users_without_an_email_and_fails_for_unknown_ones(): void
    {
        $this->makeUser();

        $this->artisan('arcflow:dashboard-password')->assertExitCode(0);
        $this->artisan('arcflow:dashboard-password', ['email' => 'nobody@example.test'])->assertExitCode(1);
    }
}
