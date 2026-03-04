<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCreatedForFreelancerNotification extends Notification
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
        $customer = $this->order->user;
        $service = $this->order->service;
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject('Nueva contratacion recibida')
            ->greeting('Hola ' . $notifiable->names . ',')
            ->line(sprintf(
                '%s %s contrato tu servicio "%s".',
                $customer->names,
                $customer->last_names,
                $service->title
            ))
            ->line('Numero de orden: #' . $this->order->id)
            ->line('Estado actual: ' . $this->order->status)
            ->action('Ver orden', $frontendUrl . '/orders/' . $this->order->id)
            ->line('Gracias por usar la plataforma.');
    }

    public function toArray(object $notifiable): array
    {
        $customer = $this->order->user;
        $service = $this->order->service;

        return [
            'type' => 'order_created',
            'title' => 'Nueva contratación',
            'message' => sprintf(
                '%s %s contrató tu servicio "%s" (orden #%d).',
                $customer->names,
                $customer->last_names,
                $service->title,
                $this->order->id
            ),
            'order_id' => $this->order->id,
            'service_id' => $service->id,
            'service_title' => $service->title,
            'customer_id' => $customer->id,
            'customer_name' => trim($customer->names . ' ' . $customer->last_names),
            'status' => $this->order->status,
        ];
    }
}
