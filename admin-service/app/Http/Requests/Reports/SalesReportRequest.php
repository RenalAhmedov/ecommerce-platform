<?php

namespace App\Http\Requests\Reports;

use App\Enums\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
{
    private const DEFAULT_DAYS = 30;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::enum(ReportPeriod::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function period(): ReportPeriod
    {
        return $this->enum('period', ReportPeriod::class) ?? ReportPeriod::Day;
    }

    public function firstDay(): CarbonImmutable
    {
        return $this->filled('date_from')
            ? CarbonImmutable::parse($this->input('date_from'))
            : $this->lastDay()->subDays(self::DEFAULT_DAYS - 1);
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->filled('date_to') ? CarbonImmutable::parse($this->input('date_to')) : CarbonImmutable::today();
    }
}
