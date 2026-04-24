<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\PurchaseOrder;

class PurchaseOrderWarehouse extends Notification implements ShouldQueue
{
    use Queueable;

    protected $purchaseOrder;
    /**
     * Create a new notification instance.
     */
    public function __construct(PurchaseOrder $purchaseOrder)
    {
        $this->purchaseOrder = $purchaseOrder;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $purchaseOrder = $this->purchaseOrder;
        return (new MailMessage)
            ->subject("New Purchase Order #{$purchaseOrder->order_number} Placed")
            ->greeting('Hello Warehouse Team,')
            ->line("A new purchase order (#{$purchaseOrder->order_number}) has been successfully placed.")
            ->line("The items are expected to be delivered to the warehouse by **{$purchaseOrder->expected_delivery_date->format('d M Y')}**.")
            ->line("Please prepare to receive and verify the shipment upon arrival.")
            ->action('View Purchase Order', Route('purchase-order', $purchaseOrder->order_number))
            ->line('Thank you for your coordination and support.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
