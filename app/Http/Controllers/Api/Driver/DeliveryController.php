<?php

namespace App\Http\Controllers\Api\Driver;

use App\Exceptions\DeliveryRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $profile = $request->user()->driverProfile;

        $delivery = Delivery::query()
            ->where('driver_profile_id', $profile->driver_profile_id)
            ->active()
            ->with(['deliveryOrders.order.market'])
            ->latest('delivery_id')
            ->first();

        return response()->json([
            'delivery' => $delivery ? DeliveryResource::make($delivery) : null,
        ]);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->driverProfile;

        $deliveries = Delivery::query()
            ->where('driver_profile_id', $profile->driver_profile_id)
            ->where(fn ($q) => $q->whereNotNull('completed_at')->orWhereNotNull('cancelled_at'))
            ->with(['deliveryOrders.order.market'])
            ->orderByDesc('delivery_id')
            ->paginate(15);

        return DeliveryResource::collection($deliveries);
    }

    public function start(Request $request, Delivery $delivery): JsonResponse
    {
        if (Gate::denies('start', $delivery)) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_NOT_OWNER,
                'Anda bukan pemilik pengantaran ini.',
                403,
            );
        }

        $started = $this->deliveryService->start($request->user()->driverProfile, $delivery);

        return response()->json([
            'message' => 'Pengantaran dimulai',
            'delivery' => DeliveryResource::make($started),
        ]);
    }
}
