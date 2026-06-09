<?php

declare(strict_types=1);

use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Commerce\Application\LedgerService;
use App\Contexts\Commerce\Domain\Ledger\AccountType;
use App\Contexts\Commerce\Infrastructure\Persistence\Coupon;
use App\Contexts\Commerce\Infrastructure\Persistence\LedgerEntry;
use App\Contexts\Commerce\Infrastructure\Persistence\Order;
use App\Contexts\Commerce\Infrastructure\Persistence\Payment;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(SettingsRepository::class)->set(SettingKey::PaymentsEnabled, true); // commerce active
    $this->instructor = userWithRole(Role::Instructor);
    $this->course = Course::factory()->published()->for($this->instructor, 'instructor')->create([
        'pricing_type' => PricingType::OneTime,
        'price_minor' => 50000,
    ]);
});

function payWebhook(string $ref, string $status = 'paid'): TestResponse
{
    $body = json_encode(['id' => $ref, 'status' => $status]);
    $signature = hash_hmac('sha256', $body, 'fake-webhook-secret');

    return test()->call('POST', '/api/v1/webhooks/payments/moyasar', [], [], [], [
        'HTTP_X_MOYASAR_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

it('creates a pending order and payment at checkout', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_minor', 50000);

    $this->assertDatabaseHas('payments', ['status' => 'initiated', 'amount_minor' => 50000]);
});

it('applies a coupon discount', function () {
    Coupon::query()->create(['code' => 'HALF', 'type' => 'percentage', 'value' => 50, 'active' => true]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug, 'coupon' => 'HALF'])
        ->assertCreated()
        ->assertJsonPath('data.discount_minor', 25000)
        ->assertJsonPath('data.total_minor', 25000);
});

it('rejects checkout for a free course', function () {
    $free = Course::factory()->published()->create(['pricing_type' => PricingType::Free, 'price_minor' => 0]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $free->slug])->assertStatus(422);
});

it('is idempotent at checkout', function () {
    Sanctum::actingAs(User::factory()->create());
    $first = $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug])->json('data.id');
    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug])
        ->assertOk()
        ->assertJsonPath('data.id', $first);

    expect(Order::query()->count())->toBe(1);
});

it('settles payment: marks paid, activates enrollment and posts a balanced revenue split', function () {
    $student = User::factory()->create();
    Sanctum::actingAs($student);
    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug])->assertCreated();

    $ref = Payment::query()->firstOrFail()->gateway_ref;
    payWebhook($ref)->assertOk();

    // Order paid, enrollment active and linked.
    $order = Order::query()->firstOrFail();
    expect($order->status->value)->toBe('paid');
    $enrollment = Enrollment::query()->where('user_id', $student->id)->firstOrFail();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active);
    expect($enrollment->order_id)->toBe($order->id);

    // Ledger: platform revenue = 20% = 10000, instructor payable = 40000, and it balances.
    $ledger = app(LedgerService::class);
    expect($ledger->balance(AccountType::PlatformRevenue, null)->minor)->toBe(10000);
    expect($ledger->instructorBalance($this->instructor->id)->minor)->toBe(40000);

    $debits = (int) LedgerEntry::query()->sum('debit_minor');
    $credits = (int) LedgerEntry::query()->sum('credit_minor');
    expect($debits)->toBe($credits)->toBe(50000);
});

it('processes a duplicate payment webhook at most once', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug]);
    $ref = Payment::query()->firstOrFail()->gateway_ref;

    payWebhook($ref)->assertOk();
    payWebhook($ref)->assertOk()->assertJsonPath('status', 'duplicate');

    // Revenue not double-counted.
    expect(app(LedgerService::class)->balance(AccountType::PlatformRevenue, null)->minor)->toBe(10000);
});

it('rejects a payment webhook with a bad signature', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug]);
    $ref = Payment::query()->firstOrFail()->gateway_ref;

    $body = json_encode(['id' => $ref, 'status' => 'paid']);
    $this->call('POST', '/api/v1/webhooks/payments/moyasar', [], [], [], [
        'HTTP_X_MOYASAR_SIGNATURE' => 'bad',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body)->assertStatus(401);
});

it('refunds a paid order: reverses the ledger and revokes access', function () {
    $student = User::factory()->create();
    Sanctum::actingAs($student);
    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug]);
    $ref = Payment::query()->firstOrFail()->gateway_ref;
    payWebhook($ref)->assertOk();
    $order = Order::query()->firstOrFail();

    Sanctum::actingAs(userWithRole(Role::SuperAdmin));
    $this->postJson("/api/v1/commerce/orders/{$order->id}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    $ledger = app(LedgerService::class);
    expect($ledger->instructorBalance($this->instructor->id)->minor)->toBe(0);
    expect($ledger->balance(AccountType::PlatformRevenue, null)->minor)->toBe(0);
    expect(Enrollment::query()->where('order_id', $order->id)->first()->status)->toBe(EnrollmentStatus::Refunded);
});

it('runs the instructor payout flow against the ledger balance', function () {
    // Generate a sale so the instructor has 40000 payable.
    $student = User::factory()->create();
    Sanctum::actingAs($student);
    $this->postJson('/api/v1/commerce/checkout', ['course_slug' => $this->course->slug]);
    payWebhook(Payment::query()->firstOrFail()->gateway_ref)->assertOk();

    // Instructor requests a payout within balance.
    Sanctum::actingAs($this->instructor);
    $payoutId = $this->postJson('/api/v1/commerce/payouts', ['amount_minor' => 40000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');

    // Over-balance request is rejected.
    $this->postJson('/api/v1/commerce/payouts', ['amount_minor' => 999999])->assertStatus(422);

    // Admin settles the payout; balance drops to zero.
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));
    $this->postJson("/api/v1/commerce/payouts/{$payoutId}/pay")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    expect(app(LedgerService::class)->instructorBalance($this->instructor->id)->minor)->toBe(0);
});

it('enforces the minimum payout amount', function () {
    Sanctum::actingAs($this->instructor);
    $this->postJson('/api/v1/commerce/payouts', ['amount_minor' => 100])->assertStatus(422);
});

it('lets an admin create a coupon but forbids a student', function () {
    Sanctum::actingAs(userWithRole(Role::Student));
    $this->postJson('/api/v1/commerce/coupons', ['code' => 'X', 'type' => 'fixed', 'value' => 1000])
        ->assertForbidden();

    Sanctum::actingAs(userWithRole(Role::SuperAdmin));
    $this->postJson('/api/v1/commerce/coupons', ['code' => 'WELCOME', 'type' => 'fixed', 'value' => 1000])
        ->assertCreated();
});
