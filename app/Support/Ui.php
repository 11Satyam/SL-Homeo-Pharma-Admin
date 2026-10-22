<?php

namespace App\Support;

/**
 * Plain-language names for codes Fmt::status() does not cover: stock movement
 * types, location types, payment methods and roles. Statuses go through Fmt::status().
 */
final class Ui
{
    public const LABELS = [
        // stock movement types
        'opening' => 'Opening stock', 'receipt' => 'Stock received', 'transfer_dispatch' => 'Sent (in transit)',
        'transfer_receive' => 'Transfer received', 'transfer_damage' => 'Damaged in transit', 'sale' => 'Sale (delivered)',
        'return' => 'Shop return', 'return_damaged' => 'Shop return (damaged)', 'damage_adjustment' => 'Damage write-off', 'reversal' => 'Correction (undo)',
        // location types
        'warehouse' => 'Warehouse', 'distributor' => 'Distributor', 'transit' => 'In transit', 'customer' => 'Sold to shops', 'external' => 'Supplier', 'damaged' => 'Damaged',
        // payment methods
        'cash' => 'Cash', 'upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'other' => 'Other',
        // roles
        'admin' => 'Admin', 'inventory_manager' => 'Inventory manager', 'accounts_manager' => 'Accounts manager', 'staff' => 'Field staff', 'viewer' => 'Viewer (read-only)',
    ];

    public const ROLE_HELP = [
        'admin' => 'Full control: warehouses, distributors, approvals and corrections.',
        'inventory_manager' => 'Adds products, receives stock into warehouses, sends stock to distributors.',
        'accounts_manager' => 'Verifies collected payments and reviews staff expenses.',
        'distributor' => 'Owner login for one distributor: shops, staff, orders, collections.',
        'staff' => 'Field staff of one distributor: takes orders, delivers, collects payments.',
        'viewer' => 'Can look at everything but cannot change anything (auditors).',
    ];

    /** Roles that must belong to a distributor. */
    public const DISTRIBUTOR_ROLES = ['distributor', 'staff'];

    public const TRANSFER_STEPS = ['draft', 'approved', 'in_transit', 'received'];

    public const ORDER_STEPS = ['confirmed', 'packed', 'out_for_delivery', 'delivered'];

    public static function label(?string $code): string
    {
        if ($code === null) {
            return '';
        }

        return self::LABELS[$code] ?? Fmt::status($code)[1];
    }

    /** Index of $status in a stepper; partial states sit on the step they are working toward. */
    public static function stepIndex(array $steps, string $status): int
    {
        $status = ['partially_received' => 'in_transit', 'partially_delivered' => 'delivered'][$status] ?? $status;
        $i = array_search($status, $steps, true);

        return $i === false ? -1 : $i;
    }
}
