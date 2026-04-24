<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\OfferLetter;

class OfferLetterNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $offer_letter;
    /**
     * Create a new notification instance.
     */
    public function __construct(OfferLetter $offer_letter)
    {
        $this->offer_letter = $offer_letter;
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
        $pdfPath = public_path('Offer Letters/offer-letter-' . $this->offer_letter->user_id . '.pdf');

        return (new MailMessage)
                ->subject("📄 Offer Letter for {$this->offer_letter->user->name}")
                ->greeting("Dear {$notifiable->name},")
                ->line("Please find the attached offer letter for your review.")
                ->attach($pdfPath, [
                    'as' => 'OfferLetter.pdf',
                    'mime' => 'application/pdf',
                ])
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
