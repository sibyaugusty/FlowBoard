<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'user_id', 'color'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'board_members')->withPivot('role')->withTimestamps();
    }

    public function columns()
    {
        return $this->hasMany(Column::class)->orderBy('position');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function allMembers()
    {
        return $this->members->push($this->owner)->unique('id');
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->user_id === $user->id || $this->members->contains($user);
    }
}
