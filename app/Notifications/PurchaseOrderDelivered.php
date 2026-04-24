<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\PurchaseOrder;
use Illuminate\Support\HtmlString;

class PurchaseOrderDelivered extends Notification implements ShouldQueue
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
        $orderNumber = $this->purchaseOrder->order_number;
        $vendorName = $this->purchaseOrder->vendor->name ?? 'Vendor';
        $orderDate = $this->purchaseOrder->date->format('d M Y');
        $deliveryDate = optional($this->purchaseOrder->expected_delivery_date)->format('d M Y');

        return (new MailMessage)
            ->subject("✅ Order Delivered & Inventory Updated for Purchase Order #{$orderNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line(new HtmlString("We’re pleased to inform you that the delivery for your purchase order <strong>#{$orderNumber}</strong> has been <strong>successfully completed</strong>."))
            ->line("Here’s a quick summary of the delivered order:")
            ->line("- Vendor: {$vendorName}")
            ->line("- Order Date: {$orderDate}")
            ->line("- Delivery Date: {$deliveryDate}")
            ->line('')
            ->line("The inventory has been updated accordingly, and all items have been logged into the system.")
            ->line("If you have any questions or need to verify stock levels, feel free to reach out to the admin team.")
            ->line('')
            ->line("Thank you for your continued trust and partnership.")
            ->salutation("Warm regards,  
    The SwitchIt Team");
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
