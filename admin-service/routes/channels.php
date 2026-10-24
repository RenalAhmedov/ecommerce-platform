<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Order activity from the shop and from other admins (see App\Events).
Broadcast::channel('admin.orders', fn (User $user) => $user->isAdmin());
