<?php

namespace App\Http\Controllers\Web;

use App\Domain\Promotions\PromotionWorkflowService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\StorePromotionOfferRequest;
use App\Models\PromotionOffer;
use App\Models\User;

class MemberPromotionController extends Controller
{
    public function __construct(
        protected PromotionWorkflowService $workflow,
    ) {}

    public function store(StorePromotionOfferRequest $request, User $user)
    {
        $this->workflow->createOffer(
            $request->user(),
            $user,
            (string) $request->validated('branch_role_id')
        );

        return back()->with('success', 'Promotion offer sent.');
    }

    public function cancel(User $user, PromotionOffer $promotionOffer)
    {
        abort_unless((int) $promotionOffer->member_id === (int) $user->id, 404);

        $this->workflow->cancelOffer(request()->user(), $promotionOffer);

        return back()->with('success', 'Promotion offer cancelled.');
    }

    public function demote(User $user)
    {
        $this->workflow->demoteToMember(request()->user(), $user);

        return back()->with('success', 'Member demoted to Member.');
    }
}
