<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\PurchaseOrder;
use Illuminate\Support\HtmlString;

class PurchaseOrderDeliveryCancelled extends Notification implements ShouldQueue
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
            ->subject("❌ Delivery Cancelled for Purchase Order #{$orderNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line(new HtmlString("We regret to inform you that the delivery for your purchase order <strong>#{$orderNumber}</strong> has been <strong>cancelled</strong>."))
            ->line("Here’s a quick summary of the affected order:")
            ->line("- Vendor: {$vendorName}")
            ->line("- Order Date: {$orderDate}")
            ->line("- Expected Delivery: {$deliveryDate}")
            ->line('')
            ->line("This cancellation may be due to logistical issues, vendor constraints, or administrative changes.")
            ->line("If you need to reschedule delivery or revise the order, please contact the admin team at your earliest convenience.")
            ->line('')
            ->line("We apologize for any inconvenience caused and appreciate your understanding.")
            ->salutation("Sincerely,  
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
            'purchase_order_id' => $this->purchaseOrder->id,
            'status' => $this->purchaseOrder->delivery_status,
        ];
    }
}
