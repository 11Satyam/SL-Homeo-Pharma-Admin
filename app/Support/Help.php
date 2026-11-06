<?php

namespace App\Support;

use App\Models\User;

/**
 * All in-app guidance in one place: the "How this page works" panel on every
 * screen, the role guides, the worked example and the FAQ on the Help page.
 * Keys of PAGES are route names. 'roles' limits an entry to those roles (admin always sees all).
 */
final class Help
{
    public const PAGES = [
        'erp.dashboard' => [
            'title' => 'Dashboard',
            'what' => 'Your starting point. "Getting started" shows the setup steps still to do, "To do now" lists work waiting for you, and the figures below show stock, sales and money owed.',
            'steps' => ['Work through "To do now" from top to bottom; each item has a button that takes you straight to it.', 'Use "Quick actions" to start new work.', 'Watch the numbers for low stock, money owed by shops and unverified collections.'],
        ],
        'erp.products' => [
            'title' => 'Products',
            'what' => 'The list of items you sell, with price and tax. Every order and invoice takes its price from here, so keep it correct.',
            'steps' => ['Click "Add a product" and fill the name, a short unique code (SKU) and the selling price.', 'Set a "Low-stock alert" level; the Dashboard warns you when stock falls to that number.', 'Use "Edit" to change a price. Old invoices keep the price they were issued with.'],
            'example' => 'Paracetamol 500mg, SKU PARA500, unit strip, selling price 10.00, tax 0%, low-stock alert at 20.',
            'next' => ['Receive stock for your products', 'erp.stock'],
        ],
        'erp.stock' => [
            'title' => 'Warehouse & Stock',
            'what' => 'Shows how much of each product is in every warehouse and distributor. "Available" is what can still be sold: stock on hand minus stock already promised to open orders.',
            'steps' => ['First time: create a warehouse (for example "Main Warehouse").', 'Use "Receive stock" when goods arrive from a supplier, or "Opening stock" to enter what you already have.', 'Use "Write off damaged stock" for broken or expired items. Stock is never edited directly; every change is recorded in the Stock Ledger.'],
            'example' => 'Receive 100 strips of Paracetamol into Main Warehouse. The warehouse now shows On hand 100, Available 100.',
            'next' => ['Send stock to a distributor', 'erp.transfers'],
        ],
        'erp.ledger' => [
            'title' => 'Stock Ledger',
            'what' => 'Every stock change ever made, newest first: what moved, how much, from where to where and who did it. Nothing is ever deleted.',
            'steps' => ['Filter by product or type of change to find an entry.', 'Download a spreadsheet (CSV) for your accountant.', 'Admins can correct a wrong entry with "Undo". This adds an opposite entry, so history stays complete.'],
        ],
        'erp.transfers' => [
            'title' => 'Stock Transfers',
            'what' => 'Moves stock from a warehouse to a distributor (or between locations). A transfer goes through 4 steps, and stock only moves at Dispatch and Receive.',
            'steps' => ['Create: choose From, To and quantities. Nothing moves yet (Draft).', 'Approve: an admin checks and approves it. Admins can tick "Approve now" while creating.', 'Dispatch: goods leave the warehouse. Stock moves to "In transit".', 'Receive: the distributor counts what arrived and enters good and damaged quantities. Good stock becomes available to sell.'],
            'example' => 'Send 20 of 100 strips: after Dispatch the warehouse shows 80 and In transit 20. After the distributor receives 20, they have 20 to sell.',
            'next' => ['Take an order from a shop', 'erp.orders'],
        ],
        'erp.distributors' => [
            'title' => 'Distributors',
            'what' => 'The businesses you supply. Registering one creates its login, its own stock location and emails the owner a link to set a password.',
            'steps' => ['Click "Register a distributor" and fill the business name and the owner\'s email.', 'The owner gets an email to set their password, then signs in to manage their shops and staff.', 'Next, send them stock with a transfer.', 'Deactivating a distributor blocks all of its users from signing in; its history is kept.'],
            'example' => 'Sharma Distributors, contact Anil Sharma, email anil@sharma.example, area Andheri.',
            'next' => ['Send stock to a distributor', 'erp.transfers'],
        ],
        'erp.distributors.show' => [
            'title' => 'Distributor details',
            'what' => 'The profile and full stock history of one distributor. Every figure comes from the Stock Ledger, so it always matches what was posted.',
            'steps' => ['Check the cards for allocated, available, sold, in transit, returned and damaged stock.', 'Open Stock movements to filter the history by product, type or date, then use the eye icon to trace one entry.', 'Use Allocate stock or Transfer out to send stock, and Damage / adjust to record a loss with a reason.'],
            'example' => 'Search SO-2611 to find every stock entry for that order.',
            'next' => ['Send stock to a distributor', 'erp.transfers'],
        ],
        'erp.users' => [
            'title' => 'Users & Staff',
            'what' => 'People who can sign in. You never set someone\'s password: they get an email link and choose their own.',
            'steps' => ['Click "Add a person", enter name and email and pick what they do (their role).', 'Field staff and distributor owners must belong to a distributor.', 'Use "Suspend" to block someone immediately; they are also signed out of the mobile app.'],
            'example' => 'Distributor adds field staff: Ravi Kumar, ravi@sharma.example. Ravi gets an email, sets a password and can start taking orders.',
        ],
        'erp.shops' => [
            'title' => 'Shops',
            'what' => 'Retail shops (your customers). Each shop belongs to one distributor and has its own credit terms and statement.',
            'steps' => ['Click "Add a shop". Only the name is required.', '"Payment terms" is how many days the shop gets to pay. An invoice becomes overdue after that.', 'Open a shop to see what it owes and to record a payment.'],
            'example' => 'City Medical, owner Mr. Patel, area Andheri West, payment terms 15 days.',
            'next' => ['Take an order', 'erp.orders'],
        ],
        'erp.shop' => [
            'title' => 'Shop statement',
            'what' => 'Everything about one shop: invoices, what it still owes and payments received.',
            'steps' => ['Check the amount owed at the top.', 'To record money received, enter the amount and how it was paid in "Record a payment". It is applied to the oldest unpaid invoices automatically.', 'Red rows are overdue invoices.'],
            'example' => 'Shop owes 50.00 for one invoice. Record 50.00 cash. The invoice is now paid. The payment shows "Collected" until a manager verifies the money.',
        ],
        'erp.orders' => [
            'title' => 'Orders & Deliveries',
            'what' => 'Shop orders from start to finish. Taking an order sets the stock aside (reserved); stock is only taken out when the delivery is recorded, which also creates the invoice.',
            'steps' => ['Click "New order", pick the shop and type quantities. You can see available stock and the total as you type.', 'Optional: mark the order Packed, then Out for delivery.', 'At the shop, open "Record delivery" and confirm the delivered quantities. An invoice is created automatically.', 'If the shop refuses, use "Delivery failed"; to drop the order use "Cancel". Reserved stock is released.'],
            'example' => 'City Medical orders 5 strips. Distributor shows On hand 20, Available 15. After delivery: On hand 15, and invoice for 5 x 10.00 = 50.00 is issued.',
            'next' => ['See invoices and money owed', 'erp.invoices'],
        ],
        'erp.invoices' => [
            'title' => 'Invoices & Outstanding',
            'what' => 'Invoices are created automatically when an order is delivered. This page shows what every shop still owes and how late it is.',
            'steps' => ['Use the tabs to see Unpaid or Overdue invoices.', 'Click "Collect" to go to the shop and record a payment.', 'Click the invoice number to print it or save it as PDF.'],
        ],
        'erp.invoice' => [
            'title' => 'Invoice',
            'what' => 'A printable invoice with what was paid and what is still owed.',
            'steps' => ['Click "Print / Save PDF" to give a copy to the shop.', 'Use "Collect payment" to record money against this shop.'],
        ],
        'erp.payments' => [
            'title' => 'Payments & Collections',
            'what' => 'Money collected from shops. Staff record it as "Collected"; a manager then checks the cash or bank and clicks "Verify".',
            'steps' => ['To record a new payment, pick the shop under "Record a payment".', 'Managers: use the "Needs verifying" tab, check the money really arrived, then click Verify.', '"Not applied" is money not yet set against an invoice (for example an advance).'],
            'example' => 'Ravi collects 50.00 cash from City Medical. It shows "Collected". The distributor counts the cash in the evening and clicks Verify.',
        ],
        'erp.expenses' => [
            'title' => 'Staff Expenses',
            'what' => 'Expenses claimed by field staff from the mobile app (fuel, travel, etc.). Review each one and approve or reject it.',
            'steps' => ['Open "Waiting for review".', 'Check the amount and the receipt photo.', 'Click Approve, or write a reason and click Reject. You cannot review your own expense.'],
        ],
        'erp.attendance' => [
            'title' => 'Staff Attendance',
            'what' => 'Check-in and check-out times recorded by field staff in the mobile app, with the location their phone reported.',
            'steps' => ['Filter by date or staff member.', '"Outside" means the phone was outside the office area set for the distributor.', 'Admins set the office area on the Distributors page.'],
        ],
        'erp.audit' => [
            'title' => 'Audit Logs',
            'what' => 'A record of who did what and when: sign-ins, approvals, stock and money changes. Use it to answer "who changed this?".',
            'steps' => ['Filter by the start of an action name, for example "stock." or "payment."'],
        ],
        'erp.help' => [
            'title' => 'Help Center',
            'what' => 'Guides, a full worked example and answers to common questions. Use the search box to find anything.',
            'steps' => [],
        ],
    ];

    /** Step-by-step first-time guides per role: [title, explanation, route]. */
    public const GUIDES = [
        'admin' => [
            ['Create a warehouse', 'Where your stock is kept, e.g. "Main Warehouse".', 'erp.stock'],
            ['Add your products', 'Name, code and selling price.', 'erp.products'],
            ['Receive opening stock', 'Enter how much of each product you have.', 'erp.stock'],
            ['Register a distributor', 'Creates their login and stock location.', 'erp.distributors'],
            ['Send stock to the distributor', 'Create, approve and dispatch a transfer.', 'erp.transfers'],
            ['Distributor receives the stock', 'They confirm the quantities that arrived.', 'erp.transfers'],
            ['Add shops and field staff', 'Or let the distributor do this.', 'erp.shops'],
            ['Track daily work on the Dashboard', 'Approvals, low stock, money owed.', 'erp.dashboard'],
        ],
        'inventory_manager' => [
            ['Add your products', 'Name, code, price and low-stock level.', 'erp.products'],
            ['Receive stock into a warehouse', 'Every arrival from a supplier.', 'erp.stock'],
            ['Create transfers to distributors', 'An admin approves them.', 'erp.transfers'],
            ['Dispatch approved transfers', 'Stock moves to "In transit".', 'erp.transfers'],
            ['Write off damaged stock', 'Broken or expired items.', 'erp.stock'],
        ],
        'accounts_manager' => [
            ['Verify collected payments', 'Check cash/bank, then click Verify.', 'erp.payments'],
            ['Review staff expenses', 'Approve or reject with a reason.', 'erp.expenses'],
            ['Follow up overdue invoices', 'See who owes and how late.', 'erp.invoices'],
        ],
        'distributor' => [
            ['Receive stock sent to you', 'Count what arrived and confirm it.', 'erp.transfers'],
            ['Add your field staff', 'They get an email to set a password.', 'erp.users'],
            ['Add your shops', 'Name, area and payment terms.', 'erp.shops'],
            ['Take an order', 'Stock is set aside for the shop.', 'erp.orders'],
            ['Record the delivery', 'Stock is deducted, invoice created.', 'erp.orders'],
            ['Record payments from shops', 'From the shop\'s page.', 'erp.payments'],
            ['Verify payments and review expenses', 'Every evening.', 'erp.payments'],
        ],
        'staff' => [
            ['Check what stock is available', 'Before promising a shop.', 'erp.stock'],
            ['Take an order', 'Pick the shop and quantities.', 'erp.orders'],
            ['Deliver and record it', 'Confirm the quantities handed over.', 'erp.orders'],
            ['Collect payment', 'Record cash/UPI/cheque on the shop page.', 'erp.payments'],
            ['Attendance and expenses', 'Use the mobile app: check in, check out, claim expenses.', null],
        ],
        'viewer' => [
            ['Look at stock', 'Every warehouse and distributor.', 'erp.stock'],
            ['Look at stock history', 'Every change, downloadable.', 'erp.ledger'],
            ['Look at the activity log', 'Who did what and when.', 'erp.audit'],
        ],
    ];

    /** The worked example: [who, action, what happens, balances after]. */
    public const EXAMPLE = [
        ['Admin', 'Receive 100 strips of Paracetamol into Main Warehouse', 'Stock enters the system from the supplier.', 'Warehouse 100'],
        ['Admin', 'Create a transfer of 20 strips to Sharma Distributors and approve it', 'Nothing moves yet; it is only a plan.', 'Warehouse 100'],
        ['Admin', 'Dispatch the transfer', 'Goods are on the truck.', 'Warehouse 80 · In transit 20'],
        ['Distributor', 'Receive the transfer: 20 good, 0 damaged', 'Stock arrives and can now be sold.', 'Warehouse 80 · Distributor 20'],
        ['Field staff', 'Take an order from City Medical for 5 strips', '5 strips are set aside (reserved) for the shop.', 'Distributor on hand 20 · available 15'],
        ['Field staff', 'Record the delivery of 5 strips', 'Stock is deducted and invoice INV-… for 50.00 is created, due in 15 days.', 'Distributor 15 · Shop owes 50.00'],
        ['Field staff', 'Record 50.00 cash collected from City Medical', 'Invoice is paid. Payment shows "Collected".', 'Shop owes 0.00'],
        ['Distributor', 'Count the cash and click Verify', 'The money is confirmed as received.', 'Payment Verified'],
        ['Distributor', 'Later, City Medical returns 2 strips in good condition', 'Stock goes back to the distributor and a credit note of 20.00 is issued.', 'Distributor 17'],
    ];

    /** [question, answer, roles|null]. */
    public const FAQ = [
        ['Why can\'t I see a product when taking an order?', 'Only active products are listed. If it shows "0 available", the distributor has no free stock: receive a transfer first, or wait for open orders to be delivered or cancelled.', null],
        ['What is the difference between "On hand" and "Available"?', 'On hand is everything physically there. Available is on hand minus stock already promised to open orders. You can only sell what is available.', null],
        ['When is stock taken out of the distributor?', 'Only when the delivery is recorded. Taking an order just sets the stock aside so nobody else sells it.', null],
        ['A transfer is "Approved" but stock has not moved. Why?', 'Approving is only permission. Stock moves when someone clicks Dispatch, and arrives when the distributor clicks Receive.', null],
        ['Some items arrived broken. What do I do?', 'When receiving the transfer, enter the broken quantity in "Damaged". It is recorded as damaged in transit and not added to your stock.', ['distributor', 'inventory_manager']],
        ['Only part of the transfer arrived.', 'Receive what arrived. The rest stays "In transit" and you can receive it later from the same transfer.', ['distributor', 'inventory_manager']],
        ['The shop accepted fewer items than ordered.', 'In "Record delivery", change the quantity to what was actually delivered. The invoice is made for that amount and the rest of the stock is released.', ['distributor', 'staff']],
        ['The shop refused the delivery.', 'Click "Delivery failed" and write the reason. The reserved stock is released and no invoice is created.', ['distributor', 'staff']],
        ['A shop returned goods after delivery.', 'Open the order, use "Record a return", enter good and damaged quantities. Good items go back to stock, and a credit note reduces what the shop owes.', ['distributor']],
        ['How is a payment applied to invoices?', 'By default it pays the oldest unpaid invoices first. Untick "Apply automatically" to choose amounts yourself. Anything left over is kept as "Not applied".', null],
        ['Why does a payment say "Collected" and not "Verified"?', 'Collected means staff took the money. Verified means a manager checked it reached the cash box or bank. Verify every evening.', null],
        ['I entered the wrong stock quantity.', 'Stock entries cannot be edited, so history stays honest. Ask an admin to "Undo" the entry on the Stock Ledger page, then enter it again correctly.', null],
        ['I clicked Save twice. Will it be recorded twice?', 'No. Orders, payments and stock entries are protected against double submission; the second click is ignored.', null],
        ['How do field staff mark attendance or claim expenses?', 'In the mobile app: check in at the start of the day, check out at the end, and submit expenses with a receipt photo. Managers review them here.', null],
        ['Someone left the company.', 'On "Users & Staff", click Suspend next to their name. They are signed out everywhere immediately; their records stay.', ['distributor']],
        ['I forgot my password.', 'On the sign-in page click "Forgot password?" and follow the email link.', null],
        ['A staff member did not get the activation email.', 'Ask them to use "Forgot password?" on the sign-in page with the same email address. It sends a fresh link.', ['distributor']],
        ['What happens when I deactivate a distributor?', 'All of their logins are blocked. Their stock, shops, invoices and history are kept and come back if you activate them again.', []],
    ];

    public static function page(?string $route): ?array
    {
        return $route ? (self::PAGES[$route] ?? null) : null;
    }

    public static function guide(User $u): array
    {
        return self::GUIDES[$u->role] ?? [];
    }

    public static function faq(User $u): array
    {
        return array_values(array_filter(self::FAQ, fn ($f) => $f[2] === null || $u->hasRole('admin', ...$f[2])));
    }
}
