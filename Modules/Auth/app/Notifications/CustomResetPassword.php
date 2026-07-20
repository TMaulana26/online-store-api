<?php

namespace Modules\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPassword
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->view('emails.reset', ['url' => $url]);
    }

    /**
     * Get the reset URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function resetUrl($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        return $frontendUrl.'/reset-password?token='.$this->token.'&email='.$notifiable->getEmailForPasswordReset();
    }
}
