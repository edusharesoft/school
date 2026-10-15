<?php

namespace App\Models\Hostel;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Allocation extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'hostel_allocations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'room_fee' => 'decimal:2', 'meal_fee' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Students\Student::class, 'student_id');
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'hostel_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'hostel_room_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'hostel_bed_id');
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class, 'hostel_meal_plan_id');
    }

    public function monthlyTotal(): float
    {
        return round((float) $this->room_fee + (float) $this->meal_fee, 2);
    }
}
