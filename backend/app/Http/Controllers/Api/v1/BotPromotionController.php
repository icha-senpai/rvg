<?php

namespace App\Http\Controllers\Api\v1;

use App\Domain\Promotions\PromotionWorkflowService;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\FinalizePromotionAcceptanceRequest;
use App\Http\Requests\Promotions\PreparePromotionAcceptanceRequest;
use App\Models\PromotionOffer;
use Illuminate\Validation\ValidationException;

class BotPromotionController extends Controller
{
    public function __construct(
        protected PromotionWorkflowService $workflow,
    ) {}

    public function prepareAccept(PreparePromotionAcceptanceRequest $request, PromotionOffer $promotionOffer)
    {
        try {
            return ApiResponse::success(
                null,
                $this->workflow->prepareAcceptance(
                    $promotionOffer,
                    (string) $request->validated('discord_id'),
                    $request->validated('message_id')
                )
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Unable to prepare acceptance.',
                $e->errors(),
                422
            );
        }
    }

    public function prepareAcceptByMessage(PreparePromotionAcceptanceRequest $request, string $messageId)
    {
        try {
            $offer = PromotionOffer::query()
                ->where('discord_dm_message_id', $messageId)
                ->latest('id')
                ->first();

            if (! $offer) {
                return ApiResponse::error('Unable to locate the tracked promotion offer for this DM.', null, 404);
            }

            return ApiResponse::success(
                null,
                $this->workflow->prepareAcceptance(
                    $offer,
                    (string) $request->validated('discord_id'),
                    $messageId
                )
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Unable to prepare acceptance.',
                $e->errors(),
                422
            );
        }
    }

    public function finalizeAccept(FinalizePromotionAcceptanceRequest $request, PromotionOffer $promotionOffer)
    {
        try {
            $offer = $this->workflow->finalizeAcceptance(
                $promotionOffer,
                (string) $request->validated('discord_id'),
                $request->validated('message_id')
            );

            return ApiResponse::success(null, [
                'offer_id' => $offer->id,
                'state' => $offer->state,
                'accepted_at' => optional($offer->accepted_at)->toIso8601String(),
                'member_id' => $offer->member_id,
                'to_rank' => $offer->to_rank,
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Unable to finalize acceptance.',
                $e->errors(),
                422
            );
        }
    }

    public function finalizeAcceptByMessage(FinalizePromotionAcceptanceRequest $request, string $messageId)
    {
        try {
            $offer = PromotionOffer::query()
                ->where('discord_dm_message_id', $messageId)
                ->latest('id')
                ->first();

            if (! $offer) {
                return ApiResponse::error('Unable to locate the tracked promotion offer for this DM.', null, 404);
            }

            $offer = $this->workflow->finalizeAcceptance(
                $offer,
                (string) $request->validated('discord_id'),
                $messageId
            );

            return ApiResponse::success(null, [
                'offer_id' => $offer->id,
                'state' => $offer->state,
                'accepted_at' => optional($offer->accepted_at)->toIso8601String(),
                'member_id' => $offer->member_id,
                'to_rank' => $offer->to_rank,
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?: 'Unable to finalize acceptance.',
                $e->errors(),
                422
            );
        }
    }
}
