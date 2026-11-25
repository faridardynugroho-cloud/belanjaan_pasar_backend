<?php

namespace App\Http\Controllers\Api\Driver;

use App\Exceptions\DeliveryRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\CompleteDeliveryOrderRequest;
use App\Http\Requests\Api\Driver\RejectDeliveryOrderRequest;
use App\Http\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeliveryOrderController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {}

    public function accept(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        if (Gate::denies('accept', $deliveryOrder)) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_NOT_OWNER,
                'Anda bukan pemilik pengantaran ini.',
                403,
            );
        }

        $accepted = $this->deliveryService->accept($request->user()->driverProfile, $deliveryOrder);

        return response()->json([
            'message' => 'Order diterima',
            'delivery_order' => DeliveryOrderResource::make($accepted),
        ]);
    }

    public function reject(RejectDeliveryOrderRequest $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        if (Gate::denies('reject', $deliveryOrder)) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_NOT_OWNER,
                'Anda bukan pemilik pengantaran ini.',
                403,
            );
        }

        $rejected = $this->deliveryService->reject(
            $request->user()->driverProfile,
            $deliveryOrder,
            $request->validated('rejection_reason'),
        );

        return response()->json([
            'message' => 'Order ditolak',
            'delivery_order' => DeliveryOrderResource::make($rejected),
        ]);
    }

    public function complete(CompleteDeliveryOrderRequest $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        if (Gate::denies('complete', $deliveryOrder)) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_NOT_OWNER,
                'Anda bukan pemilik pengantaran ini.',
                403,
            );
        }

        $proofData = [
            'received_by' => $request->validated('received_by'),
            'received_note' => $request->validated('received_note'),
        ];
        $storedProofPath = null;

        try {
            if ($request->hasFile('proof_photo')) {
                $storedProofPath = $request->file('proof_photo')->store('delivery-proofs', 'public');
                $proofData['proof_photo'] = $storedProofPath;
            }

            $completed = $this->deliveryService->complete(
                $request->user()->driverProfile,
                $deliveryOrder,
                $proofData,
            );

            return response()->json([
                'message' => 'Order selesai diantar',
                'delivery_order' => DeliveryOrderResource::make($completed),
            ]);
        } catch (Throwable $e) {
            if ($storedProofPath !== null) {
                Storage::disk('public')->delete($storedProofPath);
            }

            throw $e;
        }
    }
}
