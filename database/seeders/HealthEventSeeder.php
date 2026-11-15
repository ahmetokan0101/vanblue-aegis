<?php

namespace Database\Seeders;

use App\Models\HealthEvent;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic mission medical log so the dashboard feed is populated for demos.
 */
class HealthEventSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [400, 'system', 'info', null, 'Görev günlük senkronizasyonu', 'Tıbbi kayıtlar Dünya Görev Kontrol ile senkronize edildi (gecikme 13 dk).'],
            [310, 'countermeasure', 'success', 'astronot2', 'Karşı-tedbir tamamlandı', 'ARED direnç egzersizi · 45 dk'],
            [255, 'cognitive', 'success', 'astronot1', 'PVT testi: %93', 'Ortalama 291 ms, en hızlı 244 ms'],
            [190, 'countermeasure', 'success', 'astronot4', 'Karşı-tedbir tamamlandı', 'LBNP alt vücut vakum · 20 dk'],
            [140, 'alert', 'warning', 'astronot3', 'Kabin CO₂ hafif yükseldi', 'İtki bölmesinde CO₂ %0.48 — CDRA filtresi devreye alındı.'],
            [95, 'triage', 'info', 'astronot3', 'Otonom triage: NOMİNAL', 'CO₂ normale döndü, ek aksiyon gerekmiyor.'],
            [42, 'cognitive', 'warning', 'astronot2', 'PVT testi: %78', 'Ortalama 382 ms — uyku borcu tespit edildi, dinlenme önerildi.'],
            [12, 'countermeasure', 'success', 'astronot1', 'Karşı-tedbir tamamlandı', 'CEVIS bisiklet ergometresi · 30 dk'],
        ];

        foreach ($entries as [$minutesAgo, $category, $severity, $memberId, $title, $message]) {
            HealthEvent::create([
                'member_id' => $memberId,
                'category' => $category,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
            ])->forceFill(['created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo)])->save();
        }
    }
}
