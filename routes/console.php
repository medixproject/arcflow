<?php

use App\Console\Commands\MonitorArcPayments;
use Illuminate\Support\Facades\Schedule;

Schedule::command(MonitorArcPayments::class)->everyTenSeconds()->withoutOverlapping();
