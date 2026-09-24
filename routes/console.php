<?php

use App\Models\CommandRun;
use App\Support\Console\InProcess;

/*
|--------------------------------------------------------------------------
| Zamanlanmış görevler
|--------------------------------------------------------------------------
| Sunucuda tek bir cron satırı yeterlidir:
|   * * * * * cd /proje/yolu && php artisan schedule:run >> /dev/null 2>&1
|
| Görevler Schedule::command() ile DEĞİL InProcess::command() ile eklenir: hosting
| proc_open'ı kapatıyor ve Schedule::command() ayrı süreç başlatamadığı için hiç
| çalışmıyor. Ayrıntı: App\Support\Console\InProcess.
|
| Hepsi withoutOverlapping: uzun süren bir tur, bir sonrakiyle çakışmasın.
*/

/*
 * Nabız: panel bu damgaya bakarak cron'un ve kuyruk işçisinin çalıştığını gösterir.
 * Terminal erişimi olmayan sunucuda bunu görmenin başka yolu yok.
 */
InProcess::command('system:heartbeat')->everyMinute()->withoutOverlapping();

// Panelden çalıştırılan komutların 30 günden eski kayıtları silinir.
InProcess::command('model:prune', ['--model' => [CommandRun::class]])->dailyAt('00:30')->withoutOverlapping();

/*
 * Tur zamanı yaklaşan turlarda araçsız kalan grupları boş koltuklara yerleştirir.
 * Yerleşmiş gruplara dokunmaz; gruplar bölünmez. Saat başı koşar ki son anda gelen
 * kayıtlar da kalkıştan önce bir araca yerleşsin.
 */
InProcess::command('tours:allocate-upcoming')->hourly()->withoutOverlapping();
