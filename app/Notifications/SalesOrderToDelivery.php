<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SalesOrder;
use Illuminate\Support\HtmlString;

class SalesOrderToDelivery extends Notification implements ShouldQueue
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
            ->subject("🚚 Sales Order #{$orderNumber} Dispatched to Courier Facility")
            ->greeting("Dear {$notifiable->name},")
            ->line(new HtmlString("Sales order <strong>#{$orderNumber}</strong> has been <strong>dispatched</strong> from the warehouse and is now at the courier facility."))
            ->line("Please proceed with the next steps to ensure timely delivery:")
            ->line("- Shop: {$shopName}")
            ->line("- Order Date: {$orderDate}")
            ->line("- Expected Delivery: {$deliveryDate}")
            ->line('')
            ->line("Kindly verify the shipment, initiate routing, and prepare for final delivery to the customer.")
            ->line("If any discrepancies or delays arise, please notify the logistics coordinator immediately.")
            ->salutation("Thank you for your continued support,\nThe SwitchIt Team");

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
