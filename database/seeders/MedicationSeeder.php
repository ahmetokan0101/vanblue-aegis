<?php

namespace Database\Seeders;

use App\Models\Medication;
use Illuminate\Database\Seeder;

/**
 * Seeds the Orion deep-space medical kit (quantities sized for a 4-person, 920-day mission).
 */
class MedicationSeeder extends Seeder
{
    public function run(): void
    {
        $kit = [
            ['İbuprofen', 'Ağrı / inflamasyon', 'tablet', '400 mg', 'tablet', 240, 300, 60],
            ['Parasetamol', 'Ağrı / ateş', 'tablet', '500 mg', 'tablet', 280, 300, 60],
            ['Aspirin', 'Kardiyak', 'çiğneme tableti', '300 mg', 'tablet', 18, 20, 6],
            ['Prometazin', 'Bulantı / uzay tutması', 'tablet', '25 mg', 'tablet', 34, 60, 12],
            ['Psödoefedrin', 'Dekonjestan', 'tablet', '60 mg', 'tablet', 70, 120, 24],
            ['Loratadin', 'Alerji', 'tablet', '10 mg', 'tablet', 80, 90, 20],
            ['Melatonin', 'Uyku', 'tablet', '3 mg', 'tablet', 190, 400, 80],
            ['Zolpidem', 'Uyku', 'tablet', '10 mg', 'tablet', 22, 100, 25],
            ['Siprofloksasin', 'Antibiyotik', 'tablet', '500 mg', 'tablet', 40, 56, 14],
            ['Valasiklovir', 'Antiviral', 'tablet', '1 g', 'tablet', 42, 42, 12],
            ['Enoksaparin', 'Antikoagülan', 'enjektör', '40 mg', 'enjektör', 20, 20, 6],
            ['Potasyum sitrat', 'Böbrek taşı önleme', 'tablet', '1080 mg', 'tablet', 150, 180, 40],
            ['Suni gözyaşı', 'Göz', 'damla', '10 ml', 'şişe', 9, 24, 6],
            ['Mupirosin krem', 'Cilt / yara', 'krem', '%2', 'tüp', 5, 8, 2],
            ['İdrar strip testi', 'Tanı', 'strip', null, 'adet', 46, 100, 20],
            ['Steril pansuman seti', 'Yara bakımı', 'set', null, 'set', 31, 40, 10],
        ];

        foreach ($kit as [$name, $category, $form, $strength, $unit, $quantity, $initial, $threshold]) {
            Medication::updateOrCreate(['name' => $name], [
                'category' => $category,
                'form' => $form,
                'strength' => $strength,
                'unit' => $unit,
                'quantity' => $quantity,
                'initial_quantity' => $initial,
                'min_threshold' => $threshold,
                'expires_on' => now()->addMonths(in_array($name, ['Prometazin', 'Suni gözyaşı'], true) ? 14 : 30)->toDateString(),
                'storage_note' => 'Kozmik radyasyon raf ömrünü kısaltabilir',
            ]);
        }
    }
}
