<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Notification;

class LowMarginNotification extends Notification
{
    public function __construct(private Product $product) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['format' => 'filament', 'title' => 'Product below minimum margin', 'body' => $this->product->name.' requires a cost review.', 'icon' => 'heroicon-o-exclamation-triangle', 'iconColor' => 'warning', 'actions' => [], 'duration' => 'persistent'];
    }
}
