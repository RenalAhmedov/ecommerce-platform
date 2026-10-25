<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait NestPagination
{
    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->resource;

        return [
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];
    }
}
