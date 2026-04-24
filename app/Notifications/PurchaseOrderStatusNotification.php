<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\PurchaseOrder;
use Illuminate\Support\HtmlString;


class PurchaseOrderStatusNotification extends Notification implements ShouldQueue
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
        $status = ucfirst($this->purchaseOrder->approval_status);
        $orderNumber = $this->purchaseOrder->order_number;
        $vendorName = $this->purchaseOrder->vendor->name ?? 'Vendor';
        $orderDate = $this->purchaseOrder->date->format('d M Y');
        $deliveryDate = optional($this->purchaseOrder->expected_delivery_date)->format('d M Y');

        return (new MailMessage)
            ->subject("📦 Purchase Order #{$orderNumber} has been {$status}")
            ->greeting("Dear {$notifiable->name},")
            ->line(new HtmlString("Your purchase order <strong>#{$orderNumber}</strong> has been <strong>{$status}</strong>."))
            ->line("Here’s a quick summary of your order:")
            ->line("- Vendor: {$vendorName}")
            ->line("- Order Date: {$orderDate}")
            ->line("- Expected Delivery: {$deliveryDate}")
            ->line('')
            ->line($status === 'Rejected'
                ? "If you have any questions or would like to revise the order, feel free to reach out to the admin."
                : "Thank you for your purchase order submission. The order can now proceed to the next stage of processing.")
            ->line('')
            ->line("If you believe this update was made in error, please contact admin.")
            ->salutation("Warm regards,  
                The SwitchIt Team");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'status' => $this->purchaseOrder->approval_status,
        ];
    }
}
