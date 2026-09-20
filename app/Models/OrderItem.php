<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'order_id', 'line_number', 'item_kind', 'product_id', 'ticket_type_id',
        'session_id', 'description_snapshot', 'quantity', 'unit_price_minor', 'discount_minor',
        'tax_rate_bps', 'tax_minor', 'line_total_minor', 'currency', 'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }
}
