<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Categories with their ledger accounts, plus the rules that pick them. */
class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()->withCount('documents')->orderBy('name_en')->get();
        $rules = CategoryRule::query()->with(['vendor:id,name,tax_id', 'author:id,name'])->orderByDesc('hits')->orderByDesc('updated_at')->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories->map(fn (Category $c) => [
                ...CategoryResource::make($c)->resolve(),
                'documents' => (int) $c->getAttribute('documents_count'),
                'keywords' => $c->keywords ?? [],
            ]),
            'rules' => $rules->map(fn (CategoryRule $r) => [
                'id' => $r->id,
                'category_id' => $r->category_id,
                'vendor' => $r->vendor ? ['name' => $r->vendor->name, 'tax_id' => $r->vendor->tax_id] : null,
                'pattern' => $r->pattern,
                'source' => $r->source,
                'hits' => $r->hits,
                'author' => $r->author?->name,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(Request $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'account_code' => ['nullable', 'string', 'max:20'],
            'account_1c' => ['nullable', 'string', 'max:20', 'regex:/^[0-9.]*$/'],
        ]);
        $before = $category->only(['account_code', 'account_1c']);
        $category->update($data);
        $audit->record('category.updated', $category, ['from' => $before, 'to' => $data], $request->user());

        return back();
    }

    public function storeRule(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'min:3', 'max:80'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);
        $rule = CategoryRule::create([
            'pattern' => mb_strtolower(trim($data['pattern'])),
            'category_id' => (int) $data['category_id'],
            'source' => CategoryRule::SOURCE_MANUAL,
            'created_by' => $request->user()?->id,
        ]);
        $audit->record('rule.created', $rule, ['pattern' => $rule->pattern, 'category' => $rule->category_id], $request->user());

        return back()->with('success', __('rules.created'));
    }

    public function updateRule(Request $request, CategoryRule $rule, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id']]);
        $before = $rule->category_id;
        $rule->update(['category_id' => (int) $data['category_id'], 'source' => CategoryRule::SOURCE_MANUAL]);
        $audit->record('rule.updated', $rule, ['from' => $before, 'to' => $rule->category_id], $request->user());

        return back();
    }

    public function destroyRule(Request $request, CategoryRule $rule, AuditLogger $audit): RedirectResponse
    {
        $audit->record('rule.deleted', $rule, ['pattern' => $rule->pattern, 'vendor' => $rule->vendor_id], $request->user());
        $rule->delete();

        return back()->with('success', __('rules.deleted'));
    }
}
