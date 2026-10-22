import type { PaginationQueryDto } from '../dto/pagination-query.dto.js';
import type { Paginated } from '../interfaces/paginated.interface.js';

export function toPaginated<T>(
  data: T[],
  total: number,
  { page, limit }: PaginationQueryDto,
): Paginated<T> {
  return {
    data,
    meta: { page, limit, total, totalPages: Math.ceil(total / limit) },
  };
}
