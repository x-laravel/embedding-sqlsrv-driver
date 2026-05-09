<?php

namespace XLaravel\Embedding\Driver\SqlServer\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Embedding\Attributes\EmbedOn;
use XLaravel\Embedding\Concerns\Embeddable;
use XLaravel\Embedding\Contracts\HasEmbeddings;

#[EmbedOn(['title', 'body'])]
class Post extends Model implements HasEmbeddings
{
    use Embeddable;

    protected $fillable = ['title', 'body'];

    public function toEmbeddingText(): string
    {
        return $this->title.' '.$this->body;
    }
}
