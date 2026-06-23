<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConcertRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        
        return auth()->user() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,
     */
    public function rules(): array
    {
        return [
            // Concert basic info
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.am' => ['nullable', 'string', 'max:255'],
            
            'artist' => ['required', 'array'],
            'artist.en' => ['required', 'string', 'max:255'],
            'artist.am' => ['nullable', 'string', 'max:255'],
            
            'venue' => ['required', 'array'],
            'venue.en' => ['required', 'string', 'max:255'],
            'venue.am' => ['nullable', 'string', 'max:255'],
            
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.am' => ['nullable', 'string'],
            
            // Date and time
            'concert_date' => ['required', 'date', 'after:now'],
            'door_open_time' => ['required', 'date', 'after:now', 'before:concert_date'],
            
            // Capacity and status
            'max_capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', 'string', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
            
            // Image
            'image_url' => ['nullable', 'string', 'url', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'], // 2MB
        ];
    }

    /**
     * Get the validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Concert name is required',
            'name.en.required' => 'Concert name in English is required',
            'name.en.max' => 'Concert name in English cannot exceed 255 characters',
            'name.am.max' => 'Concert name in Amharic cannot exceed 255 characters',
            
            'artist.required' => 'Artist name is required',
            'artist.en.required' => 'Artist name in English is required',
            'artist.en.max' => 'Artist name in English cannot exceed 255 characters',
            'artist.am.max' => 'Artist name in Amharic cannot exceed 255 characters',
            
            'venue.required' => 'Venue is required',
            'venue.en.required' => 'Venue in English is required',
            'venue.en.max' => 'Venue in English cannot exceed 255 characters',
            'venue.am.max' => 'Venue in Amharic cannot exceed 255 characters',
            
            'concert_date.required' => 'Concert date is required',
            'concert_date.date' => 'Concert date must be a valid date',
            'concert_date.after' => 'Concert date must be in the future',
            
            'door_open_time.required' => 'Door open time is required',
            'door_open_time.date' => 'Door open time must be a valid date',
            'door_open_time.after' => 'Door open time must be in the future',
            'door_open_time.before' => 'Door open time must be before the concert date',
            
            'max_capacity.required' => 'Maximum capacity is required',
            'max_capacity.integer' => 'Maximum capacity must be a number',
            'max_capacity.min' => 'Maximum capacity must be at least 1',
            'max_capacity.max' => 'Maximum capacity cannot exceed 100,000',
            
            'status.in' => 'Status must be one of: upcoming, ongoing, completed, cancelled',
            
            'image_url.url' => 'Image URL must be a valid URL',
            'image.image' => 'File must be an image',
            'image.mimes' => 'Image must be of type: jpeg, png, jpg, gif, webp',
            'image.max' => 'Image size cannot exceed 2MB',
        ];
    }
 
   
}