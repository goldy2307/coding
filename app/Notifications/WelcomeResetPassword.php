<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
        return (new MailMessage)
                ->subject('Welcome to SwitchIt — Let’s Get You Started')
                ->greeting('Dear ' . $notifiable->name . ',')
                ->line('We’re thrilled to welcome you to the SwitchIt family! Your employee account has been successfully created, and we can’t wait for you to dive in and explore all the tools and resources available to you.')
                ->line('To get started, you’ll need to set your password and activate your account. This ensures your access is secure and personalized.')
                ->action('Set Your Password', $url)
                ->line('This link will take you to a secure page where you can choose your password. Once done, you’ll be able to log in and begin using your dashboard right away.')
                ->line('Here’s what you can expect once you log in:')
                ->line('- Access to your personalized employee dashboard')
                ->line('- Tools to manage your tasks, attendance, and profile')
                ->line('- Notifications and updates tailored to your role')
                ->line('')
                ->line('If you weren’t expecting this email or believe it was sent in error, please ignore it or contact our support team.')
                ->salutation('Warm regards,  
        The SwitchIt Team');
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
