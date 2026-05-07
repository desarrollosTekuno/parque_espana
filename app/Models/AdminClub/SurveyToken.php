<?php

namespace App\Models\AdminClub;

use App\Models\Members\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyToken extends Model
{
    use HasFactory;

    protected $table = 'surveys.survey_tokens';

    protected $fillable = [
        'survey_id',
        'member_id',
        'token',
        'used',
        'used_at',
    ];

    protected $casts = [
        'used'    => 'boolean',
        'used_at' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function response()
    {
        return $this->hasOne(SurveyResponse::class, 'token_id');
    }
}
