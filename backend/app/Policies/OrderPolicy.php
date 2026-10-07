<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderPolicy extends AdminPolicy
{
    public function view(User $user, Model $model): bool
    {
        return $user->isAdmin() || ($model instanceof Order && $model->user_id !== null && (int) $model->user_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        // Storefront orders must go through the transactional checkout service.
        return false;
    }
}
