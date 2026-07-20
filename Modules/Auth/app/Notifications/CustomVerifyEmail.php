<?php

namespace Modules\Auth\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        if (is_callable(static::$createUrlCallback)) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $id,
                'hash' => $hash,
            ]
        );

        $queryString = parse_url($backendUrl, PHP_URL_QUERY);

        // This assumes your frontend URL is set in standard config or env
        // e.g. FRONTEND_URL=http://localhost:3000
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

        return "{$frontendUrl}/verify-email?{$queryString}";
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if (is_callable(static::$toMailCallback)) {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }

        return (new MailMessage)
            ->subject('Verify Email Address - IniCMS')
            ->view('emails.verify', [
                'url' => $verificationUrl,
                'user' => $notifiable,
            ]);
    }
}
