<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index()
    {
        // SECURITY: Only admin+ can manage announcements
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403);
        }

        $announcements = Announcement::with('creator:id,name')
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Announcements/Index', [
            'announcements' => $announcements,
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:info,warning,success,error',
            'link_url' => 'nullable|url|max:500',
            'link_text' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'order' => 'integer|min:0',
        ]);

        $validated['created_by'] = auth()->id();

        Announcement::create($validated);

        return redirect()->back()->with('success', 'Oznámenie bolo vytvorené.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:info,warning,success,error',
            'link_url' => 'nullable|url|max:500',
            'link_text' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'order' => 'integer|min:0',
        ]);

        $announcement->update($validated);

        return redirect()->back()->with('success', 'Oznámenie bolo aktualizované.');
    }

    public function destroy(Announcement $announcement)
    {
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403);
        }

        $announcement->delete();

        return redirect()->back()->with('success', 'Oznámenie bolo zmazané.');
    }
}
