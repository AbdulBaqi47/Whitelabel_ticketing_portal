<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendResetPasswordNotification extends Notification
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
        $setPasswordUrl = "http://agent.whitelabel.pk/set-password?entity={$notifiable->uuid}&action=reset-action";
        return (new MailMessage)
            ->subject('Reset Your Password - Secure Your Account')
            ->greeting('Dear ' . $this->user->name . ',')
            ->line('We received a request to reset your password. To ensure the security of your account, please set a new password by clicking the button below.')
            ->action('Reset Password', $setPasswordUrl)
            ->line('If you did not request this change, please ignore this email. Your current password will remain unchanged.')
            ->line('For any assistance, feel free to contact our support team.')
            ->salutation('Best regards, The WhiteLabel Team');
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
