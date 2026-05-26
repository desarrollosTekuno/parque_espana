<?php

namespace App\Models\AdminClub;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationGuestListItem extends Model {
    use HasFactory, SoftDeletes;

    protected $table = 'guest_lists.guest_list_items';

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];

    // scope para obtener solo los invitados que no pagara el socio y no son cortesias
    public function scopeNotBillableNotComped($query)
    {
        return $query->where('is_billable_to_member', false)
                     ->where('is_comped', false);
    }
}
