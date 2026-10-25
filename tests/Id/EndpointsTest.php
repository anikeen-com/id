<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Illuminate\Database\Eloquent\Model;

/**
 * Every package method has to call an endpoint that exists in Anikeen ID, with the right token.
 */
class EndpointsTest extends TestCase
{
    private function owner(): Model
    {
        $owner = new class extends Model {
            use Billable;

            protected $guarded = [];
        };
        $owner->anikeen_id = '01JUSER';
        $owner->anikeen_id_access_token = 'user-token';

        return $owner;
    }

    private function sentCall(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    private function list(array $items = []): array
    {
        return ['data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items)], 'links' => ['next' => null]];
    }

    public function test_charge_uses_the_versioned_endpoint_without_stripe_options(): void
    {
        $this->respond(201, ['data' => ['id' => 'tx1', 'status' => 'succeeded']]);

        $this->owner()->charge(10, 'pm_1', ['transfer_data' => ['destination' => 'acct_x']]);

        $this->assertSame('POST /api/v1/billing/charge Bearer user-token', $this->sentCall(0));
        $this->assertSame(['amount' => 10, 'payment_method_id' => 'pm_1'], $this->sentJson(0));
    }

    public function test_billing_overview(): void
    {
        $this->respond(200, ['data' => ['current_charges' => 11.9, 'has_default_payment_method' => true]]);

        $this->assertSame(11.9, $this->owner()->billingOverview()->current_charges);
        $this->assertSame('GET /api/v1/billing Bearer user-token', $this->sentCall(0));
    }

    public function test_invoice_download_url_is_read_not_updated(): void
    {
        $this->respond(200, $this->list([['id' => 'inv1']]));
        $this->respond(200, ['data' => ['id' => 'inv1', 'temporary_url' => 'https://s3/inv1.pdf']]);
        $this->respond(200, ['data' => ['id' => 'inv1', 'temporary_url' => 'https://s3/inv1.pdf']]);

        $url = $this->owner()->invoices()->find('inv1')->getInvoiceTemporaryUrl();

        $this->assertSame('https://s3/inv1.pdf', $url);
        $this->assertSame('GET /api/v1/invoices/inv1 Bearer user-token', $this->sentCall(2));
    }

    public function test_order_items_are_created_on_the_items_endpoint(): void
    {
        $this->respond(200, $this->list());                                       // GET v1/orders
        $this->respond(200, ['data' => ['id' => 'order1', 'status' => 'draft']]); // GET v1/orders/order1
        $this->respond(200, $this->list());                                       // GET v1/orders/order1/items
        $this->respond(201, ['data' => [['id' => 'item1', 'name' => 'Domain']]]);  // POST v1/orders/order1/items

        $created = $this->owner()->orders()->find('order1')->orderItems()
            ->create('order1', ['items' => [['type' => 'digital', 'name' => 'Domain', 'price' => 10, 'unit' => 'year', 'units' => 1]]]);

        $this->assertSame('item1', $created->id);
        $this->assertSame('POST /api/v1/orders/order1/items Bearer user-token', $this->sentCall(3));
    }

    public function test_create_user_uses_the_app_token_and_admin_endpoint(): void
    {
        $this->respond(200, ['data' => ['id' => '01JNEW']]);

        app(AnikeenId::class)->createUser(['first_name' => 'Erika', 'last_name' => 'Mustermann', 'email' => 'erika@example.com', 'password' => 'secret-123']);

        $this->assertSame('POST /api/v1/admin/users Bearer app-token', $this->sentCall(0));
        $this->assertSame(['email' => 'erika@example.com', 'password' => 'secret-123', 'name' => 'Erika Mustermann', 'password_confirmation' => 'secret-123'], $this->sentJson(0));
    }

    public function test_email_check_and_socialite_users(): void
    {
        $this->respond(200, ['data' => ['exists' => true]]);
        $this->respond(200, ['data' => ['id' => '01JUSER']]);

        $this->assertTrue(app(AnikeenId::class)->isEmailExisting('erika@example.com')->data->exists);
        app(AnikeenId::class)->findOrCreateSocialiteUser('twitch', '123', 'streamer@example.com');

        $this->assertSame('POST /api/v1/admin/users/check Bearer app-token', $this->sentCall(0));
        $this->assertSame('POST /api/v1/admin/users/socialite Bearer app-token', $this->sentCall(1));
        $this->assertSame(['provider' => 'twitch', 'provider_id' => '123', 'email' => 'streamer@example.com'], $this->sentJson(1));
    }

    public function test_ssh_keys_of_a_user_for_apps_are_not_cached_across_users(): void
    {
        $this->respond(200, ['data' => [['id' => 'k1', 'name' => 'A']]]);
        $this->respond(200, ['data' => [['id' => 'k2', 'name' => 'B']]]);

        $first = app(AnikeenId::class)->sshKeysByUserId('01JA');
        $second = app(AnikeenId::class)->sshKeysByUserId('01JB');

        $this->assertSame('k1', $first->toArray()[0]->id);
        $this->assertSame('k2', $second->toArray()[0]->id);
        $this->assertSame('GET /api/v1/client/users/01JA/ssh-keys Bearer app-token', $this->sentCall(0));
        $this->assertSame('GET /api/v1/client/users/01JB/ssh-keys Bearer app-token', $this->sentCall(1));
    }

    public function test_users_manage_their_own_ssh_keys(): void
    {
        $this->respond(200, $this->list());
        $this->respond(201, ['data' => ['id' => 'k1', 'name' => 'Laptop']]);
        $this->respond(200, ['data' => ['id' => 'k1', 'name' => 'Work']]);
        $this->respond(204);

        $keys = $this->owner()->sshKeys();
        $key = $keys->create('ssh-ed25519 AAAA… erika@laptop', 'Laptop');
        $renamed = $key->update('Work');

        $this->assertSame('Work', $renamed->name);
        $this->assertTrue($renamed->delete());
        $this->assertSame([
            'GET /api/v1/ssh-keys Bearer user-token',
            'POST /api/v1/ssh-keys Bearer user-token',
            'PUT /api/v1/ssh-keys/k1 Bearer user-token',
            'DELETE /api/v1/ssh-keys/k1 Bearer user-token',
        ], array_map(fn ($i) => $this->sentCall($i), range(0, 3)));
    }

    public function test_payment_methods_can_be_deleted(): void
    {
        $this->respond(200, ['data' => [['id' => 'pm_1']]]);
        $this->respond(200, ['data' => ['id' => 'pm_1', 'type' => 'card']]);
        $this->respond(204);

        $this->assertTrue($this->owner()->paymentMethods()->find('pm_1')->delete());
        $this->assertSame('DELETE /api/v1/payment-methods/pm_1 Bearer user-token', $this->sentCall(2));
    }

    public function test_products_and_boxes(): void
    {
        $this->respond(200, ['data' => [['id' => 'p1']]]);
        $this->respond(200, [['id' => 'b1']]);

        app(AnikeenId::class)->products();
        app(AnikeenId::class)->boxes();

        $this->assertStringStartsWith('GET /api/v1/products', $this->sentCall(0));
        $this->assertStringStartsWith('GET /api/v1/boxes', $this->sentCall(1));
    }

    public function test_shipping_labels_use_the_app_token(): void
    {
        foreach (range(1, 5) as $i) {
            $this->respond(200, ['data' => []]);
        }
        $this->http->append(new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.7'));

        $id = app(AnikeenId::class);
        $id->shippingLabels();
        $id->shippingLabel('l1');
        $id->issueShippingLabel(['box' => ['id' => 'b1'], 'weight' => 20, 'address' => [], 'billed_user_id' => '01JUSER', 'test' => true]);
        $id->shipShippingLabel(['0A', 'FF']);
        $id->shippingPriceList();
        $pdf = $id->shippingLabelPdf('l1');

        $this->assertSame('%PDF-1.7', $pdf);
        $this->assertSame([
            'GET /api/v1/admin/shipping-labels Bearer app-token',
            'GET /api/v1/admin/shipping-labels/l1 Bearer app-token',
            'POST /api/v1/admin/shipping-labels/issue Bearer app-token',
            'POST /api/v1/admin/shipping-labels/ship Bearer app-token',
            'GET /api/v1/admin/shipping-labels/price-list Bearer app-token',
            'GET /api/v1/admin/shipping-labels/l1/pdf Bearer app-token',
        ], array_map(fn ($i) => $this->sentCall($i), range(0, 5)));
    }

    public function test_orders_and_transactions_for_users(): void
    {
        $this->respond(201, ['data' => ['id' => 'o1']]);
        $this->respond(201, ['data' => ['id' => 'tx1']]);

        app(AnikeenId::class)->createOrderForUser('01JUSER', ['items' => [], 'checkout' => true]);
        app(AnikeenId::class)->createTransactionForUser('01JUSER', ['status' => 'succeeded', 'type' => 'deposit', 'amount' => '9.99', 'currency_iso' => 'EUR', 'created_at' => '2026-10-26']);

        $this->assertSame('POST /api/v1/admin/orders Bearer app-token', $this->sentCall(0));
        $this->assertSame('01JUSER', $this->sentJson(0)['user_id']);
        $this->assertSame('POST /api/v1/admin/transactions Bearer app-token', $this->sentCall(1));
    }

    public function test_order_preview_maps_the_country_into_the_billing_address(): void
    {
        $this->respond(200, ['data' => ['gross_total' => 11.9]]);

        app(AnikeenId::class)->createOrderPreview(['country_iso' => 'DE', 'items' => []]);

        $this->assertSame(['items' => [], 'billing_address' => ['country_iso' => 'DE']], $this->sentJson(0));
    }
}
