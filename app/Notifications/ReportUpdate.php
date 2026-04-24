<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SalesOrder;

class ReportUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    protected $report;

    /**
     * Create a new notification instance.
     */
    public function __construct($report)
    {
        $this->report = $report;
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
        $order = SalesOrder::find($this->report->sales_order_id);
        return (new MailMessage)
            ->subject("Approval Status Update for Order #{$order->order_number}")
            ->greeting("Hello,")
            ->line("The approval status for Sales Order #{$order->order_number} has been marked as *declined*.")
            ->line("Please review the report associated with this order and take necessary action.")
            ->line("Thank you for your attention and continued support.");
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
