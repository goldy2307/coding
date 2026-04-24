<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SalesOrder;
use Illuminate\Support\HtmlString;

class SalesOrderBackToWarehouse extends Notification implements ShouldQueue
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
            ->subject("Sales Order #{$orderNumber} – Return Shipment Notification")
            ->greeting("Dear {$notifiable->name},")
            ->line(new HtmlString("We would like to inform you that <strong>Sales Order #{$orderNumber}</strong> has been <strong>returned to the warehouse</strong> following its dispatch from the courier facility."))
            ->line("Kindly review the details of the returned shipment:")
            ->line("- Shop: {$shopName}")
            ->line("- Order Date: {$orderDate}")
            ->line("- Expected Delivery Date: {$deliveryDate}")
            ->line("If you notice any discrepancies or require further clarification, please contact the logistics coordinator at your earliest convenience.")
            ->salutation("Warm regards,\nSwitchIt Team");

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
