import { ApiProperty } from '@nestjs/swagger';

export class ErrorResponseDto {
  statusCode: number;
  error: string;

  @ApiProperty({
    oneOf: [{ type: 'string' }, { type: 'array', items: { type: 'string' } }],
  })
  message: string | string[];

  path: string;
  timestamp: string;
}
