<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
// use Illuminate\Notifications\Notification;

class CustomResetPassword extends ResetPassword
{
    use Queueable;
    
    
    public function __construct($token) { 
        parent::__construct($token); // call parent constructor 
    }

   
    public function via($notifiable)
    {
        return ['mail'];
    }

    
    public function toMail($notifiable)
    {
        return (new MailMessage) 
            ->subject('Action Required: Reset Your Switch It Password') 
            ->greeting('Dear ' . $notifiable->name . ',') 
            ->line('We received a request to reset your password for your Switch It account. If you initiated this request, please click the link below to set a new password:') 
            ->action('Reset Password', url(config('app.url').route('password.reset', $this->token, false))) 
            ->line('If you did not request a password reset, please ignore this email or contact our support team immediately.');
    }

    
    public function toArray($notifiable)
    {
        return [];
    }
}
