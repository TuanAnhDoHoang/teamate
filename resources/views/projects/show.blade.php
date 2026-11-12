<x-layouts::app :title="$project->name.' - '.$team->name">
    <div
        x-data="{
            isDragging: false,
            newFolderModalOpen: false,
            renameModalOpen: false,
            activeRenameItem: { id: null, name: '' },
            uploadTrayOpen: false,
            uploadTrayMinimized: false,
            uploads: [],
            folderId: {{ $currentFolder ? $currentFolder->id : 'null' }},
            uploadUrl: '{{ route('projects.upload', [$team, $project], false) }}',
            csrf: '{{ csrf_token() }}',

            openRename(id, name) {
                this.activeRenameItem = { id: id, name: name };
                this.renameModalOpen = true;
            },

            triggerFileInput() {
                this.$refs.fileInput.click();
            },

            triggerFolderInput() {
                this.$refs.folderInput.click();
            },

            handleFileSelect(e) {
                const files = Array.from(e.target.files);
                if (files.length > 0) {
                    this.uploadFiles(files);
                }
                e.target.value = '';
            },

            handleDrop(e) {
                this.isDragging = false;
                const files = Array.from(e.dataTransfer.files);
                if (files.length > 0) {
                    this.uploadFiles(files);
                }
            },

            uploadFiles(files) {
                this.uploadTrayOpen = true;
                this.uploadTrayMinimized = false;

                files.forEach((file) => {
                    const uploadItem = {
                        id: Math.random().toString(36).substring(2, 9),
                        name: file.webkitRelativePath || file.name,
                        relativePath: file.webkitRelativePath || file.name,
                        progress: 0,
                        status: 'uploading',
                        errorMsg: '',
                    };

                    this.uploads.unshift(uploadItem);
                    this.startUpload(file, uploadItem);
                });
            },

            startUpload(file, uploadItem, malwareConfirmation = null) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('_token', this.csrf);
                if (malwareConfirmation) {
                    formData.append('malware_confirmation', malwareConfirmation);
                }
                if (this.folderId) {
                    formData.append('parent_id', this.folderId);
                }
                if (uploadItem.relativePath) {
                    formData.append('relative_path', uploadItem.relativePath);
                }

                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.uploadUrl, true);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.timeout = 120000;

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        uploadItem.progress = Math.round((e.loaded / e.total) * 100);
                    }
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        uploadItem.progress = 100;
                        uploadItem.status = 'success';
                        // Check if all uploads complete, refresh after brief pause
                        const active = this.uploads.filter(u => u.status === 'uploading');
                        if (active.length === 0) {
                            setTimeout(() => { window.location.reload(); }, 900);
                        }
                    } else {
                        let res;
                        try {
                            res = JSON.parse(xhr.responseText);
                        } catch (err) {
                            res = {};
                        }

                        if (res.malware_detected && res.confirmation_token) {
                            const accepted = window.confirm(
                                `ClamAV detected ${res.signature} in ${file.name}. Uploading this file may harm other users. Do you want to upload it anyway?`
                            );

                            if (accepted) {
                                uploadItem.progress = 0;
                                this.startUpload(file, uploadItem, res.confirmation_token);
                            } else {
                                uploadItem.status = 'cancelled';
                                uploadItem.errorMsg = `Upload cancelled. ClamAV detected ${res.signature}.`;
                            }

                            return;
                        }

                        uploadItem.status = 'error';
                        uploadItem.errorMsg = res.message || (res.errors ? Object.values(res.errors)[0][0] : 'Upload failed with status ' + xhr.status);
                    }
                };

                xhr.onerror = (event) => {
                    uploadItem.status = 'error';
                    uploadItem.errorMsg = 'The connection was interrupted before the server responded. Check your connection and try again.';
                    console.error('Project file upload network error', {
                        event,
                        url: this.uploadUrl,
                        fileSize: file.size,
                    });
                };

                xhr.ontimeout = () => {
                    uploadItem.status = 'error';
                    uploadItem.errorMsg = 'The upload took too long and timed out. Please try again.';
                };

                xhr.onabort = () => {
                    uploadItem.status = 'error';
                    uploadItem.errorMsg = 'The upload was interrupted. Please try again.';
                };

                xhr.send(formData);
            }
        }"
        class="flex flex-col gap-6 w-full"
    >
        <!-- Hidden HTML File & Folder Inputs -->
        @if ($canWriteCurrentFolder)
            <input
                type="file"
                x-ref="fileInput"
                multiple
                class="hidden"
                @change="handleFileSelect($event)"
            />
            <input
                type="file"
                x-ref="folderInput"
                webkitdirectory
                directory
                multiple
                class="hidden"
                @change="handleFileSelect($event)"
            />
        @endif

        <!-- Status Alert -->
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                <svg class="size-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Navigation Breadcrumbs to Team -->
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-neutral-400">
            <a href="{{ route('dashboard') }}" class="hover:text-emerald-600 transition" wire:navigate>{{ __('Teams') }}</a>
            <svg class="size-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <a href="{{ route('teams.show', $team) }}" class="hover:text-emerald-600 transition" wire:navigate>{{ $team->name }}</a>
            <svg class="size-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-800 dark:text-neutral-200 font-semibold">{{ $project->name }}</span>
        </nav>

        <!-- 5.3 Header: Project Name, Description, Status, Action Buttons -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-6 dark:border-neutral-800">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        {{ $project->name }}
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800">
                        {{ ucfirst($project->status) }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400 max-w-2xl">
                    {{ $project->description ?: __('Hierarchical repository with granular role-based access control and virus scanning.') }}
                </p>
            </div>

            <!-- Action buttons: shown ONLY to users with Edit permission or higher -->
            @if ($canEdit || $canOwn)
                <div class="flex items-center gap-2.5 shrink-0">
                    @if ($canOwn)
                        <a
                            href="{{ route('projects.members.index', [$team, $project]) }}"
                            class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                            wire:navigate
                        >
                            {{ __('Manage members') }}
                        </a>
                        <a
                            href="{{ route('projects.roles.index', [$team, $project]) }}"
                            class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                            wire:navigate
                        >
                            {{ __('Manage roles') }}
                        </a>
                    @endif

                    @if ($canWriteCurrentFolder)
                        <button
                            type="button"
                            @click="newFolderModalOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700 transition"
                        >
                            <svg class="size-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            </svg>
                            {{ __('New folder') }}
                        </button>

                        <button
                            type="button"
                            @click="triggerFileInput()"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            {{ __('Upload files') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>

        <!-- 5.3 Folder Breadcrumbs (Project > Folder A > ...) Clickable -->
        <div class="flex items-center gap-1.5 rounded-xl border border-slate-200/80 bg-white px-4 py-2.5 text-xs text-slate-600 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
            <svg class="size-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>

            @foreach ($breadcrumbs as $index => $crumb)
                @if ($index > 0)
                    <span class="text-slate-300 dark:text-neutral-600">/</span>
                @endif

                @php
                    $isLast = $index === count($breadcrumbs) - 1;
                    $url = route('projects.show', [$team, $project]);
                    if ($crumb['id']) {
                        $url .= '?folder='.$crumb['id'];
                    }
                @endphp

                @if ($isLast)
                    <span class="font-bold text-slate-900 dark:text-white">{{ $crumb['name'] }}</span>
                @else
                    <a href="{{ $url }}" class="hover:text-emerald-600 transition hover:underline" wire:navigate>
                        {{ $crumb['name'] }}
                    </a>
                @endif
            @endforeach
        </div>

        <!-- Content Area: Empty State OR Items Table -->
        @if ($items->isEmpty())
            <!-- 5.3 DEFAULT STATE IS EMPTY -->
            @if ($canWriteCurrentFolder)
                <!-- Large Dashed-border Drop Zone for users with Edit permission -->
                <div
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleDrop($event)"
                    :class="isDragging
                        ? 'border-emerald-500 bg-emerald-500/10 scale-[1.005]'
                        : 'border-slate-300 bg-white dark:border-neutral-800 dark:bg-neutral-900/60'"
                    class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-20 text-center transition-all duration-200 cursor-pointer"
                    @click="triggerFileInput()"
                >
                    <div class="flex size-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 mb-4 shadow-2xs">
                        <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                    </div>

                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                        {{ $currentFolder ? __('This folder is empty') : __('This project is empty') }}
                    </h2>

                    <p class="mt-2 max-w-md text-sm text-slate-500 dark:text-neutral-400 leading-relaxed">
                        {{ __('Drag and drop files or folders here, or use the buttons above') }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3" @click.stop>
                        <button
                            type="button"
                            @click="triggerFileInput()"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700 transition"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            {{ __('Upload files') }}
                        </button>

                        <button
                            type="button"
                            @click="triggerFolderInput()"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700 transition"
                        >
                            <svg class="size-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            {{ __('Upload folder') }}
                        </button>
                    </div>
                </div>
            @else
                <!-- Read-only Empty State: No upload zone -->
                <div class="flex flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-20 text-center dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-neutral-800 mb-3">
                        <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h2 class="text-base font-bold text-slate-800 dark:text-neutral-200">
                        {{ __('No items available') }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-400 max-w-sm">
                        {{ __('This project does not contain any files or folders yet.') }}
                    </p>
                </div>
            @endif
        @else
            <!-- 5.3 Items Table (Folders sorted before files) -->
            <div
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleDrop($event)"
                :class="isDragging ? 'ring-2 ring-emerald-500 bg-emerald-500/5' : ''"
                class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs transition-all dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-neutral-300">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-neutral-800 dark:bg-neutral-800/50">
                            <tr>
                                <th class="px-6 py-3.5">{{ __('Name') }}</th>
                                <th class="px-6 py-3.5">{{ __('Size') }}</th>
                                <th class="px-6 py-3.5">{{ __('Uploaded by') }}</th>
                                <th class="px-6 py-3.5">{{ __('Modified') }}</th>
                                <th class="px-6 py-3.5 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-neutral-800">
                            @foreach ($items as $item)
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-neutral-800/40 transition">
                                    <!-- Name Column with Folder or File Icon -->
                                    <td class="px-6 py-3.5 font-medium text-slate-900 dark:text-white">
                                        @if ($item->isFolder() && in_array((int) $item->id, $readableItemIds, true))
                                            <a
                                                href="{{ route('projects.show', [$team, $project]).'?folder='.$item->id }}"
                                                class="flex items-center gap-3 hover:text-emerald-600 transition group"
                                                wire:navigate
                                            >
                                                <div class="flex size-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 shrink-0">
                                                    <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                    </svg>
                                                </div>
                                                <span class="font-bold text-sm group-hover:underline">{{ $item->name }}</span>
                                            </a>
                                        @else
                                            <div class="flex items-center gap-3">
                                                <div @class([
                                                    'flex size-8 items-center justify-center rounded-lg shrink-0',
                                                    'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400' => $item->isFolder(),
                                                    'bg-slate-100 text-slate-600 dark:bg-neutral-800 dark:text-neutral-300' => $item->isFile(),
                                                ])>
                                                    @if ($item->isFolder())
                                                        <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                        </svg>
                                                    @else
                                                        <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    @endif
                                                </div>
                                                <span class="text-sm font-medium text-slate-800 dark:text-neutral-200">{{ $item->name }}</span>
                                            </div>
                                        @endif
                                        @if ($item->scan_status === 'malware')
                                            <div class="mt-1 text-xs font-semibold text-rose-700 dark:text-rose-400">
                                                {{ __('Malware detected: :signature', ['signature' => $item->malware_signature]) }}
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Size -->
                                    <td class="px-6 py-3.5 text-xs text-slate-500 dark:text-neutral-400 font-mono">
                                        {{ $item->formattedSize() }}
                                    </td>

                                    <!-- Uploaded by -->
                                    <td class="px-6 py-3.5 text-xs text-slate-600 dark:text-neutral-300">
                                        {{ $item->creator->name ?? 'System' }}
                                    </td>

                                    <!-- Modified -->
                                    <td class="px-6 py-3.5 text-xs text-slate-500 dark:text-neutral-400">
                                        {{ $item->updated_at->diffForHumans() }}
                                    </td>

                                    <!-- Three-dot Menu -->
                                    <td class="px-6 py-3.5 text-right relative" x-data="{ menuOpen: false }">
                                        <div class="inline-block text-left">
                                            <button
                                                type="button"
                                                @click="menuOpen = !menuOpen"
                                                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-200 transition"
                                            >
                                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                                </svg>
                                            </button>

                                            <!-- Dropdown Menu -->
                                            <div
                                                x-cloak
                                                x-show="menuOpen"
                                                @click.outside="menuOpen = false"
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="opacity-100 scale-100"
                                                x-transition:leave-end="opacity-0 scale-95"
                                                class="absolute right-6 mt-1 w-36 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg z-30 text-left text-xs dark:border-neutral-800 dark:bg-neutral-900"
                                            >
                                                @if ($item->isFile() && in_array((int) $item->id, $readableItemIds, true))
                                                    @if (in_array((int) $item->id, $previewableItemIds, true))
                                                        <a
                                                            href="{{ route('projects.items.preview', [$team, $project, $item]) }}"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                                        >
                                                            <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6.75 9.75-6.75S21.75 12 21.75 12 18.25 18.75 12 18.75 2.25 12 2.25 12z" />
                                                                <circle cx="12" cy="12" r="3" />
                                                            </svg>
                                                            {{ __('View') }}
                                                        </a>
                                                    @endif
                                                    <a
                                                        href="{{ route('projects.items.download', [$team, $project, $item]) }}"
                                                        class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                                    >
                                                        <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                        {{ __('Download') }}
                                                    </a>
                                                @elseif ($item->isFolder() && in_array((int) $item->id, $readableItemIds, true))
                                                    <a
                                                        href="{{ route('projects.items.download', [$team, $project, $item]) }}"
                                                        class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                                    >
                                                        <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                        {{ __('Download as ZIP') }}
                                                    </a>
                                                @endif

                                                <!-- Rename and Delete require project and item write permission -->
                                                @if ($canEdit && in_array((int) $item->id, $writableItemIds, true))
                                                    <button
                                                        type="button"
                                                        @click="menuOpen = false; openRename({{ $item->id }}, '{{ addslashes($item->name) }}')"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                                    >
                                                        <svg class="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        {{ __('Rename') }}
                                                    </button>

                                                    <form method="POST" action="{{ route('projects.items.destroy', [$team, $project, $item]) }}" onsubmit="return confirm('Delete this {{ $item->kind }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="flex w-full items-center gap-2 px-3 py-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                                                        >
                                                            <svg class="size-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            {{ __('Delete') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- 5.3 Small Collapsible Upload Tray at Bottom Right -->
        <div
            x-cloak
            x-show="uploadTrayOpen"
            class="fixed bottom-5 right-5 z-40 w-80 sm:w-96 rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden dark:border-neutral-800 dark:bg-neutral-900"
        >
            <!-- Tray Header -->
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/90 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-800/60">
                <div class="flex items-center gap-2">
                    <span class="flex size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">
                        {{ __('File Uploads') }} (<span x-text="uploads.length"></span>)
                    </h4>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="uploadTrayMinimized = !uploadTrayMinimized"
                        class="rounded p-1 text-slate-400 hover:bg-slate-200 dark:hover:bg-neutral-700"
                    >
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path x-show="!uploadTrayMinimized" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            <path x-show="uploadTrayMinimized" stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="uploadTrayOpen = false"
                        class="rounded p-1 text-slate-400 hover:bg-slate-200 dark:hover:bg-neutral-700"
                    >
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Tray Items List -->
            <div x-show="!uploadTrayMinimized" class="max-h-60 overflow-y-auto p-3 space-y-3 divide-y divide-slate-100 dark:divide-neutral-800 text-xs">
                <template x-for="item in uploads" :key="item.id">
                    <div class="pt-2 first:pt-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="truncate font-medium text-slate-800 dark:text-neutral-200" x-text="item.name"></span>
                            <span class="shrink-0 text-[10px] font-bold"
                                  :class="{
                                      'text-emerald-600': item.status === 'success',
                                      'text-rose-600': item.status === 'error',
                                      'text-amber-600': item.status === 'cancelled',
                                      'text-slate-500': item.status === 'uploading'
                                  }"
                                  x-text="item.status === 'success' ? 'Uploaded' : (item.status === 'error' ? 'Failed' : (item.status === 'cancelled' ? 'Cancelled' : item.progress + '%'))">
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden dark:bg-neutral-800">
                            <div
                                class="h-full rounded-full transition-all duration-200"
                                :class="{
                                    'bg-emerald-600': item.status === 'success',
                                    'bg-rose-500': item.status === 'error',
                                    'bg-amber-500': item.status === 'cancelled',
                                    'bg-emerald-500': item.status === 'uploading'
                                }"
                                :style="'width: ' + item.progress + '%'"
                            ></div>
                        </div>

                        <!-- Error or cancellation message -->
                        <template x-if="(item.status === 'error' || item.status === 'cancelled') && item.errorMsg">
                            <p class="mt-1 text-[10px] leading-tight"
                               :class="item.status === 'cancelled' ? 'text-amber-600' : 'text-rose-600'"
                               x-text="item.errorMsg"></p>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- 5.3 "New folder" Modal -->
        @if ($canWriteCurrentFolder)
            <div
                x-cloak
                x-show="newFolderModalOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-modal="true"
            >
                <div
                    x-show="newFolderModalOpen"
                    x-transition:enter="ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs"
                    @click="newFolderModalOpen = false"
                ></div>

                <div class="flex min-h-screen items-center justify-center p-4">
                    <div
                        x-show="newFolderModalOpen"
                        x-transition:enter="ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-neutral-900 dark:border dark:border-neutral-800"
                        @click.stop
                    >
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ __('New folder') }}
                        </h3>

                        <form method="POST" action="{{ route('projects.folders.store', [$team, $project]) }}" class="mt-4 flex flex-col gap-4">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}" />

                            <div>
                                <label for="folder_name" class="block text-xs font-semibold text-slate-700 dark:text-neutral-300">
                                    {{ __('Folder name') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="folder_name"
                                    name="name"
                                    type="text"
                                    required
                                    autofocus
                                    placeholder="source-code"
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                                />
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-2">
                                <button
                                    type="button"
                                    @click="newFolderModalOpen = false"
                                    class="rounded-xl border border-slate-300 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-300"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700"
                                >
                                    {{ __('Create folder') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Rename Modal -->
            <div
                x-cloak
                x-show="renameModalOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-modal="true"
            >
                <div
                    x-show="renameModalOpen"
                    x-transition:enter="ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs"
                    @click="renameModalOpen = false"
                ></div>

                <div class="flex min-h-screen items-center justify-center p-4">
                    <div
                        x-show="renameModalOpen"
                        x-transition:enter="ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-neutral-900 dark:border dark:border-neutral-800"
                        @click.stop
                    >
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ __('Rename Item') }}
                        </h3>

                        <form
                            method="POST"
                            :action="'{{ url('teams/'.$team->slug.'/projects/'.$project->slug.'/items') }}/' + activeRenameItem.id"
                            class="mt-4 flex flex-col gap-4"
                        >
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="rename_input" class="block text-xs font-semibold text-slate-700 dark:text-neutral-300">
                                    {{ __('Name') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="rename_input"
                                    name="name"
                                    type="text"
                                    x-model="activeRenameItem.name"
                                    required
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                                />
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-2">
                                <button
                                    type="button"
                                    @click="renameModalOpen = false"
                                    class="rounded-xl border border-slate-300 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:text-neutral-300"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700"
                                >
                                    {{ __('Save') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
