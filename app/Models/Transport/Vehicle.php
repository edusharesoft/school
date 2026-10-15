<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    protected $table = 'transport_vehicles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'capacity' => 'integer', 'insurance_expiry' => 'date', 'fitness_expiry' => 'date', 'tax_token_expiry' => 'date', 'route_permit_expiry' => 'date'];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TransportRoute::class, 'vehicle_id');
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'transport_vehicle_id');
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class, 'transport_vehicle_id');
    }

    public const TYPES = ['bus', 'minibus', 'microbus', 'van', 'other'];

    public const FUELS = ['diesel', 'petrol', 'octane', 'cng', 'electric'];

    public const STATUSES = ['active', 'maintenance', 'retired'];

    /** Papers that expire: key => label. */
    public const DOCUMENTS = ['insurance_expiry' => 'Insurance', 'fitness_expiry' => 'Fitness certificate', 'tax_token_expiry' => 'Tax token', 'route_permit_expiry' => 'Route permit'];

    /** Documents already expired or expiring within $days. @return array<string,\Illuminate\Support\Carbon> */
    public function expiring(int $days = 30): array
    {
        $out = [];
        foreach (array_keys(self::DOCUMENTS) as $f) {
            if ($this->{$f} && $this->{$f}->lte(now()->addDays($days)->endOfDay())) {
                $out[$f] = $this->{$f};
            }
        }

        return $out;
    }

    public function label(): string
    {
        return $this->registration_no.($this->name ? ' — '.$this->name : '');
    }
}
