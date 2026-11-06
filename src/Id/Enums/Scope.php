<?php

namespace Anikeen\Id\Enums;

class Scope
{
    const USER = 'user';
    const USER_READ = 'user:read';

    const ADDRESSES = 'addresses';
    const ADDRESSES_READ = 'addresses:read';

    const BILLING = 'billing';
    const BILLING_READ = 'billing:read';
    const BILLING_CLIENT = 'billing:client';

    const INVOICES = 'invoices';
    const INVOICES_READ = 'invoices:read';
    const INVOICES_CLIENT = 'invoices:client';

    const ORDERS = 'orders';
    const ORDERS_READ = 'orders:read';
    const ORDERS_CLIENT = 'orders:client';

    const PAYMENT_METHODS = 'payment-methods';
    const PAYMENT_METHODS_READ = 'payment-methods:read';

    const SUBSCRIPTIONS = 'subscriptions';
    const SUBSCRIPTIONS_READ = 'subscriptions:read';
    const SUBSCRIPTIONS_CLIENT = 'subscriptions:client';

    const TRANSACTIONS = 'transactions';
    const TRANSACTIONS_READ = 'transactions:read';
    const TRANSACTIONS_CLIENT = 'transactions:client';

    // The user's own shared preferences (`global`). PREFERENCES_CLIENT labels an app token for
    // the preferences of any user; access to a namespace is granted by Anikeen, not by the scope.
    const PREFERENCES = 'preferences';
    const PREFERENCES_READ = 'preferences:read';
    const PREFERENCES_CLIENT = 'preferences:client';

    const SSH_KEYS = 'ssh-keys';
    const SSH_KEYS_READ = 'ssh-keys:read';

    // Products of the Anikeen Console (servers, floating IPs, hosting, domains, zones, object storage),
    // read-only. Changing products needs `user`; there is no full `products` scope.
    const PRODUCTS_READ = 'products:read';

    // Reseller API of the Anikeen Console. A full scope includes reading; `user` and `*` do not
    // open these endpoints, a token must carry the reseller scope itself.
    const RESELLER_CUSTOMERS = 'reseller-customers';
    const RESELLER_CUSTOMERS_READ = 'reseller-customers:read';
    const RESELLER_GRANTS = 'reseller-grants';
    const RESELLER_ORDERS = 'reseller-orders';

    const ADMIN = 'admin';
}
