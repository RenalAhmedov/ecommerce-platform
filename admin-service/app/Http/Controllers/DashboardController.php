<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(): View
    {
        return view('dashboard', ['metrics' => $this->dashboard->metrics()]);
    }
}
