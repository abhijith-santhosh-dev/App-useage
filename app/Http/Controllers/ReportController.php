<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ReportController extends Controller
{
    public function generateReport(Request $request)
    {
        
        $request->validate([
            'usage_date' => 'required|date',
        ]);

        $usageDate = $request->input('usage_date');

        
        $usageData = DB::table('user_app_usage_1')
            ->select(
                'app_name',
                'productivity_level',
                'start_time',
                'end_time',
                'duration'
            )
            ->where('usage_date', $usageDate)
            ->get();

        if ($usageData->isEmpty()) {
            return response()->json(['message' => 'No data available for the given date.'], 404);
        }

      
        $firstStartTime = strtotime($usageData->first()->start_time);

        $intervals = [];
        $intervalStart = $firstStartTime;

        while ($intervalStart < strtotime($usageDate . ' 23:59:59')) {
            $intervalEnd = $intervalStart + 300; // 5 minutes = 300 seconds
            $intervals[] = [
                'start' => date('H:i', $intervalStart),
                'end' => date('H:i', $intervalEnd),
                'apps' => [],
                'productive_percentage' => 0,
                'neutral_percentage' => 0,
                'unproductive_percentage' => 0,
            ];
            $intervalStart = $intervalEnd;
        }

        foreach ($intervals as &$interval) {
            $intervalStart = strtotime($usageDate . ' ' . $interval['start'] . ':00');
            $intervalEnd = strtotime($usageDate . ' ' . $interval['end'] . ':00');

            $productiveTime = 0;
            $neutralTime = 0;
            $unproductiveTime = 0;
            $appDurations = [];

            foreach ($usageData as $usage) {
                $usageStart = strtotime($usage->start_time);
                $usageEnd = strtotime($usage->end_time);

                $overlapStart = max($intervalStart, $usageStart);
                $overlapEnd = min($intervalEnd, $usageEnd);
                $overlapDuration = max(0, $overlapEnd - $overlapStart);

                if ($overlapDuration > 0) {
                    $appDurations[$usage->app_name] = ($appDurations[$usage->app_name] ?? 0) + $overlapDuration;

                    if ($usage->productivity_level == 2) {
                        $productiveTime += $overlapDuration;
                    } elseif ($usage->productivity_level == 0) {
                        $unproductiveTime += $overlapDuration;
                    } else {
                        $neutralTime += $overlapDuration;
                    }
                }
            }

            $totalTime = $productiveTime + $neutralTime + $unproductiveTime;

            if ($totalTime > 0) {
                $interval['productive_percentage'] = round(($productiveTime / $totalTime) * 100, 2);
                $interval['neutral_percentage'] = round(($neutralTime / $totalTime) * 100, 2);
                $interval['unproductive_percentage'] = round(($unproductiveTime / $totalTime) * 100, 2);
            }

            foreach ($appDurations as $appName => $duration) {
                $interval['apps'][] = "{$appName} ({$duration})";
            }
        }

        return response()->json([
            'date' => $usageDate,
            'report' => $intervals,
        ]);
    }

}
