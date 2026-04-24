<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SalesOrder;
use Illuminate\Support\HtmlString;

class SalesOrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $salesOrder;
    /**
     * Create a new notification instance.
     */
    public function __construct(SalesOrder $salesOrder)
    {
        $this->salesOrder = $salesOrder;
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
        $status = ucfirst($this->salesOrder->approval_status);
        $orderNumber = $this->salesOrder->order_number;
        $shopName = $this->salesOrder->shop->name ?? 'Shop';
        $orderDate = $this->salesOrder->date->format('d M Y');
        $deliveryDate = optional($this->salesOrder->expected_delivery_date)->format('d M Y');

        return (new MailMessage)
            ->subject("📦 Sales Order #{$orderNumber} has been {$status}")
            ->greeting("Dear {$notifiable->name},")
            ->line(new HtmlString("Your sales order <strong>#{$orderNumber}</strong> has been <strong>{$status}</strong>."))
            ->line("Here’s a quick summary of your order:")
            ->line("- Shop: {$shopName}")
            ->line("- Order Date: {$orderDate}")
            ->line("- Expected Delivery: {$deliveryDate}")
            ->line('')
            ->line($status === 'Rejected'
                ? "If you have any questions or would like to revise the order, feel free to reach out to the admin."
                : "Thank you for your sales order submission. The order can now proceed to the next stage of processing.")
            ->line('')
            ->line("If you believe this update was made in error, please contact admin.")
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
