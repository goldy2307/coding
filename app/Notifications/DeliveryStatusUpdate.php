<?php

namespace App\Notifications;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class DeliveryStatusUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public $salesOrder;
    public $status;
    public $remark;

    /**
     * Create a new notification instance.
     */
    public function __construct(SalesOrder $salesOrder, $status, $remark = null)
    {
        $this->salesOrder = $salesOrder;
        $this->status = $status;
        $this->remark = $remark;
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
        $status = $this->status;
        $remark = $this->remark ?? null;
        $orderNumber = $this->salesOrder->order_number;

        $mail = (new MailMessage)
            ->subject("📦 Sales Order #{$orderNumber} Status: {$status}")
            ->greeting("Hello {$notifiable->name},")
            ->line(new HtmlString("Sales order <strong>#{$orderNumber}</strong> shipment status has been updated to <strong>{$status}</strong>."))
            ->line("Here’s a quick summary of the order:")
            ->line("- Order Date: {$this->salesOrder->date->format('d M Y')}")
            ->line("- Expected Delivery: " . optional($this->salesOrder->expected_delivery_date)->format('d M Y'));

        if ($status === 'Failed' && $remark) {
            $mail->line("- Reason: {$remark}")
                ->line("This may be due to logistical issues, vendor constraints, or administrative changes.")
                ->line("If you need to reschedule or revise the order, please contact the admin team.");
        }

        $mail->line("Thank you for using SwitchIt!");

        return $mail;
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
