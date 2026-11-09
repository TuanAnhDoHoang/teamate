<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Render the Teamate landing page with mock RBAC definitions.
     */
    public function index(): View
    {
        $features = [
            [
                'id' => 'storage',
                'title' => 'Project Storage',
                'description' => 'All documents, files and history of your project live in one place.',
                'highlight' => false,
            ],
            [
                'id' => 'permissions',
                'title' => 'Secure Permissions',
                'description' => 'Everyone sees and does only what belongs to their role.',
                'highlight' => false,
            ],
            [
                'id' => 'backup',
                'title' => 'Decentralized Backup',
                'description' => 'Your data is backed up across multiple nodes, with no single point of failure.',
                'highlight' => true,
            ],
        ];

        $roles = [
            'admin' => [
                'id' => 'admin',
                'name' => 'Admin',
                'color' => 'red',
                'badge_class' => 'bg-rose-50 text-rose-700 border-rose-200',
                'description' => 'Manages team, projects, members and permissions.',
            ],
            'dev' => [
                'id' => 'dev',
                'name' => 'Development Team',
                'color' => 'blue',
                'badge_class' => 'bg-blue-50 text-blue-700 border-blue-200',
                'description' => 'Creates and edits content, updates progress.',
            ],
            'review' => [
                'id' => 'review',
                'name' => 'Review Team',
                'color' => 'purple',
                'badge_class' => 'bg-purple-50 text-purple-700 border-purple-200',
                'description' => 'Reviews and gives internal feedback.',
            ],
            'client' => [
                'id' => 'client',
                'name' => 'Client',
                'color' => 'green',
                'badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'description' => 'Follows progress on shared content.',
            ],
        ];

        $permissionTypes = ['Read', 'Edit', 'Own'];

        $resources = [
            [
                'id' => 'team',
                'name' => 'Team Workspace',
                'type' => 'Team',
                'level' => 0,
            ],
            [
                'id' => 'project',
                'name' => 'Project Repository',
                'type' => 'Project',
                'level' => 1,
            ],
            [
                'id' => 'folder_a',
                'name' => 'Folder A (source code / development docs)',
                'type' => 'Folder',
                'level' => 2,
            ],
            [
                'id' => 'folder_b',
                'name' => 'Folder B (review docs)',
                'type' => 'Folder',
                'level' => 2,
            ],
            [
                'id' => 'folder_c',
                'name' => 'Folder C (client-shared docs)',
                'type' => 'Folder',
                'level' => 2,
            ],
        ];

        // RBAC Matrix mapping roles to resource permission sets
        $matrix = [
            'admin' => [
                'team' => ['Read', 'Edit', 'Own'],
                'project' => ['Read', 'Edit', 'Own'],
                'folder_a' => ['Read', 'Edit', 'Own'],
                'folder_b' => ['Read', 'Edit', 'Own'],
                'folder_c' => ['Read', 'Edit', 'Own'],
            ],
            'dev' => [
                'team' => ['Read'],
                'project' => ['Read'],
                'folder_a' => ['Read', 'Edit'],
                'folder_b' => ['Read'],
                'folder_c' => [],
            ],
            'review' => [
                'team' => ['Read'],
                'project' => ['Read'],
                'folder_a' => ['Read'],
                'folder_b' => ['Read', 'Edit'],
                'folder_c' => [],
            ],
            'client' => [
                'team' => ['Read'],
                'project' => ['Read'],
                'folder_a' => [],
                'folder_b' => [],
                'folder_c' => ['Read'],
            ],
        ];

        $steps = [
            [
                'step' => 1,
                'title' => 'Create a team',
                'description' => 'Set up your team workspace and assign leadership roles in seconds.',
            ],
            [
                'step' => 2,
                'title' => 'Create a project',
                'description' => 'Structure resources and folders with inheritance from team policies.',
            ],
            [
                'step' => 3,
                'title' => 'Invite members to start working',
                'description' => 'Grant exact roles so everyone has the right access from day one.',
            ],
        ];

        return view('welcome', compact('features', 'roles', 'permissionTypes', 'resources', 'matrix', 'steps'));
    }
}

