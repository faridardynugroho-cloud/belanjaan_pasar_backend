<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\DispatchService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 30;

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function tries(): int
    {
        return (int) config('delivery.dispatch_max_tries', 10);
    }

    public function handle(DispatchService $dispatchService): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->status !== Order::STATUS_READY_FOR_DELIVERY || $order->activeDelivery()->exists()) {
            return;
        }

        $assigned = $dispatchService->dispatch($order);

        if ($assigned === null && $this->attempts() < $this->tries()) {
            $this->release((int) config('delivery.dispatch_retry_delay_seconds', 30));
        }
    }
}
