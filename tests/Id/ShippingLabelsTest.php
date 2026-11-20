<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;

class ShippingLabelsTest extends TestCase
{
    private function line(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    /**
     * A label as Anikeen ID returns it.
     */
    private function label(string $id, ?string $reference = null): array
    {
        return ['id' => $id, 'provider' => 'test', 'tracking_number' => 'TEST0123456789ABCDEF', 'reference' => $reference, 'cancelled_at' => null];
    }

    public function test_the_label_list_reads_laravels_plain_paginator(): void
    {
        // `GET shipping-labels` answers with `->paginate(100)`: `links` is a list, the page data
        // sits at the root
        $this->respond(200, [
            'current_page' => 1,
            'data' => [$this->label('l1', 'envelope-1'), $this->label('l2', 'envelope-1')],
            'first_page_url' => 'https://id.anikeen.com/api/v1/admin/shipping-labels?page=1',
            'from' => 1,
            'last_page' => 2,
            'last_page_url' => 'https://id.anikeen.com/api/v1/admin/shipping-labels?page=2',
            'links' => [
                ['url' => null, 'label' => '&laquo; Previous', 'active' => false],
                ['url' => 'https://id.anikeen.com/api/v1/admin/shipping-labels?page=1', 'label' => '1', 'active' => true],
                ['url' => 'https://id.anikeen.com/api/v1/admin/shipping-labels?page=2', 'label' => 'Next &raquo;', 'active' => false],
            ],
            'next_page_url' => 'https://id.anikeen.com/api/v1/admin/shipping-labels?page=2',
            'path' => 'https://id.anikeen.com/api/v1/admin/shipping-labels',
            'per_page' => 100,
            'prev_page_url' => null,
            'to' => 2,
            'total' => 102,
        ]);

        $result = app(AnikeenId::class)->shippingLabels(['reference' => 'envelope-1']);

        $this->assertTrue($result->success());
        $this->assertCount(2, $result->data);
        $this->assertSame('l2', $result->data[1]->id);
        $this->assertSame(102, $result->total);
        $this->assertSame('https://id.anikeen.com/api/v1/admin/shipping-labels?page=2', $result->links->next);
        $this->assertNull($result->links->prev);
        $this->assertSame(1, $result->meta->current_page);
        $this->assertSame(2, $result->meta->last_page);
        $this->assertSame('2', $result->next()->cursor());
        $this->assertSame('GET /api/v1/admin/shipping-labels Bearer app-token', $this->line(0));
        $this->assertSame('reference=envelope-1', $this->sent[0]['request']->getUri()->getQuery());
    }

    public function test_a_resource_collection_still_reads_as_before(): void
    {
        $this->respond(200, [
            'data' => [$this->label('l1')],
            'links' => ['first' => '?page=1', 'last' => '?page=3', 'prev' => null, 'next' => '?page=2'],
            'meta' => ['current_page' => 1, 'last_page' => 3, 'per_page' => 1, 'total' => 3],
        ]);

        $result = app(AnikeenId::class)->shippingLabels();

        $this->assertCount(1, $result->data);
        $this->assertSame(3, $result->total);
        $this->assertSame('?page=2', $result->links->next);
        $this->assertSame(3, $result->meta->last_page);
    }

    public function test_an_issue_sends_its_reference_and_a_retry_gets_the_label_back(): void
    {
        $this->respond(201, $this->label('l1', 'envelope-1'));
        $this->respond(200, $this->label('l1', 'envelope-1'));
        $this->respond(409, ['message' => 'This reference already names another live shipping label. Void it to use the reference again.', 'error' => 'reference_conflict', 'label_id' => 'l1']);

        $attributes = ['box' => ['common_name' => 'C6'], 'weight' => 12, 'address' => [], 'billed_user_id' => '01JUSER', 'reference' => 'envelope-1'];
        $first = app(AnikeenId::class)->issueShippingLabel($attributes);
        $retry = app(AnikeenId::class)->issueShippingLabel($attributes);
        $changed = app(AnikeenId::class)->issueShippingLabel([...$attributes, 'weight' => 30]);

        $this->assertSame('envelope-1', $this->sentJson(0)['reference']);
        $this->assertSame('POST /api/v1/admin/shipping-labels/issue Bearer app-token', $this->line(0));
        $this->assertSame([201, 200], [$first->status, $retry->status]);
        $this->assertSame($first->data->id, $retry->data->id);
        $this->assertFalse($changed->success());
        $this->assertSame(409, $changed->status);
        $this->assertSame('reference_conflict', $changed->data->error);
        $this->assertSame('l1', $changed->data->label_id);
    }

    public function test_a_label_is_voided_with_the_app_token(): void
    {
        $this->respond(200, [...$this->label('l1'), 'cancelled_at' => '2026-11-20T10:00:00.000000Z']);
        $this->respond(409, ['message' => 'The shipping label was already cancelled.', 'error' => 'label_cancelled']);

        $voided = app(AnikeenId::class)->voidShippingLabel('l1');
        $again = app(AnikeenId::class)->voidShippingLabel('l1');

        $this->assertSame('POST /api/v1/admin/shipping-labels/l1/void Bearer app-token', $this->line(0));
        $this->assertSame([], $this->sentJson(0));
        $this->assertSame('2026-11-20T10:00:00.000000Z', $voided->data->cancelled_at);
        $this->assertSame(409, $again->status);
        $this->assertSame('label_cancelled', $again->data->error);
    }
}
