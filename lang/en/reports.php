<?php

return [
    'page_title' => 'Operational reports', 'description' => 'Reconcile committed financial and operational facts within your permitted branches.',
    'report_types' => 'Report types', 'types' => ['revenue' => 'Revenue', 'attendance' => 'Attendance', 'sessions' => 'Sessions', 'staff' => 'Staff activity'],
    'branch' => 'Branch', 'all_permitted_branches' => 'All permitted branches', 'from' => 'From', 'to' => 'To', 'status' => 'Status', 'all_statuses' => 'All statuses',
    'child_id' => 'Child ID', 'ticket_id' => 'Ticket ID', 'guardian_phone' => 'Guardian phone', 'staff_id' => 'Staff user ID',
    'sort' => 'Sort', 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'amount_desc' => 'Highest amount', 'amount_asc' => 'Lowest amount', 'apply' => 'Apply filters',
    'summary' => 'Reconciled summary', 'results' => 'Report results', 'empty' => 'No committed records match these filters.', 'export_csv' => 'Export filtered CSV',
    'generated' => 'Generated :time', 'context' => 'Branch context · Currency: :currency · Time zone: :timezone', 'currency_total' => 'Currency: :currency', 'mixed_context' => 'Mixed branch settings', 'minutes' => 'minutes', 'unknown' => 'Unknown', 'fix_filters' => 'Review the report filters.', 'range_too_large' => 'The synchronous report range cannot exceed 31 days.', 'unknown_age' => 'Unknown',
    'payment_methods' => ['cash' => 'Cash', 'unknown' => 'Unknown'], 'payment_statuses' => ['posted' => 'Posted'],
    'breakdowns' => ['title' => 'Revenue breakdowns', 'products' => 'Products and services', 'ticket_types' => 'Ticket types', 'payment_methods' => 'Payment methods', 'item' => 'Item', 'paid' => 'Paid', 'refunded' => 'Refunded', 'net' => 'Net'],
    'statuses' => ['active' => 'Active', 'pending_payment' => 'Pending payment', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
    'metrics' => ['paid_orders' => 'Paid orders', 'gross_minor' => 'Gross sales', 'discounts_minor' => 'Discounts', 'tax_minor' => 'Tax', 'paid_minor' => 'Payments', 'refunded_minor' => 'Refunds', 'net_minor' => 'Net recorded revenue', 'visits' => 'Visits', 'completed' => 'Completed', 'sessions' => 'Sessions', 'adjustment_minor' => 'Adjustments', 'actions' => 'Actions', 'actors' => 'Staff'],
    'columns' => [
        'revenue' => ['Receipt', 'Branch', 'Actor', 'Paid', 'Refunded', 'Net', 'Payment method', 'Occurred'],
        'attendance' => ['Session', 'Branch', 'Started', 'State', 'Age band'],
        'sessions' => ['Session', 'Branch', 'State', 'Duration', 'Extension', 'Adjustment', 'Final charge', 'Payment', 'Paid amount', 'Refunded', 'Receipt', 'Verification', 'Override reason', 'Started / ended'],
        'staff' => ['Occurred', 'Branch', 'Actor', 'Action', 'Subject'],
    ],
];
