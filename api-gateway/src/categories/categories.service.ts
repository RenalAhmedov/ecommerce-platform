import { Injectable } from '@nestjs/common';
import { CategoriesRepository } from './categories.repository.js';

@Injectable()
export class CategoriesService {
  constructor(private readonly categoriesRepository: CategoriesRepository) {}

  existsById(id: number): Promise<boolean> {
    return this.categoriesRepository.existsById(id);
  }
}
