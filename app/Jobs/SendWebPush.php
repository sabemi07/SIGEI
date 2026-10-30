<?php

namespace App\Jobs;

use App\Models\Enums\PushDeliveryStatus;
use App\Models\PushDelivery;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;
use Throwable;

class SendWebPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 40;

    public function __construct(public int $deliveryId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('push-delivery:'.$this->deliveryId))->releaseAfter(15)->expireAfter(60)];
    }

    public function handle(WebPushService $sender): void
    {
        if ($sender->send($this->deliveryId)) {
            throw new RuntimeException('webpush_temporary_failure');
        }
    }

    public function failed(?Throwable $exception): void
    {
        PushDelivery::query()->whereKey($this->deliveryId)->whereIn('status', [PushDeliveryStatus::Queued, PushDeliveryStatus::Sending])
            ->update(['status' => PushDeliveryStatus::Failed->value, 'error_code' => 'retry_exhausted']);
    }
}
