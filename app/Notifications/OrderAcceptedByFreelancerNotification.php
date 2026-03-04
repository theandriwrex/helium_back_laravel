<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderAcceptedByFreelancerNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $service = $this->order->service;
        $freelancerUser = optional($service->freelancerProfile)->user;
        $freelancerName = $freelancerUser
            ? trim($freelancerUser->names . ' ' . $freelancerUser->last_names)
            : 'tu freelancer';
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject('Tu orden fue aceptada')
            ->greeting('Hola ' . $notifiable->names . ',')
            ->line(sprintf(
                '%s acepto la orden #%d del servicio "%s".',
                $freelancerName,
                $this->order->id,
                $service->title
            ))
            ->line('Estado actual: ' . $this->order->status)
            ->action('Ver orden', $frontendUrl . '/orders/' . $this->order->id)
            ->line('Te notificaremos los siguientes cambios del proceso.');
    }

    public function toArray(object $notifiable): array
    {
        $service = $this->order->service;
        $freelancerUser = optional($service->freelancerProfile)->user;

        return [
            'type' => 'order_accepted',
            'title' => 'Tu orden fue aceptada',
            'message' => sprintf(
                'El freelancer %s aceptó la orden #%d del servicio "%s".',
                $freelancerUser ? trim($freelancerUser->names . ' ' . $freelancerUser->last_names) : 'asignado',
                $this->order->id,
                $service->title
            ),
            'order_id' => $this->order->id,
            'service_id' => $service->id,
            'service_title' => $service->title,
            'status' => $this->order->status,
        ];
    }
}
