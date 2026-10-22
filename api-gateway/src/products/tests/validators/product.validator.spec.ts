import { BadRequestException, ConflictException } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { CategoriesService } from '../../../categories/categories.service.js';
import { ProductQueryDto } from '../../dto/product-query.dto.js';
import type { ProductsRepository } from '../../products.repository.js';
import { ProductValidator } from '../../validators/product.validator.js';

describe('ProductValidator', () => {
  let validator: ProductValidator;
  let categoriesService: { existsById: ReturnType<typeof vi.fn> };
  let products: {
    existsBySku: ReturnType<typeof vi.fn>;
    existsBySlug: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    categoriesService = { existsById: vi.fn().mockResolvedValue(true) };
    products = {
      existsBySku: vi.fn().mockResolvedValue(false),
      existsBySlug: vi.fn().mockResolvedValue(false),
    };

    const moduleRef = await Test.createTestingModule({
      providers: [
        ProductValidator,
        { provide: CategoriesService, useValue: categoriesService },
      ],
    }).compile();

    validator = moduleRef.get(ProductValidator);
  });

  const asRepository = () => products as unknown as ProductsRepository;
  const filters = (values: Partial<ProductQueryDto>) =>
    Object.assign(new ProductQueryDto(), values);

  describe('ensureValidPriceRange', () => {
    it('throws when minPrice is greater than maxPrice', () => {
      expect(() =>
        validator.ensureValidPriceRange(
          filters({ minPrice: 50, maxPrice: 10 }),
        ),
      ).toThrow(BadRequestException);
    });

    it.each([
      { minPrice: 10, maxPrice: 10 },
      { minPrice: 10, maxPrice: 50 },
      { minPrice: 50 },
      { maxPrice: 10 },
      {},
    ])('accepts %o', (range) => {
      expect(() =>
        validator.ensureValidPriceRange(filters(range)),
      ).not.toThrow();
    });
  });

  describe('resolveSlug', () => {
    it('keeps an explicit slug', () => {
      expect(
        validator.resolveSlug({
          name: 'Anything',
          slug: 'my-slug',
          sku: 'A',
          price: 1,
        }),
      ).toBe('my-slug');
    });

    it('generates a slug from the name', () => {
      expect(
        validator.resolveSlug({
          name: 'Café Crème – 50% OFF!',
          sku: 'A',
          price: 1,
        }),
      ).toBe('cafe-creme-50-off');
    });

    it('throws when no slug can be generated from the name', () => {
      expect(() =>
        validator.resolveSlug({ name: '!!!', sku: 'A', price: 1 }),
      ).toThrow(BadRequestException);
    });
  });

  describe('ensureHasChanges', () => {
    it('throws for an empty change set', () => {
      expect(() => validator.ensureHasChanges({})).toThrow(BadRequestException);
    });

    it('accepts a change set with a null value', () => {
      expect(() =>
        validator.ensureHasChanges({ description: null }),
      ).not.toThrow();
    });
  });

  describe('ensureCategoryExists', () => {
    it.each([undefined, null])(
      'skips the lookup for %s',
      async (categoryId) => {
        await validator.ensureCategoryExists(categoryId);

        expect(categoriesService.existsById).not.toHaveBeenCalled();
      },
    );

    it('accepts an existing category', async () => {
      await expect(validator.ensureCategoryExists(3)).resolves.toBeUndefined();
      expect(categoriesService.existsById).toHaveBeenCalledWith(3);
    });

    it('throws BadRequest for an unknown category', async () => {
      categoriesService.existsById.mockResolvedValue(false);

      await expect(validator.ensureCategoryExists(999)).rejects.toThrow(
        BadRequestException,
      );
    });
  });

  describe('ensureSkuAvailable', () => {
    it('accepts a free SKU and passes the excluded id to the repository', async () => {
      await validator.ensureSkuAvailable(asRepository(), 'MK-001', 7);

      expect(products.existsBySku).toHaveBeenCalledWith('MK-001', 7);
    });

    it('throws Conflict for a taken SKU', async () => {
      products.existsBySku.mockResolvedValue(true);

      await expect(
        validator.ensureSkuAvailable(asRepository(), 'MK-001'),
      ).rejects.toThrow(ConflictException);
    });
  });

  describe('ensureSlugAvailable', () => {
    it('accepts a free slug and passes the excluded id to the repository', async () => {
      await validator.ensureSlugAvailable(asRepository(), 'keyboard', 7);

      expect(products.existsBySlug).toHaveBeenCalledWith('keyboard', 7);
    });

    it('throws Conflict for a taken slug', async () => {
      products.existsBySlug.mockResolvedValue(true);

      await expect(
        validator.ensureSlugAvailable(asRepository(), 'keyboard'),
      ).rejects.toThrow(ConflictException);
    });
  });
});
