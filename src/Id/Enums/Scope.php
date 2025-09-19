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

    const SSH_KEYS = 'ssh-keys';
    const SSH_KEYS_READ = 'ssh-keys:read';

    const ADMIN = 'admin';
}
