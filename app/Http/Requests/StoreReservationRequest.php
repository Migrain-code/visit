<?php

namespace App\Http\Requests;

use App\Models\District;
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
            'tour_id' => ['nullable', 'integer', 'exists:tours,id'],
            'tour_departure_id' => ['nullable', 'integer', 'exists:tour_departures,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'people_count' => ['required', 'integer', 'min:1', 'max:60'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
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
            'kvkk.accepted' => 'Devam etmek için KVKK aydınlatma metnini onaylamanız gerekir.',
            'people_count.required' => 'Kaç kişi katılacağınızı yazın.',
            'people_count.min' => 'Kişi sayısı en az 1 olmalıdır.',
            'people_count.max' => '60 kişiden büyük gruplar için lütfen bizi arayın.',
            'preferred_date.after_or_equal' => 'Tercih edilen tarih bugünden önce olamaz.',
            'website.max' => 'Form doğrulanamadı.',
            RecaptchaVerifier::FIELD.'.required' => 'Güvenlik doğrulaması tamamlanamadı. Sayfayı yenileyip tekrar deneyin.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $provinceId = $this->integer('province_id');
            $districtId = $this->integer('district_id');

            if ($provinceId && $districtId) {
                $matches = District::query()->whereKey($districtId)->where('province_id', $provinceId)->exists();

                if (! $matches) {
                    $validator->errors()->add('district_id', 'Seçilen ilçe seçilen ile ait değil.');
                }
            }

            // Sefer seçildiyse gerçekten satışta olmalı ve seçilen tura ait olmalı.
            if ($departureId = $this->integer('tour_departure_id')) {
                $departure = TourDeparture::query()->bookable()->find($departureId);

                if (! $departure) {
                    $validator->errors()->add('tour_departure_id', 'Seçilen tarih artık kayda açık değil. Lütfen başka bir tarih seçin.');
                } elseif ($this->integer('tour_id') && (int) $departure->tour_id !== $this->integer('tour_id')) {
                    $validator->errors()->add('tour_departure_id', 'Seçilen tarih bu tura ait değil.');
                }
            }
        });
    }
}
