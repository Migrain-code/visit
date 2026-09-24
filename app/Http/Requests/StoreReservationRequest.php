<?php

namespace App\Http\Requests;

use App\Models\TourDeparture;
use App\Rules\Recaptcha;
use App\Services\Security\Recaptcha as RecaptchaVerifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
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
            'tour_departure_id' => ['nullable', 'integer', 'exists:tour_departures,id'],
            'people_count' => ['required', 'integer', 'min:1', 'max:60'],
            'message' => ['nullable', 'string', 'max:2000'],
            'kvkk' => ['accepted'],
            'website' => ['nullable', 'max:0'], // honeypot: gerçek kullanıcılar boş bırakır
            // reCAPTCHA anahtarları girilmemişse kural listesi boş kalır ve form
            // aynen çalışmaya devam eder.
            RecaptchaVerifier::FIELD => app(RecaptchaVerifier::class)->enabled()
                ? ['required', new Recaptcha]
                : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Lütfen geçerli bir telefon numarası girin.',
            'kvkk.accepted' => 'Devam etmek için kişisel verilerin işlenmesini onaylamanız gerekir.',
            'people_count.required' => 'Kaç kişi katılacağınızı yazın.',
            'people_count.min' => 'Kişi sayısı en az 1 olmalıdır.',
            'people_count.max' => '60 kişiden büyük gruplar için lütfen bizi arayın.',
            'website.max' => 'Form doğrulanamadı.',
            RecaptchaVerifier::FIELD.'.required' => 'Güvenlik doğrulaması tamamlanamadı. Sayfayı yenileyip tekrar deneyin.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Tur seçildiyse gerçekten satışta olmalı.
            if ($departureId = $this->integer('tour_departure_id')) {
                if (! TourDeparture::query()->bookable()->whereKey($departureId)->exists()) {
                    $validator->errors()->add('tour_departure_id', 'Seçilen tur artık kayda açık değil. Lütfen başka bir tur seçin.');
                }
            }
        });
    }
}
