<?php

namespace App\Models\AdminClub;

use App\Models\Administrator\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.surveys';

    protected $fillable = [
        'club_id',
        'title',
        'description',
        'status',
        'slug',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id', 'id', 'clubs.clubs');
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class, 'survey_id')->orderBy('order');
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class, 'survey_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForClub($query, $clubId)
    {
        return $query->where('club_id', $clubId);
    }
}
