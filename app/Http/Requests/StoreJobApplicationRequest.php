<?php

namespace App\Http\Requests;

use App\Models\JobApplication;
use App\Rules\Recaptcha;
use App\Services\Security\Recaptcha as RecaptchaVerifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s()\-]{10,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'birth_date' => ['nullable', 'date', 'before:-16 years'],
            'position' => ['nullable', 'string', 'max:100', Rule::in(JobApplication::POSITIONS)],
            'message' => ['nullable', 'string', 'max:2000'],
            // Yalnız belge biçimleri; 5 MB. İçerik türü sunucuda da denetlenir.
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'kvkk' => ['accepted'],
            'website' => ['nullable', 'max:0'], // honeypot
            RecaptchaVerifier::FIELD => app(RecaptchaVerifier::class)->enabled()
                ? ['required', new Recaptcha]
                : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Lütfen geçerli bir telefon numarası girin.',
            'birth_date.before' => 'Başvuru için en az 16 yaşında olmalısınız.',
            'cv.mimes' => 'Özgeçmiş PDF ya da Word dosyası olmalıdır.',
            'cv.max' => 'Özgeçmiş dosyası en fazla 5 MB olabilir.',
            'kvkk.accepted' => 'Devam etmek için kişisel verilerin işlenmesini onaylamanız gerekir.',
            'website.max' => 'Form doğrulanamadı.',
            RecaptchaVerifier::FIELD.'.required' => 'Güvenlik doğrulaması tamamlanamadı. Sayfayı yenileyip tekrar deneyin.',
        ];
    }
}
