<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class Host extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $primaryKey = 'hostname';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function all($columns = ['*']): Collection
    {
        $query = \App\Models\Agent::select('hostname', 'ip_address')
            ->groupBy('hostname', 'ip_address')
            ->get()
            ->map(function ($item) {
                $host = new static();
                $host->hostname = $item->hostname;
                $host->ip_address = $item->ip_address;
                $host->agents_count = \App\Models\Agent::where('hostname', $item->hostname)->count();
                $host->total_alerts = \App\Models\Agent::where('hostname', $item->hostname)
                    ->withCount('alerts')
                    ->get()
                    ->sum('alerts_count');
                $host->critical_alerts = \App\Models\Agent::where('hostname', $item->hostname)
                    ->withCount(['alerts as critical_count' => fn ($q) => $q->where('type', 'yara_webshell_match')])
                    ->get()
                    ->sum('critical_count');
                return $host;
            });

        return new Collection($query);
    }

    public function agents()
    {
        return \App\Models\Agent::where('hostname', $this->hostname)->orderBy('app_name')->get();
    }
}