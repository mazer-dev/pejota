<x-filament-panels::page>
    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Materials</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalMaterials() }}</div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
            <div class="text-sm text-gray-500 dark:text-gray-400">Active Branches</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalBranches() }}</div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Stock Value</div>
            <div class="text-2xl font-bold text-green-600">${{ number_format($this->getTotalStockValue(), 2) }}</div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border-l-4 border-red-500">
            <div class="text-sm text-gray-500 dark:text-gray-400">Low Stock Alerts</div>
            <div class="text-2xl font-bold text-red-600">{{ $this->getLowStockMaterials()->count() }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Low Stock Alerts --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                ⚠️ Low Stock Alerts
            </h3>
            @if($this->getLowStockMaterials()->count() > 0)
                <div class="space-y-2">
                    @foreach($this->getLowStockMaterials() as $material)
                        <div class="flex justify-between items-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $material->name }}</p>
                                <p class="text-sm text-gray-500">SKU: {{ $material->sku }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-red-600 font-bold">{{ $material->stock_level }} {{ $material->unit }}</p>
                                <p class="text-xs text-gray-500">Reorder at: {{ $material->reorder_point }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-green-600 flex items-center gap-2">
                    ✅ All materials are above reorder point
                </p>
            @endif
        </div>

        {{-- Stock by Branch --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                🏢 Stock by Branch
            </h3>
            @if($this->getStockByBranch()->count() > 0)
                <div class="space-y-2">
                    @foreach($this->getStockByBranch() as $branch)
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $branch['name'] }}</span>
                            <span class="text-blue-600 font-bold">{{ number_format($branch['stock'], 2) }} units</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500">No branches found</p>
            @endif
        </div>
    </div>

    {{-- Recent Movements --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mt-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
            📋 Recent Stock Movements
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Date</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Type</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Material</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Qty</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">From</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">To</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                    @forelse($this->getRecentMovements() as $movement)
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $movement->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    {{ $movement->type === 'in' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $movement->type === 'out' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $movement->type === 'transfer' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ str_contains($movement->type, 'adjustment') ? 'bg-purple-100 text-purple-800' : '' }}
                                ">
                                    {{ strtoupper($movement->type) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $movement->material->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $movement->qty }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $movement->fromBranch->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $movement->toBranch->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No movements yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>