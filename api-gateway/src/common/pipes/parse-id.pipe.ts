import { BadRequestException, Injectable, PipeTransform } from '@nestjs/common';

@Injectable()
export class ParseIdPipe implements PipeTransform<unknown, number> {
  transform(value: unknown): number {
    const id = Number(value);

    if (!Number.isSafeInteger(id) || id < 1) {
      throw new BadRequestException('id must be a positive integer');
    }

    return id;
  }
}
