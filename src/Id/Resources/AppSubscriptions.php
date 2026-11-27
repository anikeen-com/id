<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Contracts\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Subscriptions of a user, managed with the app's own token (client credentials, scope
 * `subscriptions:client`) instead of the user's token, so they keep working when the user's
 * login expired. The app has to be trusted by the ID service. Nothing is requested until a
 * method is called.
 */
class AppSubscriptions
{
    public function __construct(protected Billable|Model $billable)
    {
    }

    /**
     * The user's Anikeen ID (see AnikeenId::useUserIdField()).
     */
    public function userId(): string
    {
        $userId = $this->billable->{AnikeenId::getUserIdField()} ?? null;

        if (empty($userId)) {
            throw new InvalidArgumentException(sprintf('The billable has no Anikeen ID in [%s].', AnikeenId::getUserIdField()));
        }

        return (string) $userId;
    }

    /**
     * Create a subscription for the user (it is billed only after checkout()).
     *
     * Either inline (name, unit, price) or from a service of the app's catalog (`product_id`,
     * optionally `coupon_code`): then Anikeen ID takes name, unit and monthly cap from the product
     * and computes the price (list price → offer → reseller discount of `group` or coupon, see
     * AppInventory::price()). That price is the contract price: catalog changes apply to new
     * subscriptions only. The subscription has `product_id`, `coupon_id` and `pricing` (how the
     * price was made).
     *
     * @param array{
     *     group?: null|string,
     *     product_id?: null|string,
     *     coupon_code?: null|string,
     *     first_period?: null|string,
     *     name?: string,
     *     description?: null|string,
     *     unit?: string,
     *     price?: float|string,
     *     monthly_cap?: null|float|string,
     *     predecessor_id?: null|string,
     *     vat_rate?: float,
     *     payload?: null|array,
     *     ends_at?: null|string,
     *     trial_days?: null|int,
     *     trial_ends_at?: null|string,
     *     trial_once?: bool,
     *     trial_payment_method?: null|string,
     *     discount?: null|array{label: string, percent_off?: float|string, amount_off?: float|string, duration: string, duration_in_periods?: int},
     *     idempotency_key?: null|string
     * } $attributes With an idempotency_key (max. 191 characters), a retried call returns the
     *               subscription created by the first one instead of a duplicate.
     *               product_id: a service of the app's catalog (prohibits unit, price, monthly_cap
     *               and discount); name and description default to the product's, sent ones win
     *               (e.g. the server's own name). coupon_code: a coupon the customer entered (only
     *               with product_id). first_period (only with product_id, FirstPeriod): setup
     *               (default) or transfer start a service with a setup/transfer price at its
     *               dearer first-period price, then Anikeen ID schedules the renewal price itself
     *               (`scheduled_change.first_period`); none for a service that already runs
     *               (takeovers always). Not with a trial (422 first_period_with_trial).
     *               Without product_id, name, unit and price are required.
     *               monthly_cap: net, at most this per calendar month (hour and day units only,
     *               at most 2 decimals, not below price; null = no cap).
     *               predecessor_id: the subscription this one replaces (a resize; same user and
     *               app, checked out, not cancelled): both share the monthly cap within a month.
     *               trial_days: a free trial of 1–365 days from the checkout on (status `trial`,
     *               nothing billed; the first period is billed when it ends), or
     *               trial_ends_at: a fixed end of the trial (ISO 8601, in the future), not both.
     *               trial_once: refuse the trial if the user already had one with the app in the
     *               same group (422 here, 409 "trial_already_used" on checkout).
     *               trial_payment_method: TrialPaymentMethod::REQUIRED (default) or ::OPTIONAL,
     *               only with a trial: with `optional` the user may confirm it without a payment
     *               method, and without one at the trial's end it is cancelled instead of billed.
     *               discount: e.g. a coupon, as a negative line next to each billed period:
     *               label (shown to the user, max. 100 characters), percent_off (0.01–100) or
     *               amount_off (net per period, at most the price), duration `once` (the first
     *               billed period), `repeating` (duration_in_periods billed periods) or
     *               `forever`. Not combinable with a monthly_cap.
     *
     * @throws ApiException 422 validation or a PricingError code (isPricingError(), e.g.
     *                      coupon_expired), 409 "idempotency_key_conflict", …
     */
    public function create(array $attributes): AppSubscription
    {
        return new AppSubscription(AppRequest::send('POST', 'v1/client/subscriptions', [
            ...$attributes,
            'user_id' => $this->userId(),
        ])->data);
    }

    /**
     * Find one of the user's subscriptions; null when it doesn't exist (or belongs to someone else).
     *
     * @throws ApiException
     */
    public function find(string $id): ?AppSubscription
    {
        try {
            $subscription = new AppSubscription(AppRequest::send('GET', sprintf('v1/client/subscriptions/%s', $id))->data);
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }

        return $subscription->user_id === $this->userId() ? $subscription : null;
    }

    /**
     * All of the user's subscriptions, optionally filtered.
     *
     * @param array{group?: string, active?: bool, cancelled?: bool} $filters
     * @return list<AppSubscription>
     *
     * @throws ApiException
     */
    public function all(array $filters = []): array
    {
        $subscriptions = [];
        $page = 1;

        do {
            $result = AppRequest::send('GET', 'v1/client/subscriptions', null, [
                ...array_map(fn ($value) => is_bool($value) ? (int) $value : $value, $filters),
                'user_id' => $this->userId(),
                'per_page' => 100,
                'page' => $page,
            ]);

            foreach ($result->data as $data) {
                $subscriptions[] = new AppSubscription($data);
            }

            $lastPage = (int) ($result->meta->last_page ?? $page);
        } while ($page++ < $lastPage);

        return $subscriptions;
    }
}
