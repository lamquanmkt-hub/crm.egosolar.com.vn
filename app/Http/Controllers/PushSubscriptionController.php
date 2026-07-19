<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\DTO\PushSubscriptionData;
use App\Http\Requests\Push\SubscribePushSubscriptionRequest;
use App\Http\Requests\Push\UnsubscribePushSubscriptionRequest;
use App\Services\Push\PushSubscriptionService;
use Illuminate\Http\JsonResponse;
final class PushSubscriptionController extends Controller
{
    public function __construct(private readonly PushSubscriptionService $service)
    {
        // nên gắn middleware auth ở route hoặc ở đây
        // $this->middleware('auth');
    }
    public function subscribe(SubscribePushSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->service->subscribe(
            $request->user(),
            new PushSubscriptionData(
                endpoint: $data['endpoint'],
                publicKey: $data['publicKey'],
                authToken: $data['authToken'],
                contentEncoding: $data['contentEncoding'] ?? null,
            )
        );
        return response()->json([
            'ok' => true,
            'message' => 'Đã đăng ký nhận thông báo thành công.',
        ], 200);
    }
    public function unsubscribe(UnsubscribePushSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->service->unsubscribe($request->user(), $data['endpoint']);
        return response()->json([
            'ok' => true,
            'message' => 'Đã hủy đăng ký nhận thông báo.',
        ], 200);
    }
}
