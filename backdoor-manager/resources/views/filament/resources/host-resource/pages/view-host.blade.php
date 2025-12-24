<x-filament-panels::page>
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center">
                    <x-heroicon-o-server class="w-10 h-10 mr-4 text-indigo-600" />
                    {{ $record->hostname }}
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 mt-2">
                    IP: {{ $record->ip_address }} • {{ $record->agents()->count() }} aplikasi
                </p>
            </div>
            <a href="{{ url('/admin/hosts') }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Kembali ke Daftar Host
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($record->agents() as $agent)
                <a href="/admin/agents/{{ $agent->id }}"
                   class="block p-6 bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 hover:shadow-xl hover:border-indigo-500 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ $agent->app_name }}
                        </h3>
                        <x-filament::badge 
                            :color="$agent->status === 'approved' ? 'success' : ($agent->status === 'pending' ? 'warning' : 'danger')">
                            {{ ucfirst($agent->status) }}
                        </x-filament::badge>
                    </div>

                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <p>
                            <strong>Stack:</strong> 
                            {{ implode(', ', $agent->tech_stack['framework'] ?? $agent->tech_stack['language'] ?? ['Unknown']) }}
                        </p>
                        <p>
                            <strong>Online:</strong> {{ $agent->last_seen_at?->diffForHumans() ?? 'Belum pernah' }}
                        </p>
                    </div>

                    @if($agent->alerts->count() > 0)
                        <div class="mt-4 pt-4 border-t border-gray-300 dark:border-gray-600">
                            <p class="text-red-600 dark:text-red-400 font-medium">
                                ⚠️ {{ $agent->alerts->count() }} deteksi backdoor
                                @if($agent->alerts->where('type', 'yara_webshell_match')->count() > 0)
                                    ({{ $agent->alerts->where('type', 'yara_webshell_match')->count() }} webshell kritis)
                                @endif
                            </p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>