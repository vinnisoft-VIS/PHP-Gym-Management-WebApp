<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Goal;
use App\Weighin;
use Auth;
use Validator;
use Carbon\Carbon;

class GoalController extends Controller
{
    public function getGoal()
    {
        $user = Auth::user();
        $existWeighIn = Weighin::where('user_id', $user->id)->first();
        if (!$existWeighIn) {
            return response()->json([
                'success' => false,
                'message' => 'Before creating goal, first create weigh in.'
            ]);
        }

        $goals = Goal::where('user_id', $user->id)
                    ->where('per', '<', 100)
                    ->orWhere(function($query) use ($user) {
                        $query->where('user_id', $user->id)
                        ->whereNull('per');
                    })
                    ->orderBy('id', 'asc')->get();

        $currentWeighIn = Weighin::where('user_id', $user->id)->orderBy('date', 'desc')->first();
        $currentWeight = $currentWeighIn->weight;
        foreach ($goals as $key => $goal) {
            $firstWeighIn = Weighin::where('user_id', $user->id)
                                    ->where('created_at', '<', $goal->created_at)
                                    ->orderBy('created_at', 'desc')->first();

            if ($firstWeighIn) {
                if ($goal->goal_type == 0) { // For Weight Calculation
                    if ($goal->gain_loss == 0) { // For Weight Lose Calculation
                        $nextWeighIn = Weighin::where('user_id', $user->id)
                                            ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                            ->where('weight', '<', $firstWeighIn->weight)
                                            ->orderBy('created_at', 'asc')->first();
                        $currentWeighIn = Weighin::where('user_id', $user->id)
                                                ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                                ->where('weight', '<', $firstWeighIn->weight)
                                                ->orderBy('created_at', 'desc')->first();

                        if ($currentWeighIn && $currentWeighIn->weight < $firstWeighIn->weight) {
                            $nextper = 0;
                            if ($nextWeighIn && $currentWeighIn->weight != $nextWeighIn->weight && $currentWeighIn->weight > $nextWeighIn->weight) {
                                // $nextDiff = $firstWeighIn->weight - $nextWeighIn->weight;
                                // $nextper  = ($nextDiff / $goal->fat_percent) * 100;
                                $diff = $firstWeighIn->weight - $nextWeighIn->weight;
                            } else {
                                $diff = $firstWeighIn->weight - $currentWeighIn->weight;

                            }

                            $per  = ($diff / $goal->fat_percent) * 100;
                            // $per = $per + $nextper;
                            $per = abs($per);
                            if ($per > 100) {
                                $per = 100;
                            }
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        } else {
                            $per = 0;
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        }
                    } else { // For Weight Gain Calculation
                        $nextWeighIn = Weighin::where('user_id', $user->id)
                                            ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                            ->where('weight', '>', $firstWeighIn->weight)
                                            ->orderBy('created_at', 'asc')->first();
                        $currentWeighIn = Weighin::where('user_id', $user->id)
                                                ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                                ->where('weight', '>', $firstWeighIn->weight)
                                                ->orderBy('created_at', 'desc')->first();

                        if ($currentWeighIn && $currentWeighIn->weight > $firstWeighIn->weight) {
                            $nextper = 0;
                            if ($nextWeighIn && $currentWeighIn->weight != $nextWeighIn->weight && $currentWeighIn->weight < $nextWeighIn->weight) {
                                // $nextDiff = $nextWeighIn->weight - $firstWeighIn->weight;
                                // $nextper  = ($nextDiff / $goal->fat_percent) * 100;
                                $diff = $nextWeighIn->weight - $firstWeighIn->weight;
                            } else {
                                $diff = $currentWeighIn->weight - $firstWeighIn->weight;
                            }
                            $per = ($diff / $goal->fat_percent) * 100;
                            // $per = $per + $nextper;
                            $per = abs($per);
                            if ($per > 100) {
                                $per = 100;
                            }
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        } else {
                            $per = 0;
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        }
                    }
                } else { // For BodyFat Calculation

                    if ($goal->gain_loss == 0) { // For BodyFat Lose Calculation
                        $nextWeighIn = Weighin::where('user_id', $user->id)
                                            ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                            ->where('body_fat_percent', '<', $firstWeighIn->body_fat_percent)
                                            ->orderBy('created_at', 'asc')->first();
                        $currentWeighIn = Weighin::where('user_id', $user->id)
                                                ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                                ->where('body_fat_percent', '<', $firstWeighIn->body_fat_percent)
                                                ->orderBy('body_fat_percent', 'desc')->first();

                        if ($currentWeighIn && $currentWeighIn->body_fat_percent < $firstWeighIn->body_fat_percent) {
                            $nextper = 0;
                            if ($nextWeighIn && $currentWeighIn->body_fat_percent != $nextWeighIn->body_fat_percent && $currentWeighIn->body_fat_percent > $nextWeighIn->body_fat_percent) {
                                $nextDiff = $firstWeighIn->body_fat_percent - $nextWeighIn->body_fat_percent;
                                $nextper  = ($nextDiff / $goal->fat_percent) * 100;
                            }

                            $diff = $firstWeighIn->body_fat_percent - $currentWeighIn->body_fat_percent;
                            $per = ($diff / $goal->fat_percent) * 100;
                            $per = $per + $nextper;
                            $per = abs($per);
                            if ($per > 100) {
                                $per = 100;
                            }
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        } else {
                            $per = 0;
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        }
                    } else { // For BodyFat Gain Calculation
                        $nextWeighIn = Weighin::where('user_id', $user->id)
                                            ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                            ->where('body_fat_percent', '>', $firstWeighIn->body_fat_percent)
                                            ->orderBy('created_at', 'asc')->first();
                        $currentWeighIn = Weighin::where('user_id', $user->id)
                                                ->whereDate('created_at', '>', $firstWeighIn->created_at)
                                                ->where('body_fat_percent', '>', $firstWeighIn->body_fat_percent)
                                                ->orderBy('body_fat_percent', 'desc')->first();

                        if ($currentWeighIn && $currentWeighIn->body_fat_percent > $firstWeighIn->body_fat_percent) {
                            $nextper = 0;
                            if ($nextWeighIn && $currentWeighIn->body_fat_percent != $nextWeighIn->body_fat_percent && $currentWeighIn->body_fat_percent < $nextWeighIn->body_fat_percent) {
                                $nextDiff = $nextWeighIn->body_fat_percent - $firstWeighIn->body_fat_percent;
                                $nextper  = ($nextDiff / $goal->fat_percent) * 100;
                            }
                            $diff = $currentWeighIn->body_fat_percent - $firstWeighIn->body_fat_percent;
                            $per = ($diff / $goal->fat_percent) * 100;
                            $per = $per - $nextper;
                            $per = abs($per);
                            if ($per > 100) {
                                $per = 100;
                            }
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        } else {
                            $per = 0;
                            $goals[$key]['per'] = (float)number_format($per, 2);
                        }
                    }
                }
            }
            $goal->update([
                'per' => $per
            ]);
        }

        $goalArray = Goal::where('user_id', $user->id)->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Goals list.',
            'data'    => $goalArray
        ]);
    }

    public function createGoal(Request $request)
    {
        $user = Auth::user();

        $todayDate = Carbon::now();
        $goal = Goal::create([
            'user_id'         => $user->id,
            'goal_type'       => $request->goal_type, // 0 for weight and 1 for Body fat
            'fat_percent'     => $request->percent,
            'reward_goal'     => $request->reward,
            'punishment_goal' => $request->punishment,
            'gain_loss'       => $request->gain_loss // 0 for loss and 1 for gain
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Goal created successfully.',
            'data'    => $goal
        ]);
    }

    public function deleteGaol($goalId)
    {
        $goal = Goal::where('id', $goalId)->first();
        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found!.',
            ]);
        }
        $goal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Goal deleted successfully.',
        ]);
    }

    public function checkTodayWeighIn()
    {
        $todayWeighIn = Weighin::where('user_id', Auth::id())->whereDate('created_at', Carbon::now())->first();

        if (!$todayWeighIn) {
            return response()->json([
                'success' => false,
                'message' => 'Before creating goal, first create today weigh In.'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Weigh In found.'
        ]);
    }
}
