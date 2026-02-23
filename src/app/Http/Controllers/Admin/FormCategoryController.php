<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class FormCategoryController extends Controller
{
    public function index()
    {
        $categories = FormCategory::withCount(['forms', 'forms as active_forms_count' => function ($query) {
            $query->where('is_active', true);
        }])
            ->orderBy('order')
            ->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|array',
            'name.sk' => 'required|string|max:255',
            'name.en' => 'nullable|string|max:255',
            'description' => 'nullable|array',
            'description.sk' => 'nullable|string|max:1000',
            'description.en' => 'nullable|string|max:1000',
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:500',
            'order' => 'nullable|integer|min:0',
        ]);

        FormCategory::create($validated);

        // Clear cache
        Cache::forget('form_categories');

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategória bola vytvorená');
    }

    public function edit(FormCategory $category)
    {
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, FormCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|array',
            'name.sk' => 'required|string|max:255',
            'name.en' => 'nullable|string|max:255',
            'description' => 'nullable|array',
            'description.sk' => 'nullable|string|max:1000',
            'description.en' => 'nullable|string|max:1000',
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:500',
            'order' => 'nullable|integer|min:0',
        ]);

        $category->update($validated);

        // Clear cache
        Cache::forget('form_categories');

        return redirect()
            ->back()
            ->with('success', 'Kategória bola aktualizovaná');
    }

    public function destroy(FormCategory $category)
    {
        // Check if category has forms
        if ($category->forms()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Kategóriu nie je možné zmazať, pretože obsahuje formuláre. Najprv presuňte formuláre do inej kategórie.');
        }

        $category->delete();

        // Clear cache
        Cache::forget('form_categories');

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategória bola zmazaná');
    }

    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:form_categories,id',
            'categories.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['categories'] as $item) {
            FormCategory::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        // Clear cache
        Cache::forget('form_categories');

        return response()->json(['message' => 'Poradie bolo aktualizované']);
    }
}
