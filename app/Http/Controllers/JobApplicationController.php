<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobApplicationRequest;
use App\Mail\JobApplicationReceived;
use App\Models\JobApplication;
use App\Services\Security\Recaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * İş başvurusu formu: panele "İş Başvuruları" olarak düşer.
 * Özgeçmiş dosyası herkese açık diske DEĞİL, özel diske yazılır.
 */
class JobApplicationController extends Controller
{
    public function create(): View
    {
        return view('job-application', [
            'positions' => JobApplication::POSITIONS,
            'metaTitle' => 'İş Başvurusu',
            'metaDescription' => 'Ekibimizde yer almak ister misin? Rehber, şoför, organizasyon ve sosyal medya için başvurunu bırak.',
            'canonical' => route('jobs.create'),
        ]);
    }

    public function store(StoreJobApplicationRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['kvkk', 'website', 'cv', Recaptcha::FIELD]);

        if ($request->hasFile('cv')) {
            $data['cv_path'] = $request->file('cv')->store('job-applications', 'local');
        }

        $application = JobApplication::query()->create($data + [
            'kvkk_accepted' => true,
            'status' => JobApplication::STATUS_NEW,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $this->notify($application);

        return redirect()->route('jobs.thanks')->with('application_sent', true);
    }

    public function thanks(): View
    {
        return view('thanks', [
            'heading' => 'Başvurun bize ulaştı',
            'text' => 'Başvurunu inceleyip en kısa sürede sana döneceğiz. Teşekkürler!',
            'metaTitle' => 'Başvurunuz Alındı',
            'metaDescription' => 'İş başvurunuz bize ulaştı.',
            'canonical' => route('jobs.thanks'),
            'robots' => 'noindex, follow',
        ]);
    }

    protected function notify(JobApplication $application): void
    {
        $email = setting('notification_email');

        if (blank($email)) {
            return;
        }

        try {
            Mail::to($email)->send(new JobApplicationReceived($application));
        } catch (\Throwable $e) {
            Log::warning('İş başvurusu e-postası gönderilemedi: '.$e->getMessage(), ['application_id' => $application->getKey()]);
        }
    }
}
