<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;

class AgriculturalWeatherAdviceService
{
    public function generate(array $forecast): array
    {
        $advice = [];
        $thresholds = config('weather.thresholds', []);
        $daily = $forecast['daily'];
        $hourly = $forecast['hourly'];
        $current = $forecast['current'];
        $today = now($forecast['timezone'])->toDateString();
        $next24 = [];
        $currentTime = Carbon::parse($current['time'], $forecast['timezone']);
        $endTime = $currentTime->copy()->addHours(24);

        foreach ($hourly['time'] as $index => $time) {
            $hourTime = Carbon::parse($time, $forecast['timezone']);
            if ($hourTime->lt($currentTime) || $hourTime->gte($endTime)) {
                continue;
            }
            $day = substr($time, 0, 10);
            $next24[$day] = ($next24[$day] ?? 0.0) + (float) ($hourly['precipitation'][$index] ?? 0);
        }
        $rainTotal = array_sum($next24);
        $rainDays = array_keys($next24);
        if ($rainTotal >= ($thresholds['rain_mm'] ?? 5)) {
            $heavy = $rainTotal >= ($thresholds['heavy_rain_mm'] ?? 20);
            $advice[] = $this->item(
                'rain', $heavy ? 'Fortes pluies prévues' : 'Pluie prévue',
                'Une pluie notable est prévue. Vérifiez si une irrigation peut être reportée, sans la modifier automatiquement.',
                'Précipitations sur les prochaines 24 heures', number_format($rainTotal, 1, ',', ' ').' mm',
                'Prochaines 24 heures', $heavy ? 'high' : 'watch',
                'Vérifiez l’humidité du sol et les besoins de la culture avant de décider d’irriguer.', $rainDays ?: [$today]
            );
        }

        $heatDays = [];
        $heatMax = null;
        foreach ($daily['time'] as $index => $day) {
            $maximum = $daily['temperature_2m_max'][$index] ?? null;
            if (is_numeric($maximum) && $maximum >= ($thresholds['heat_c'] ?? 35)) {
                $heatDays[] = $day;
                $heatMax = max($heatMax ?? -INF, (float) $maximum);
            }
        }
        if ($heatDays) {
            $advice[] = $this->item('heat', 'Forte chaleur à surveiller', 'La chaleur peut augmenter le stress hydrique des cultures. Tenez compte du type de culture et de son stade.', 'Température maximale prévue', number_format($heatMax, 1, ',', ' ').' °C', $this->period($heatDays), 'watch', 'Surveillez l’humidité du sol et adaptez l’irrigation aux besoins observés.', $heatDays);
        }

        $windDays = [];
        $windMax = 0.0;
        $gustMax = 0.0;
        foreach ($daily['time'] as $index => $day) {
            $speed = (float) ($daily['wind_speed_10m_max'][$index] ?? 0);
            $gust = (float) ($daily['wind_gusts_10m_max'][$index] ?? 0);
            if ($speed >= ($thresholds['wind_kmh'] ?? 35) || $gust >= ($thresholds['gust_kmh'] ?? 50)) {
                $windDays[] = $day;
                $windMax = max($windMax, $speed);
                $gustMax = max($gustMax, $gust);
            }
        }
        if ($windDays) {
            $advice[] = $this->item('wind', 'Vent fort prévu', 'Le vent peut gêner les traitements et certaines opérations agricoles.', 'Vent maximal / rafales', number_format($windMax, 0, ',', ' ').' / '.number_format($gustMax, 0, ',', ' ').' km/h', $this->period($windDays), $gustMax >= 70 ? 'high' : 'watch', 'Vérifiez les conditions et les consignes du produit avant tout traitement phytosanitaire.', $windDays);
        }

        $wetDays = [];
        foreach ($daily['time'] as $index => $day) {
            $rain = (float) ($daily['precipitation_sum'][$index] ?? 0);
            $probability = (float) ($daily['precipitation_probability_max'][$index] ?? 0);
            $code = (int) ($daily['weather_code'][$index] ?? 0);
            if ($code >= 95 && $code <= 99) {
                $wetDays[] = $day;
                $advice[] = $this->item('storm', 'Orage possible', 'Un code météo associé à un orage est prévu. Cette prévision ne confirme pas un phénomène observé localement.', 'Code météo prévu', 'Orage (WMO '.$code.')', $day, 'high', 'Consultez les consignes locales et vérifiez les risques de ruissellement ou de perturbation des récoltes.', [$day]);
            } elseif ($rain >= ($thresholds['heavy_rain_mm'] ?? 20) && $probability >= 50) {
                $wetDays[] = $day;
            }
        }
        if ($wetDays) {
            $wetDays = array_values(array_unique($wetDays));
            $wetAmounts = [];
            foreach ($daily['time'] as $index => $day) {
                if (in_array($day, $wetDays, true)) {
                    $wetAmounts[] = (float) ($daily['precipitation_sum'][$index] ?? 0);
                }
            }
            $advice[] = $this->item('runoff', 'Pluie importante à anticiper', 'Des pluies importantes sont prévues et peuvent perturber les récoltes ou provoquer de la stagnation d’eau.', 'Cumul journalier maximal', number_format(max($wetAmounts ?: [0]), 1, ',', ' ').' mm', $this->period($wetDays), 'watch', 'Vérifiez le drainage des parcelles et les travaux de récolte prévus.', $wetDays);
        }

        $coldDays = [];
        $lowest = null;
        foreach ($daily['time'] as $index => $day) {
            $minimum = $daily['temperature_2m_min'][$index] ?? null;
            if (is_numeric($minimum) && $minimum <= ($thresholds['frost_c'] ?? 2)) {
                $coldDays[] = $day;
                $lowest = min($lowest ?? INF, (float) $minimum);
            }
        }
        if ($coldDays) {
            $advice[] = $this->item('frost', 'Risque de gel à vérifier', 'Des températures proches ou sous le seuil de gel sont prévues. La sensibilité dépend de la culture et de son stade.', 'Température minimale prévue', number_format($lowest, 1, ',', ' ').' °C', $this->period($coldDays), 'high', 'Vérifiez les mesures de protection adaptées à votre culture et aux conditions locales.', $coldDays);
        }

        $humid = (float) ($current['relative_humidity_2m'] ?? 0);
        if ($humid >= ($thresholds['humidity_percent'] ?? 80) && $rainTotal >= 1) {
            $advice[] = $this->item('humidity', 'Humidité élevée', 'Humidité élevée et temps humide peuvent favoriser certaines maladies fongiques ; la météo seule ne permet pas de diagnostiquer une maladie.', 'Humidité actuelle / pluie prévue', number_format($humid, 0, ',', ' ').' % / '.number_format($rainTotal, 1, ',', ' ').' mm', 'Aujourd’hui', 'watch', 'Surveillez les cultures et suivez les recommandations agronomiques locales.', $rainDays ?: [$today]);
        }

        $highEt0Days = [];
        $et0Max = null;
        foreach ($daily['time'] as $index => $day) {
            $et0 = $daily['et0_fao_evapotranspiration'][$index] ?? null;
            if (is_numeric($et0) && $et0 >= ($thresholds['et0_mm'] ?? 5)) {
                $highEt0Days[] = $day;
                $et0Max = max($et0Max ?? 0, (float) $et0);
            }
        }
        if ($highEt0Days) {
            $advice[] = $this->item('et0', 'Évapotranspiration de référence élevée', 'ET₀ élevée : surveillez les besoins en eau, sans assimiler cette valeur au besoin réel d’irrigation de la culture.', 'ET₀ maximale', number_format($et0Max, 1, ',', ' ').' mm/j', $this->period($highEt0Days), 'info', 'Croisez cette indication avec la culture, le stade végétatif et l’humidité du sol.', $highEt0Days);
        }

        $priority = ['high' => 0, 'watch' => 1, 'info' => 2];
        usort($advice, fn ($a, $b) => $priority[$a['priority']] <=> $priority[$b['priority']]);

        return $advice;
    }

    private function item(string $type, string $title, string $description, string $phenomenon, string $value, string $period, string $priority, string $action, array $days): array
    {
        return compact('type', 'title', 'description', 'phenomenon', 'value', 'period', 'priority', 'action', 'days');
    }

    private function period(array $days): string
    {
        return count($days) === 1 ? $days[0] : $days[0].' au '.$days[count($days) - 1];
    }
}
