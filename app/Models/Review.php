<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $visible = ['id', 'organisation_id', 'statement_id', 'user_id', 'accepted', 'review', 'created_at', 'updated_at'];
    protected $appends = ['updated_at_for_humans'];

    public function updatedAtForHumans(): Attribute
    {
        return new Attribute(
            get: fn($value) => Carbon::parse($this->updated_at)->format('Y-m-d H:i:s')
        );
    }

    public function reviewStatus()
    {
        return $this->belongsTo(ReviewStatus::class);
    }
}
