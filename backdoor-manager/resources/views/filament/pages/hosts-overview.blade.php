<x-filament-panels::page>
    <div class="fi-section py-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @php
                $hosts = \App\Models\Agent::select('hostname', 'ip_address')
                    ->groupBy('hostname', 'ip_address')
                    ->orderBy('hostname')
                    ->get();
            @endphp

            @forelse($hosts as $host)
                @php
                    $agents = \App\Models\Agent::where('hostname', $host->hostname)->orderBy('app_name')->get();
                    $totalAgents = $agents->count();
                    $totalAlerts = $agents->sum(fn($a) => $a->alerts->count());
                    $criticalAlerts = $agents->sum(fn($a) => $a->alerts->where('type', 'yara_webshell_match')->count());
                    $lastSeen = $agents->max('last_seen_at');
                    $hasCritical = $criticalAlerts > 0;
                @endphp

                <div class="fi-ta-record fi-ta-record-clickable fi-ta-record-clickable-hover fi-ta-record-clickable-hover-rounded-md relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $host->hostname }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $host->ip_address }}
                            </p>
                        </div>

                        @if($hasCritical)
                            <x-heroicon-o-shield-exclamation class="h-6 w-6 text-danger-600 dark:text-danger-400 animate-pulse" />
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Aplikasi</p>
                            <p class="font-semibold text-gray-950 dark:text-white">
                                {{ $totalAgents }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Total Alert</p>
                            <p class="font-semibold text-gray-950 dark:text-white">
                                {{ $totalAlerts }}
                            </p>
                        </div>
                    </div>

                    @if($criticalAlerts > 0)
                        <div class="mb-4">
                            <x-filament::badge color="danger">
                                ⚠️ {{ $criticalAlerts }} Webshell Kritis
                            </x-filament::badge>
                        </div>
                    @endif

                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Last seen: {{ $lastSeen?->diffForHumans() ?? 'Tidak ada data' }}
                    </div>

                    <!-- List Mini App di Dalam Card Host -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            Aplikasi di host ini:
                        </p>
                        <div class="space-y-3">
                            @foreach($agents as $agent)
                                <a href="/admin/agents/{{ $agent->id }}" class="flex items-center justify-between group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-2 h-2 rounded-full 
                                            {{ $agent->status === 'approved' ? 'bg-success-500' : ($agent->status === 'pending' ? 'bg-warning-500' : 'bg-danger-500') }}">
                                        </div>
                                        <span class="text-sm text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            {{ $agent->app_name }}
                                        </span>
                                    </div>
                                    @if($agent->alerts->count() > 0)
                                        <span class="text-xs text-red-600 dark:text-red-400 font-medium">
                                            {{ $agent->alerts->count() }} alert
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12">
                    <x-heroicon-o-server class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Belum ada host terdaftar</h3>
                    <p class="mt-1 text-sm text-gray-500">Agent dari server kamu akan muncul di sini setelah registrasi.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>