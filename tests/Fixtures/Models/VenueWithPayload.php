<?php

namespace XLaravel\Embedding\Driver\SqlServer\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Embedding\Attributes\EmbedOn;
use XLaravel\Embedding\Attributes\EmbedPayload;
use XLaravel\Embedding\Concerns\Embeddable;
use XLaravel\Embedding\Contracts\HasEmbeddings;

#[EmbedOn('name')]
#[EmbedPayload(['province_id', 'category_id', 'active', 'code'])]
class VenueWithPayload extends Model implements HasEmbeddings
{
    use Embeddable;

    protected $table = 'venues';

    protected $fillable = ['name', 'description', 'province_id', 'category_id', 'active', 'code'];

    protected $casts = [
        'province_id' => 'integer',
        'category_id' => 'integer',
        'active' => 'boolean',
    ];

    public function toEmbeddingText(string $slot = 'default'): string
    {
        return $this->name;
    }
}
