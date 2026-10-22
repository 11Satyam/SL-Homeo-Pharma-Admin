<?php

namespace App\Support;

/** Presentation helpers for the admin UI: one place for currency, compact numbers and status vocabulary. */
final class Fmt
{
    /** Status => [tone, label]. Tones map to .badge-s variants; a label always accompanies the colour. */
    private const STATUS = [
        'active' => ['ok', 'Active'], 'inactive' => ['neutral', 'Inactive'], 'suspended' => ['bad', 'Suspended'],
        'draft' => ['neutral', 'Draft'], 'approved' => ['info', 'Approved'], 'in_transit' => ['violet', 'In transit'],
        'partially_received' => ['warn', 'Partially received'], 'received' => ['ok', 'Received'],
        'confirmed' => ['info', 'Confirmed'], 'packed' => ['violet', 'Packed'], 'out_for_delivery' => ['warn', 'Out for delivery'],
        'delivered' => ['ok', 'Delivered'], 'partially_delivered' => ['warn', 'Partially delivered'],
        'cancelled' => ['neutral', 'Cancelled'], 'failed' => ['bad', 'Failed'],
        'collected' => ['warn', 'Pending verification'], 'verified' => ['ok', 'Verified'],
        'submitted' => ['warn', 'Awaiting review'], 'rejected' => ['bad', 'Rejected'],
        'paid' => ['ok', 'Paid'], 'overdue' => ['bad', 'Overdue'], 'partial' => ['warn', 'Part paid'], 'unpaid' => ['info', 'Unpaid'],
        'checked_in' => ['info', 'Checked in'], 'checked_out' => ['ok', 'Checked out'],
        'low' => ['bad', 'Low stock'], 'healthy' => ['ok', 'Healthy'], 'out' => ['bad', 'Out of stock'],
    ];

    public static function money($v, int $dp = 2): string
    {
        $v = (float) $v;

        return ($v < 0 ? '-' : '').'₹'.number_format(abs($v), $dp);
    }

    /** Indian-style compact currency: ₹950, ₹12.4K, ₹3.2L, ₹1.1Cr. */
    public static function moneyShort($v): string
    {
        $v = (float) $v;
        $a = abs($v);
        $s = match (true) {
            $a >= 1e7 => round($a / 1e7, 1).'Cr',
            $a >= 1e5 => round($a / 1e5, 1).'L',
            $a >= 1e3 => round($a / 1e3, 1).'K',
            default => number_format($a, 0),
        };

        return ($v < 0 ? '-' : '').'₹'.$s;
    }

    public static function num($v): string
    {
        return number_format((float) $v);
    }

    public static function status(?string $s): array
    {
        return self::STATUS[$s] ?? ['neutral', ucfirst(str_replace('_', ' ', (string) $s))];
    }

    public static function label(?string $s): string
    {
        return ucfirst(str_replace('_', ' ', (string) $s));
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    /** Stable avatar colour per name (accessible on white text). */
    public static function avatarColor(?string $name): string
    {
        $palette = ['#2563eb', '#7c3aed', '#0e7490', '#b45309', '#be185d', '#15803d', '#4338ca', '#c2410c'];

        return $palette[crc32((string) $name) % count($palette)];
    }
}
