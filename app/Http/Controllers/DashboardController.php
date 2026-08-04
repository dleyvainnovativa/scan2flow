<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Document;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use App\Support\DocumentVisibility;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        // Areas the user can see (admins: all; members: granted-view areas).
        $areaIds = $isAdmin
            ? Area::pluck('id')
            : $user->areas()->where('can_view', true)->pluck('areas.id');
        $stats = [
            'areas'     => $areaIds->count(),
            'templates' => Template::whereIn('area_id', $areaIds)->count(),
            'documents' => Document::whereIn('area_id', $areaIds)->count(),
            'pages'     => (int) Document::whereIn('area_id', $areaIds)->sum('page_count'), // >>> ADD
            'users'     => $isAdmin ? User::forCurrentTenant()->count() : null,
        ];
        $docCountQuery = Document::whereIn('area_id', $areaIds);
        DocumentVisibility::scope($docCountQuery, $user);
        $stats['documents'] = $docCountQuery->count();

        // Recent documents the user can access.
        // $recentDocuments = Document::with(['area', 'template'])
        //     ->whereIn('area_id', $areaIds)
        //     ->latest()
        //     ->limit(6)
        //     ->get();

        $recentQuery = Document::with(['area', 'template'])->whereIn('area_id', $areaIds);
        DocumentVisibility::scope($recentQuery, $user);
        $recentDocuments = $recentQuery->latest()->limit(6)->get();

        // Areas overview (with counts) for quick navigation.
        $areas = Area::withCount(['templates', 'documents'])
            ->whereIn('id', $areaIds)
            ->orderBy('name')
            ->limit(6)
            ->get();
        return view('dashboard.index', compact('stats', 'recentDocuments', 'areas', 'isAdmin'));
    }
}
