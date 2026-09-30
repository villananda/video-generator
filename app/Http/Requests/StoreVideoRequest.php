<?php

namespace App\Http\Requests;

use App\DTOs\CreateVideoData;
use App\Enums\AspectRatio;
use App\Enums\Resolution;
use App\Enums\VideoMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(VideoMode::class)],
            'prompt' => ['required', 'string', 'max:1000'],
            'image' => [
                Rule::requiredIf($this->input('mode') === VideoMode::Image->value),
                Rule::prohibitedIf($this->input('mode') === VideoMode::Text->value),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:10240',
            ],
            'aspect_ratio' => ['required', Rule::enum(AspectRatio::class)],
            'resolution' => ['required', Rule::enum(Resolution::class)],
            'single_scene' => ['boolean'],
            'no_dialogue' => ['boolean'],
            'background_music' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prompt' => 'deskripsi',
            'image' => 'gambar',
            'aspect_ratio' => 'rasio',
            'resolution' => 'resolusi',
        ];
    }

    public function toData(): CreateVideoData
    {
        return new CreateVideoData(
            mode: $this->enum('mode', VideoMode::class),
            prompt: $this->string('prompt')->trim()->value(),
            aspectRatio: $this->enum('aspect_ratio', AspectRatio::class),
            resolution: $this->enum('resolution', Resolution::class),
            singleScene: $this->boolean('single_scene'),
            noDialogue: $this->boolean('no_dialogue'),
            backgroundMusic: $this->boolean('background_music'),
            image: $this->file('image'),
            sessionId: $this->session()->getId(),
            userId: $this->user()?->id,
        );
    }
}
