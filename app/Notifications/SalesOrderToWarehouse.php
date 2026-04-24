<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SalesOrder;
use Illuminate\Support\HtmlString;

class SalesOrderToWarehouse extends Notification implements ShouldQueue
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
        $orderNumber = $this->salesOrder->order_number;
        $shopName = $this->salesOrder->shop->name ?? 'Shop';
        $orderDate = $this->salesOrder->date->format('d M Y');
        $deliveryDate = optional($this->salesOrder->expected_delivery_date)->format('d M Y');
        return (new MailMessage)
            ->subject("📦 Sales Order #{$orderNumber} Approved — Packaging Required")
            ->greeting("Dear {$notifiable->name},")
            ->line(new HtmlString("Sales order <strong>#{$orderNumber}</strong> has been <strong>approved</strong> by the backend operations team."))
            ->line("Please initiate the packaging process for the following order:")
            ->line("- Shop: {$shopName}")
            ->line("- Order Date: {$orderDate}")
            ->line("- Expected Delivery: {$deliveryDate}")
            ->line('')
            ->line("Once packaging is complete, kindly mark the order as 'Packed' and dispatch the shipment to the courier facility for delivery.")
            ->line("Timely updates are essential to ensure smooth dispatch and customer satisfaction.")
            ->line('')
            ->line("If you encounter any issues or discrepancies, please contact the operations team immediately.")
            ->salutation("Thank you for your coordination,
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
