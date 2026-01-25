<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Atribut yang bisa diisi (mass assignable)
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',             // Role user
        'last_login_at',    // Waktu login terakhir
    ];

    /**
     * Atribut yang disembunyikan saat serialisasi (misal ke JSON)
     *
     * @var string[]
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Atribut yang otomatis di-cast ke tipe data tertentu
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',           // Laravel 12 otomatis hash
        'last_login_at' => 'datetime',    // Carbon instance
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * OPTIONAL: Method helper untuk cek apakah user admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'Super Admin' || $this->role === 'Admin';
    }
}
