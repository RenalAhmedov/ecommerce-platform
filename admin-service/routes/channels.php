<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.orders', fn (User $user) => $user->isAdmin());
