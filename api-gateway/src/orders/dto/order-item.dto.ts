import { IsInt, Max, Min } from 'class-validator';

export class OrderItemDto {
  @IsInt()
  @Min(1)
  productId: number;

  @IsInt()
  @Min(1)
  @Max(100)
  quantity: number;
}
