<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientDocument extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'client_documents';

//    protected $fillable = [
//        'client_id',
//        'title',
//        'description',
//        'file_path',
//        'file_name',
//        'file_size',
//        'file_type',
//        'category',
//        'status',
//        'created_by',
//        'updated_by',
//    ];



    /**
     * Get the client associated with the document.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the document.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to search documents by title or description.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('file_name', 'like', "%{$search}%");
        });
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the file URL.
     */
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Determine if the file is an image.
     */
    public function isImage()
    {
        return in_array(strtolower($this->file_type), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ]);
    }

    /**
     * Determine if the file is a PDF.
     */
    public function isPdf()
    {
        return $this->file_type === 'application/pdf';
    }
}
