<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    use HasFactory;

    protected $table = 'surveys.survey_questions';

    protected $fillable = [
        'survey_id',
        'question_text',
        'type',
        'order',
        'is_required',
        'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'order'       => 'integer',
        'config'      => 'array',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }

    public function options()
    {
        return $this->hasMany(SurveyQuestionOption::class, 'question_id')->orderBy('order');
    }

    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class, 'question_id');
    }
}
