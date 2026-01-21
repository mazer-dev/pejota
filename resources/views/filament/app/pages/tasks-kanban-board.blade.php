<x-filament-panels::page>
    {{-- Single Root Element --}}
    <div x-data="{ draggingOverStatus: null }"> 
        <div class="flex gap-4 overflow-x-auto pb-4" style="min-height: 70vh;">
            @foreach($this->getStatuses() as $status)
                <div class="flex-shrink-0 w-80 bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
                    {{-- Status Header --}}
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background-color: {{ $status->color }}"></span>
                            {{ $status->name }}
                        </h3>
                        <span class="bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs font-medium px-2 py-1 rounded-full">
                            {{ $this->getTasks()->where('status_id', $status->id)->count() }}
                        </span>
                    </div>
                    
                    {{-- Tasks Column (Drop Zone) --}}
                    <div class="space-y-3 min-h-[250px] transition-colors duration-200 rounded-lg p-1"
                         x-on:dragover.prevent="draggingOverStatus = {{ $status->id }}"
                         x-on:dragleave.prevent="draggingOverStatus = null"
                         x-on:drop.prevent="
                            const taskId = event.dataTransfer.getData('taskId');
                            if (taskId) {
                                $wire.updateTaskStatus(parseInt(taskId), {{ $status->id }});
                            }
                            draggingOverStatus = null;
                         "
                         :class="{ 'bg-primary-500/10 border-2 border-dashed border-primary-500': draggingOverStatus === {{ $status->id }} }">
                        
                        @foreach($this->getTasks()->where('status_id', $status->id) as $task)
                            <div class="bg-white dark:bg-gray-700 rounded-lg p-3 shadow-sm cursor-grab border border-gray-200 dark:border-gray-600 hover:shadow-md transition-shadow active:cursor-grabbing relative"
                                 draggable="true"
                                 x-on:dragstart="
                                    event.dataTransfer.setData('taskId', '{{ $task->id }}');
                                    event.dataTransfer.effectAllowed = 'move';
                                 ">
                                
                                {{-- Milestone Indicator [cite: 13, 15] --}}
                                @if($task->is_milestone)
                                    <div class="absolute -top-2 -right-1 bg-amber-500 text-white text-[10px] px-2 py-0.5 rounded-full shadow-sm font-bold">
                                        🚩 MILESTONE
                                    </div>
                                @endif

                                <p class="font-medium text-gray-900 dark:text-white mb-2">
                                    {{ $task->title }}
                                </p>
                                
                                <div class="flex flex-wrap gap-2 text-xs">
                                    @if($task->project)
                                        <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded">
                                            📁 {{ $task->project->name }} 
                                        </span>
                                    @endif

                                    {{-- Dependency Info  --}}
                                    @if($task->dependencies_count > 0)
                                        <span class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 px-2 py-1 rounded">
                                            🔗 {{ $task->dependencies_count }} Dep.
                                        </span>
                                    @endif
                                </div>
                                
                                {{-- Progress Tracking [cite: 14, 61] --}}
                                @if($task->percent_complete > 0)
                                    <div class="mt-3">
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-600">
                                            <div class="h-1.5 rounded-full bg-primary-500 transition-all" 
                                                 style="width: {{ $task->percent_complete }}%"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>