import type { PaginationMetaDto } from '../dto/pagination-meta.dto.js';

export interface Paginated<T> {
  data: T[];
  meta: PaginationMetaDto;
}
