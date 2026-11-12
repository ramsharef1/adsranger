<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostPurchaseSurveyResponse extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_value' => 'float',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(PostPurchaseSurvey::class, 'post_purchase_survey_id');
    }
}
