<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserSetPasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
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
        $setPasswordUrl = "http://agent.whitelabel.pk/set-password?entity={$notifiable->uuid}&action=new-action";
        return (new MailMessage)
            ->subject('Set Your Password')
            ->greeting('Hello ' . $this->user->name . '!')
            ->line('You are just one step away from securing your account.')
            ->action('Set Password', $setPasswordUrl)
            ->line('If you did not request this, please ignore this email.')
            ->line('Thank you for being with us!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}