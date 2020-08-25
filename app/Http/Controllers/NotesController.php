<?php

namespace App\Http\Controllers;

use App\Note;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class NotesController extends Controller
{
    public function list(Request $request, $id)
    {
        $dateStart = $request->query('date_start', '');
        $dateEnd = $request->query('date_end', '');
        $temp = Carbon::now()->toDateTimeString();
        $time = explode(' ', $temp);
        $timeStart = '00:00:00';
        $timeEnd = '23:59:59';
        if ($dateStart === '') {
            $dateStart = "{$time[0]} {$timeStart}";
        }
        if ($dateEnd === '') {
            $dateEnd = "{$time[0]} {$timeEnd}";
        }
        $notes = Note::where('user_id', $id)->where('updated_at', '>=', $dateStart)->where('updated_at', '<=', $dateEnd)->get();
        return response()->json([
            'status' => 'success',
            'data' => $notes,
            'time' => $time[0],
            'start' => $dateStart,
            'end' => $dateEnd,
            'temp' => $temp,
        ], Response::HTTP_OK);
    }

    public function create(Request $request, $id)
    {
        $ret = Note::create([
            'content' => $request->input('content'),
            'time_zone' => $request->input('time_zone'),
            'user_id' => $id,
        ]);
        if (! $ret) {
            return response()->json([
                'status' => 'error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'status' => 'success',
            'data' => $ret,
        ], Response::HTTP_CREATED);
    }

    public function result($id)
    {
        $temp = Carbon::now()->toDateTimeString();
        $time = explode(' ', $temp);
        $timeStart = '00:00:00';
        $timeEnd = '23:59:59';
        $dateStart = "{$time[0]} {$timeStart}";
        $dateEnd = "{$time[0]} {$timeEnd}";
        $notes = Note::where('user_id', $id)->where('updated_at', '>=', $dateStart)->where('updated_at', '<=', $dateEnd)->get();

        // returns declaration
        $result = [];
        $result['kkal'] = 0;
        $result['mineralConsumption_total'] = 0;
        $result['score'] = 0;
        $result['points'] = 0;
        $fetched = [
            'nutrition' => [],
            'workout' => [],
            'mineralConsumption' => [],
            'rest' => [],
            'mineralType' => [],
        ];
        foreach ($notes as $note) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ba893dfa-15fe-43dd-9cac-6f3a54d488a5',
            ])->post('https://api.kata.ai/projects/40944ee0-cf5e-4f67-94ad-adf9dc4612d3/nlus/baaskaraaa:melonProjects/predict', [
                'sentence' => $note->content,
            ]);

            $json = $response->json();
            $output = $json['result'][0]['output'];
            try {
                $detections = $output['activitiesDet'];
                $isDrink = false;
                $drinkTotal = 0;
                $otherDrinkTotal = 0;
                foreach ($detections as $detection) {
                    if (array_key_exists('resolved', $detection)) {
                        $detType = $detection['resolved']['dictKey'];
                        if ($detType === 'nutrition') {
                            $result['kkal'] += Note::MAJOR_DATA_NUTRITION[$detection['value']];
                        } elseif ($detType === 'mineralConsumption') {
                            $isDrink = True;
                            $drinkTotal += 1;
                        } elseif ($detType === 'mineralType') {
                            $otherDrinkTotal += 1;
                        }
                        array_push($fetched[ $detType ], $detection['value']);
                    }
                }
                if ($otherDrinkTotal > 0) {
                    $result['mineralConsumption_total'] = $otherDrinkTotal;
                } elseif ($otherDrinkTotal == 0 && $isDrink == true) {
                    $result['mineralConsumption_total'] = $drinkTotal;
                }
            } catch (\Throwable $th) {
                continue;
            }
        }
        $result['det'] = $fetched;

        if($result['kkal'] > 2500) {
            $result['score'] += 4;
        } elseif ($result['kkal'] < 2000) {
            $result['score'] += 3;
        } else {
            $result['score'] += 5;
        }

        if (count($result['det']['workout']) >= 1) {
            $result['score'] += 4;
        }

        if (count($result['det']['rest']) >= 1) {
            $result['score'] += 4;
        }

        if ($result['mineralConsumption_total'] >= 8) {
            $result['score'] += 2;
        } else {
            $result['score'] += 1;
        }
        $result['points'] = $result['score'] * 10;

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
}
