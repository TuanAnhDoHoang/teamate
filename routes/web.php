<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectRoleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // 5.1 Dashboard: Team List
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('teams', [DashboardController::class, 'store'])->name('teams.store');

    // 5.2 Team Page
    Route::get('teams/{team:slug}', [TeamController::class, 'show'])->name('teams.show');
    Route::post('teams/{team:slug}/projects', [TeamController::class, 'storeProject'])->name('teams.projects.store');
    Route::post('teams/{team:slug}/members', [TeamController::class, 'storeMember'])->name('teams.members.store');
    Route::get('teams/{team:slug}/roles', [TeamRoleController::class, 'index'])->name('teams.roles.index');
    Route::post('teams/{team:slug}/roles', [TeamRoleController::class, 'store'])->name('teams.roles.store');
    Route::put('teams/{team:slug}/roles/{role}', [TeamRoleController::class, 'update'])->name('teams.roles.update');
    Route::patch('teams/{team:slug}/members/{member}/role', [TeamRoleController::class, 'assignMember'])->name('teams.members.role.update');

    // 5.3 Project Page & File Operations
    Route::get('teams/{team:slug}/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('teams/{team:slug}/projects/{project:slug}/members', [ProjectMemberController::class, 'index'])->name('projects.members.index');
    Route::post('teams/{team:slug}/projects/{project:slug}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::patch('teams/{team:slug}/projects/{project:slug}/members/{member}/role', [ProjectMemberController::class, 'updateRole'])->name('projects.members.role.update');
    Route::delete('teams/{team:slug}/projects/{project:slug}/members/{member}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
    Route::get('teams/{team:slug}/projects/{project:slug}/roles', [ProjectRoleController::class, 'index'])->name('projects.roles.index');
    Route::post('teams/{team:slug}/projects/{project:slug}/roles', [ProjectRoleController::class, 'store'])->name('projects.roles.store');
    Route::put('teams/{team:slug}/projects/{project:slug}/roles/{role}', [ProjectRoleController::class, 'update'])->name('projects.roles.update');
    Route::post('teams/{team:slug}/projects/{project:slug}/folders', [ProjectController::class, 'storeFolder'])->name('projects.folders.store');
    Route::post('teams/{team:slug}/projects/{project:slug}/upload', [ProjectController::class, 'upload'])->name('projects.upload');
    Route::patch('teams/{team:slug}/projects/{project:slug}/items/{item}', [ProjectController::class, 'rename'])->name('projects.items.rename');
    Route::delete('teams/{team:slug}/projects/{project:slug}/items/{item}', [ProjectController::class, 'destroy'])->name('projects.items.destroy');
    Route::get('teams/{team:slug}/projects/{project:slug}/items/{item}/preview', [ProjectController::class, 'preview'])->name('projects.items.preview');
    Route::get('teams/{team:slug}/projects/{project:slug}/items/{item}/download', [ProjectController::class, 'download'])->name('projects.items.download');
});

require __DIR__.'/settings.php';
