<?php

namespace App\Notifications\Admin;

use App\Utils\Number;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class InvoiceSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    
    protected   $invitation;
    
    protected   $invoice;

    protected   $company;

    protected   $settings; 

    public      $is_system;

    protected   $contact;

    public function __construct($invitation, $company, $is_system = false, $settings = null)
    {
        $this->invoice = $invitation->invoice;
        $this->contact = $invitation->contact;
        $this->company = $company;
        $this->settings = $this->invoice->client->getMergedSettings();
        $this->is_system = $is_system;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {

        return $this->is_system ? ['slack'] : ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

        $amount = Number::formatMoney($this->invoice->amount, $this->invoice->client);
        $subject = ctrans('texts.notification_invoice_sent_subject', 
                [
                    'client' => $this->contact->present()->name(), 
                    'invoice' => $this->invoice->number,
                ]);

        $data = [
            'title' => $subject,
            'message' => ctrans('texts.notification_invoice_sent', 
                [
                    'amount' => $amount, 
                    'client' => $this->contact->present()->name(), 
                    'invoice' => $this->invoice->number,
                ]),
            'url' => config('ninja.site_url') . '/invoices/' . $this->invoice->hashed_id,
            'button' => ctrans('texts.view_invoice'),
            'signature' => $this->settings->email_signature,
            'logo' => $this->company->present()->logo(),
        ];


        return (new MailMessage)
                    ->subject($subject)
                    ->markdown('email.admin.generic', $data);


    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function toSlack($notifiable)
    {
        $logo = $this->company->present()->logo();
        $amount = Number::formatMoney($this->invoice->amount, $this->invoice->client);

        // return (new SlackMessage)
        //         ->success()
        //         ->from(ctrans('texts.notification_bot'))
        //         ->image($logo)
        //         ->content(ctrans('texts.notification_invoice_sent', 
        //         [
        //             'amount' => $amount, 
        //             'client' => $this->contact->present()->name(), 
        //             'invoice' => $this->invoice->number
        //         ]));


        return (new SlackMessage)
                    ->from(ctrans('texts.notification_bot'))
                    ->success()
                    ->image('https://app.invoiceninja.com/favicon-v2.png')
                    ->content(trans('texts.notification_invoice_sent_subject',
                    [
                        'amount' => $amount, 
                        'client' => $this->contact->present()->name(), 
                        'invoice' => $this->invoice->number
                    ]))
                    ->attachment(function ($attachment) use($amount){
                        $attachment->title(ctrans('texts.invoice_number_placeholder', ['invoice' => $this->invoice->number]), 'http://linky')
                                   ->fields([
                                        ctrans('texts.client') => $this->contact->present()->name(),
                                        ctrans('texts.amount') => $amount,
                                    ]);
                    });
    }

}
