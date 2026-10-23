<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\NestPagination;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    use NestPagination;

    public $collects = ProductResource::class;
}
