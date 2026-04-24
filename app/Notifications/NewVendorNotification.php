<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Vendor;
use Illuminate\Support\HtmlString;

class NewVendorNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $vendor;
    /**
     * Create a new notification instance.
     */
    public function __construct(Vendor $vendor)
    {
        $this->vendor = $vendor;
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
        $vendor = $this->vendor;
        return (new MailMessage)
                ->subject("🆕 Vendor Created: {$vendor->name} — Approval Required")
                ->greeting("Dear {$notifiable->name},")
                ->line("A new vendor profile <strong>{$vendor->name}</strong> has been created by the team.")
                ->line("This vendor is currently inactive and cannot be used for purchase orders until approved.")
                ->line("Please review the vendor details and activate the profile to enable procurement workflows.")
                ->action('Review Vendor', route('vendors'))
                ->line("Timely approval ensures smooth coordination between procurement and warehouse operations.")
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
