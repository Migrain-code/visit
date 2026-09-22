<?php

namespace App\Support\Queue;

use Illuminate\Queue\Worker;

/**
 * pcntl fonksiyonları kapalı hostingde de çalışan kuyruk işçisi.
 *
 * Laravel'in işçisi yalnız pcntl EKLENTİSİNİN yüklü olup olmadığına bakar ve sonra
 * pcntl_async_signals(), pcntl_signal(), pcntl_alarm() fonksiyonlarını çağırır.
 * Paylaşımlı hostingde eklenti yüklüdür ama bu fonksiyonlar disable_functions ile
 * kapatılır: işçi daha ilk satırda "Call to undefined function" hatasıyla çöker,
 * cron çıktısı /dev/null'a gittiği için de hata görünmez; kuyruktaki işler birikir.
 *
 * Fonksiyonlar yoksa işçi sinyalsiz çalışır. Tek fark: takılan bir iş zaman aşımıyla
 * öldürülmez. Cron işçisi --max-time ile her dakika yenilendiği için bu kabul edilir.
 */
class SharedHostingWorker extends Worker
{
    protected function supportsAsyncSignals()
    {
        return parent::supportsAsyncSignals()
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_alarm');
    }

    /** Laravel'in kurduğu işçinin ayarlarını (bakım modu, kapsam sıfırlama) aynen devralır. */
    public static function from(Worker $worker): self
    {
        // Kapanış işçinin kapsamında çalışır ("self" orada Worker olur); sınıf adı açık yazılır.
        return (fn () => new SharedHostingWorker(
            $this->manager,
            $this->events,
            $this->exceptions,
            $this->isDownForMaintenance,
            $this->resetScope,
        ))->call($worker);
    }
}
