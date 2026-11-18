<?php

namespace App\Http\Controllers;

use App\Domain\Ideas\IdeaService;
use App\Models\AssetCategory;
use App\Models\Idea;
use App\Models\IdeaReview;
use App\Rules\Amount;
use App\Services\AttachmentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdeaController extends Controller
{
    public function __construct(private IdeaService $service)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $q = Idea::with(['proposer', 'category'])->withCount([
            'reviews as agree_count' => fn ($q) => $q->where('opinion', 'agree'),
            'reviews as disagree_count' => fn ($q) => $q->where('opinion', 'disagree'),
            'reviews as abstain_count' => fn ($q) => $q->where('opinion', 'abstain'),
        ])->visibleTo($user)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->boolean('mine'), fn ($q) => $q->where('proposed_by', $user->id));

        return view('ideas.index', ['ideas' => $q->latest()->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('ideas.form', ['idea' => new Idea(), 'categories' => AssetCategory::active()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $attachment = AttachmentService::store($request->file('attachment'), $request->user(), 'ideas');
        $data['attachment_id'] = $attachment?->id;
        $idea = $this->service->create($data, $request->user());
        AttachmentService::attachTo($attachment, $idea);

        return redirect()->route('ideas.show', $idea)->with('success', $idea->status === 'draft' ? 'پیش‌نویس ایده ذخیره شد.' : 'ایده ثبت و برای بررسی ارسال شد.');
    }

    public function show(Request $request, Idea $idea)
    {
        Gate::authorize('view', $idea);
        $idea->load(['proposer', 'category', 'decider', 'attachment', 'reviews.member', 'statusHistories.changer', 'reviewHistories.member', 'investments.asset']);

        return view('ideas.show', [
            'idea' => $idea,
            'myReview' => IdeaReview::where('idea_id', $idea->id)->where('member_id', $request->user()->id)->first(),
            'counts' => $idea->reviews->countBy('opinion'),
        ]);
    }

    public function edit(Request $request, Idea $idea)
    {
        Gate::authorize('update', $idea);

        return view('ideas.form', ['idea' => $idea, 'categories' => AssetCategory::active()->get()]);
    }

    public function update(Request $request, Idea $idea)
    {
        Gate::authorize('update', $idea);
        $data = $this->validated($request);
        if ($file = $request->file('attachment')) {
            $attachment = AttachmentService::store($file, $request->user(), 'ideas');
            AttachmentService::attachTo($attachment, $idea);
            $data['attachment_id'] = $attachment->id;
        }
        $this->service->update($idea, $data, $request->user());

        return redirect()->route('ideas.show', $idea)->with('success', 'ایده به‌روزرسانی شد.');
    }

    public function review(Request $request, Idea $idea)
    {
        Gate::authorize('view', $idea);
        $data = $request->validate([
            'opinion' => ['required', 'in:agree,disagree,abstain'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [], ['opinion' => 'نظر', 'comment' => 'توضیح']);
        $this->service->review($idea, $request->user(), $data['opinion'], $data['comment'] ?? null);

        return back()->with('success', 'نظر شما ثبت شد.');
    }

    public function decide(Request $request, Idea $idea)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Idea::STATUSES))],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['status' => 'وضعیت', 'note' => 'دلیل تصمیم']);
        $this->service->decide($idea, $data['status'], $data['note'] ?? null, $request->user());

        return back()->with('success', 'وضعیت ایده تغییر کرد.');
    }

    public function withdraw(Request $request, Idea $idea)
    {
        $this->service->withdraw($idea, $request->user());

        return back()->with('success', 'ایده لغو شد.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'description' => ['required', 'string', 'max:10000'],
            'required_capital' => ['nullable', new Amount()],
            'time_horizon' => ['nullable', 'string', 'max:100'],
            'expected_return_min' => ['nullable', 'numeric', 'between:-100,100000'],
            'expected_return_max' => ['nullable', 'numeric', 'between:-100,100000', 'gte:expected_return_min'],
            'expected_return_period' => ['nullable', 'in:'.implode(',', array_keys(Idea::PERIODS))],
            'risk_description' => ['nullable', 'string', 'max:5000'],
            'assumptions' => ['nullable', 'string', 'max:5000'],
            'attachment' => AttachmentService::rules(),
            'as_draft' => ['nullable', 'boolean'],
        ], [], ['title' => 'عنوان', 'description' => 'شرح', 'required_capital' => 'سرمایه موردنیاز',
            'expected_return_min' => 'حداقل بازده', 'expected_return_max' => 'حداکثر بازده']);
        $data['required_capital_irr'] = ! empty($data['required_capital']) ? (string) Money::fromInput($data['required_capital']) : null;
        $data['as_draft'] = $request->boolean('as_draft');
        unset($data['required_capital'], $data['attachment']);

        return $data;
    }
}
