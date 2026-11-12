<div class="overflow-hidden rounded-xl border border-slate-200 dark:border-neutral-800">
    @php
        $projectPermission = $isNewRole
            ? (int) old('permissions.project.'.$project->id, 0)
            : (int) ($permissionValues['project:'.$project->id] ?? 0);
    @endphp

    <div class="grid grid-cols-[minmax(12rem,1fr)_minmax(10rem,15rem)] gap-4 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-neutral-800/60 dark:text-neutral-400">
        <span>{{ __('Resource') }}</span>
        <span>{{ __('Permission') }}</span>
    </div>

    <div class="divide-y divide-slate-100 dark:divide-neutral-800" x-data="{ expandedFolders: {}, isVisible(ancestorIds) { return ancestorIds.every((folderId) => this.expandedFolders[folderId]) } }">
        <div class="grid grid-cols-[minmax(12rem,1fr)_minmax(10rem,15rem)] items-center gap-4 px-4 py-3">
            <label for="{{ $formPrefix }}-permission-project-{{ $project->id }}" class="font-medium text-slate-800 dark:text-neutral-200">
                {{ __('Project') }}: {{ $project->name }} (#{{ $project->id }})
            </label>
            <select
                id="{{ $formPrefix }}-permission-project-{{ $project->id }}"
                name="permissions[project][{{ $project->id }}]"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
            >
                @foreach ($permissionOptions as $mask => $label)
                    <option value="{{ $mask }}" @selected($projectPermission === $mask)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>

        @foreach ($resourceTree as $node)
            @php
                $item = $node['item'];
                $resourceTypeCode = $item->kind;
                $permissionKey = $resourceTypeCode.':'.$item->id;
                $permission = $isNewRole
                    ? (int) old('permissions.'.$resourceTypeCode.'.'.$item->id, 0)
                    : (int) ($permissionValues[$permissionKey] ?? 0);
            @endphp

            <div
                x-cloak
                x-show="isVisible(@js($node['ancestorIds']))"
                class="grid grid-cols-[minmax(12rem,1fr)_minmax(10rem,15rem)] items-center gap-4 px-4 py-3"
            >
                <div class="flex min-w-0 items-center gap-2" style="padding-left: {{ $node['depth'] * 1.25 }}rem">
                    @if ($node['hasChildren'])
                        <button
                            type="button"
                            class="inline-flex size-6 shrink-0 items-center justify-center rounded text-slate-500 hover:bg-slate-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                            aria-label="{{ __('Toggle contents of :folder', ['folder' => $item->name]) }}"
                            :aria-expanded="Boolean(expandedFolders[{{ $item->id }}]).toString()"
                            @click="expandedFolders[{{ $item->id }}] = ! expandedFolders[{{ $item->id }}]"
                        >
                            <svg class="size-4 transition-transform" :class="{ 'rotate-90': expandedFolders[{{ $item->id }}] }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @else
                        <span class="inline-block size-6 shrink-0" aria-hidden="true"></span>
                    @endif

                    <span class="truncate font-medium text-slate-800 dark:text-neutral-200">
                        {{ $item->isFolder() ? __('Folder') : __('File') }}: {{ $item->name }} (#{{ $item->id }})
                    </span>
                </div>

                <div class="flex flex-col gap-2">
                    <select
                        id="{{ $formPrefix }}-permission-{{ $resourceTypeCode }}-{{ $item->id }}"
                        name="permissions[{{ $resourceTypeCode }}][{{ $item->id }}]"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        @foreach ($permissionOptions as $mask => $label)
                            <option value="{{ $mask }}" @selected($permission === $mask)>{{ __($label) }}</option>
                        @endforeach
                    </select>

                    @if ($item->isFolder())
                        <label class="flex items-start gap-2 text-xs text-slate-600 dark:text-neutral-400">
                            <input
                                type="checkbox"
                                name="apply_to_descendants[{{ $item->id }}]"
                                value="1"
                                @checked($isNewRole && old('apply_to_descendants.'.$item->id))
                                class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-neutral-700 dark:bg-neutral-800"
                            />
                            <span>
                                {{ __('Apply this permission to all current contents') }}
                                <span class="block text-slate-500 dark:text-neutral-500">
                                    {{ __('This overrides the individual permissions of those items.') }}
                                </span>
                            </span>
                        </label>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
