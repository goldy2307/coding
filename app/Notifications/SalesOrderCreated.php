<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesOrderCreated extends Notification implements ShouldQueue
{
    use Queueable;
    protected $salesOrder;
    protected $salesPerson;
    /**
     * Create a new notification instance.
     */
    public function __construct($salesOrder, $salesPerson)
    {
        $this->salesOrder = $salesOrder;
        $this->salesPerson = $salesPerson;
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
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("📝 New Sales Order Created by {$this->salesPerson->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new sales order has been created by {$this->salesPerson->name}.")
            ->line("Order ID: {$this->salesOrder->order_number}")
            ->line("Customer: {$this->salesOrder->shop->name}")
            ->line("Expected Delivery: {$this->salesOrder->expected_delivery_date->format('d M Y')}")
            ->action('Review Order', url("/sales-order/{$this->salesOrder->order_number}"))
            ->line("Please verify the order details with the customer and proceed with processing.")
            ->salutation("Warm regards,  
            The SwitchIt Team");
    }

    public function toArray($notifiable)
    {
        return [
            'sales_order_id' => $this->salesOrder->id,
            'sales_person' => $this->salesPerson->name,
            'message' => 'A new sales order has been created and requires verification.',
        ];
    }
}
