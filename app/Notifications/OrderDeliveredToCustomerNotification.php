<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveredToCustomerNotification extends Notification
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
            ->subject('Tu orden fue entregada')
            ->greeting('Hola ' . $notifiable->names . ',')
            ->line(sprintf(
                '%s entrego la orden #%d del servicio "%s".',
                $freelancerName,
                $this->order->id,
                $service->title
            ))
            ->line('Puedes revisar y completar la orden si todo esta correcto.')
            ->line('Estado actual: ' . $this->order->status)
            ->action('Ver orden', $frontendUrl . '/orders/' . $this->order->id)
            ->line('Gracias por usar la plataforma.');
    }

    public function toArray(object $notifiable): array
    {
        $service = $this->order->service;
        $freelancerUser = optional($service->freelancerProfile)->user;

        return [
            'type' => 'order_delivered',
            'title' => 'Tu orden fue entregada',
            'message' => sprintf(
                'El freelancer %s entregó la orden #%d del servicio "%s".',
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
