<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Auth,Validator;
use DB;
use App\Goal;
use App\Dailypicture;
use App\Weighin;
use App\Group;
use App\GroupMember;
use Carbon\Carbon;
use App\Notification;
use DateTime;
use App\NotificationUser;
use App\NotificationGroup;
use App\RunningPlan;
use App\RunningPlanClient;
use App\WeeklyChallenges;
use App\WeeklyChallengeClient;
use App\WorkoutType;
use App\WorkoutVideo;
use App\WorkoutTypeVideo;
use App\WorkoutCategory;
use App\WorkoutClient;
use App\MealPlan;
use App\MealPlanClient;
use App\Recipe;
use App\RecipeImage;
use App\RecipeClient;
use App\RecipesCategory;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request, $status)
    {
        $owner = Auth::user();
        if ($owner->team_parent_id) {
            $owner = User::where('id', $owner->team_parent_id)->first();
        }
        // dd($request->all());
        if ($status == 'active') {
            $userStatus = 1;
        } else {
            $userStatus = 0;
        }
        $workoutCategories = WorkoutCategory::where('user_id', $owner->id)->get();

        if (isset($request->sort) && ($request->sort=='first_name' || $request->sort=='phone' || $request->sort=='email')) {
            if ($request->direction == 'asc') {
                $records = User::where('usertype',0)
                                ->where('parent_id',$owner->id)
                                ->where('status', $userStatus)
                                ->orderby($request->sort,'asc')
                                ->paginate(50);

            } else {
                $records = User::where('usertype',0)
                                ->where('parent_id',$owner->id)
                                ->where('status', $userStatus)
                                ->orderby($request->sort,'desc')
                                ->paginate(50);
            }

        } elseif (isset($request->sort) && $request->sort=='last_picture') {
            $records = User::selectRaw('users.*, MAX(dailypictures.created_at) as latest_post_timestamp')
                            ->leftJoin('dailypictures', 'dailypictures.user_id', '=', 'users.id')
                            ->where('users.parent_id',$owner->id)
                            ->where('users.usertype',0)
                            ->where('users.status', $userStatus)
                            ->groupBy('users.id')
                            ->orderBy('latest_post_timestamp', $request->direction)
                            ->paginate(50);

        } elseif (isset($request->sort) && $request->sort=='last_weight') {
            // echo 'dsfd1';die;
            $records =  User::selectRaw('users.*, MAX(weighins.createdate) as latest_post_timestamp')
                            ->leftJoin('weighins', 'weighins.user_id', '=', 'users.id')
                            ->where('users.parent_id',$owner->id)
                            ->where('users.usertype',0)
                            ->where('users.status', $userStatus)
                            ->groupBy('users.id')
                            ->orderBy('latest_post_timestamp', $request->direction)
                            ->paginate(50);

        } elseif (isset($request->sort) && ($request->sort=='total_weight' || $request->sort=='total_bf')) {
            if ($request->sort=='total_weight') {
                $ascdesc = 'weightdiff '.$request->direction;
            } else {
                $ascdesc = 'bfdiff '.$request->direction;
            }

            $data = DB::select('select (t6.weight-t5.weight) as weightdiff,(t6.body_fat_percent-t5.body_fat_percent) as bfdiff, u.id, u.first_name,u.last_name,u.name,u.email,u.phone from (select weight,body_fat_percent,createdate,t1.user_id from weighins t1 join(select max(createdate) as maxdate , user_id from weighins group by user_id) t2 on t1.createdate=t2.maxdate and t1.user_id=t2.user_id) t5  join (select weight,body_fat_percent,createdate,t3.user_id from weighins t3 join(select min(createdate) as  mindate , user_id from weighins group by user_id) t4 on t3.createdate=t4.mindate and t3.user_id=t4.user_id) t6 on t5.user_id=t6.user_id  right join users u on t5.user_id=u.id  where u.usertype=0 and u.deleted_at IS NULL and  u.parent_id='.$owner->id.' order by '.$ascdesc);
            $result = '';
            // dd($data);
            foreach ($data as $item) {
                // echo gettype($item->weightdiff);
                $dailPictures   = Dailypicture::where('user_id',$item->id)->orderby('id','DESC')->first();
                $lastPictureAdd = $dailPictures!= null ? date("m/d/Y", strtotime($dailPictures->created_at)):'NA';
                $currentWeighin = Weighin::where('user_id',$item->id)->orderby('createdate','desc')->first();
                $lastWeightAdd  = $currentWeighin!= null ? date("m/d/Y", strtotime($currentWeighin->date)):'NA';

                $email = $item->email != $item->phone ? $item->email:"NA";

                if (gettype($item->weightdiff) == 'NULL') {
                    $item->weightdiff = 'NA';
                    $weightIcon = '';
                } else {
                    if ($item->weightdiff > 0) {
                        $item->weightdiff = number_format($item->weightdiff,2);
                        $weightIcon = '<i class="fa fa-long-arrow-down" style="font-size:14px;color:green;font-weight: bold;"></i>';
                    } elseif ($item->weightdiff < 0) {
                        $item->weightdiff = number_format($item->weightdiff,2);
                        $weightIcon = '<i class="fa fa-long-arrow-up" style="font-size:14px;color:red;font-weight: bold;"></i>';
                    } else {
                        $item->weightdiff = number_format($item->weightdiff,2);
                        $weightIcon = '';
                    }
                }

                if (gettype($item->bfdiff) == 'NULL') {
                    $item->bfdiff = 'NA';
                    $bfIcon = '';
                } else {
                    if ($item->bfdiff > 0) {
                        $item->bfdiff = number_format($item->bfdiff,2);
                        $bfIcon = '<i class="fa fa-long-arrow-down" style="font-size:14px;color:green;font-weight: bold;"></i>';
                    } elseif ($item->bfdiff < 0) {
                        $item->bfdiff = number_format($item->bfdiff,2);
                        $bfIcon = '<i class="fa fa-long-arrow-up" style="font-size:14px;color:red;font-weight: bold;"></i>';
                    } else {
                        $item->bfdiff = number_format($item->bfdiff,2);
                        $bfIcon = '';
                    }
                }

                $url = route("user.profile", ['id' => $item->id, 'status' => $status]);

                $rec = '';
                if (!Auth::user()->team_parent_id) {
                    $rec = '<td>'.$item->phone.'</td>
                            <td>'.$email.'</td>';
                }
                $result .= '<tr>
                              <td>
                                  <div class="custom-control custom-checkbox" data-id="'.$item->id.'">
                                      <input type="checkbox" onclick="enabledisable(); class="custom-control-input" data-id="'.$item->id.'" id="customCheck1" >
                                      <label class="custom-control-label" for="customCheck1">'.$item->first_name.' '.$item->last_name.'</label>
                                  </div>
                              </td>
                              '.$rec.'
                              <td>'.$item->weightdiff.' '.$weightIcon.'</td>
                              <td>'.$item->bfdiff.' '.$bfIcon.'</td>
                              <td>'.$lastWeightAdd.'</td>
                              <td>'.$lastPictureAdd.'</td>
                              <td>
                                  <label class="switch-user">
                                      <input type="checkbox" '.($item->status ? "checked" : "").' class="toggle" onclick="toggle(this)" name="is_approved" value="" data-url="'.route('user.status', ['id' => $item->id]).'">
                                      <span class="slider-user round"></span>
                                  </label>
                              </td>
                              <td>
                                  <a href="'.$url.'">
                                      <i class="fa fa-eye" aria-hidden="true" style="font-size: 20px;color: #337ab7;"></i>
                                  </a>
                              </td>
                          </tr>';
            }

            // die;
            return $result;
        } elseif (isset($request->search) && strlen($request->search) > 0) {
            $search = $request->search;
            $records = User::where('usertype',0)
                            ->where(function($query) use ($search) {
                            $query->Where('first_name', 'LIKE', "%{$search}%")
                                  ->orWhere('last_name', 'LIKE', "%{$search}%");
                            })
                            ->where('parent_id',$owner->id)
                            ->where('status', $userStatus)
                            ->paginate(50);
        } else {
            $records = User::where('usertype',0)
                            ->where('parent_id',$owner->id)
                            ->where('status', $userStatus)
                            ->orderby('id','DESC')
                            ->paginate(50);
                            // dd($records);
        }

        $data = '';
        $temp = [];
        // dd($records);
        $lastWeightAddArr  = [];
        $lastPictureAddArr = [];
        foreach ($records as $key => $user) {
            // echo 'sdfdsf';die;
            if (in_array($user->id , $temp)) {
                continue;
            }

            array_push($temp,$user->id);

            $weightIcon = '';
            $bfIcon = '';
            $oldWeighin     = Weighin::where('user_id',$user->id)->orderby('createdate','asc')->first();
            $currentWeighin = Weighin::where('user_id',$user->id)->orderby('createdate','desc')->first();
            $dailPictures   = Dailypicture::where('user_id',$user->id)->orderby('id','DESC')->first();

            if ($oldWeighin && $currentWeighin) {
                $user->loss_weight = $lossWeight = $oldWeighin->weight-$currentWeighin->weight;
                $user->loss_bf = $lossBodyFat = $oldWeighin->body_fat_percent-$currentWeighin->body_fat_percent;

                if($user->loss_weight > 0){
                    $weightIcon = '<i class="fa fa-long-arrow-down" style="font-size:14px;color:green;font-weight: bold;"></i>';
                } elseif($user->loss_weight < 0) {
                    $user->loss_weight = number_format($user->loss_weight,2);
                    $weightIcon = '<i class="fa fa-long-arrow-up" style="font-size:14px;color:red;font-weight: bold;"></i>';
                } else {
                    $weightIcon = '';
                }
                if ($user->loss_bf > 0) {
                    $user->loss_bf = number_format($user->loss_bf,2);
                    $bfIcon = '<i class="fa fa-long-arrow-down" style="font-size:14px;color:green;font-weight: bold;"></i>';
                } elseif ($user->loss_bf < 0) {
                    $user->loss_bf = number_format($user->loss_bf,2);
                    $bfIcon = '<i class="fa fa-long-arrow-up" style="font-size:14px;color:red;font-weight: bold;"></i>';
                } else {
                    $bfIcon = '';
                    $user->loss_bf = number_format($user->loss_bf,2);
                }

                $user->last_weight_add = $lastWeightAdd = date("m/d/Y", strtotime($currentWeighin->date));
            } else {
                $user->loss_weight = "NA";
                $user->loss_bf = "NA";
                $user->last_weight_add = "NA";
            }
            if ($dailPictures)
                $user->last_picture_add = $lastPictureAdd = date("m/d/Y", strtotime($dailPictures->created_at));
            else
                $user->last_picture_add = "NA";

                // dd($user);
                $url = route("user.profile", ['id' => $user->id, 'status' => $status]);
                $losbfper = $user->loss_bf != 'NA'? $user->loss_bf.' %': $user->loss_bf;
                $email = $user->email != $user->phone ? $user->email:"NA";

                $lswt = is_string($user->loss_weight)?$user->loss_weight:number_format($user->loss_weight,2);
                $rec = '';
                if (!Auth::user()->team_parent_id) {
                    $rec = '<td>'.$user->phone.'</td>
                            <td>'.$email.'</td>';
                }

                if ($request->has('date_search_type') && $request->has('date_from') || $request->has('date_to')) {
                    $type = $request->date_search_type;
                    if ($request->date_search_type == "last_weighin") {
                        $searchType = $user->last_weight_add != 'NA' ? Carbon::parse($user->last_weight_add) : '';
                    }
                    if ($request->date_search_type == "last_picture") {
                        $searchType = $user->last_picture_add != 'NA' ? Carbon::parse($user->last_picture_add) : '';
                    }
                    if ($searchType && Carbon::parse($request->date_from) <= $searchType && Carbon::parse($request->date_to) >= $searchType) {
                        $data .= '<tr>
                                      <td>
                                          <div class="custom-control custom-checkbox" data-id="'.$user->id.'">
                                              <input type="checkbox" onclick="enabledisable();" class="custom-control-input" data-id="'.$user->id.'" id="customCheck'.$user->id.'" >
                                          </div>
                                      </td>
                                      <td>
                                          <label class="custom-control-label" for="customCheck'.$user->id.'">'.$user->first_name.' '.$user->last_name.'</label>
                                      </td>
                                      '.$rec.'
                                      <td>'.$lswt.' '.$weightIcon.'</td>
                                      <td>'.$losbfper.' '.$bfIcon.'</td>
                                      <td>'.$user->last_weight_add.'</td>
                                      <td>'.$user->last_picture_add.'</td>
                                      <td>
                                          <label class="switch-user">
                                              <input type="checkbox" '.($user->status ? "checked" : "").' class="toggle" onclick="toggle(this)" name="is_approved" value="" data-url="'.route('user.status', ['id' => $user->id]).'">
                                              <span class="slider-user round"></span>
                                          </label>
                                      </td>
                                      <td>
                                          <a href="'.$url.'">
                                              <i class="fa fa-eye" aria-hidden="true" style="font-size: 20px;color: #337ab7;"></i>
                                          </a>
                                      </td>
                                  </tr>';
                    }
                } else {
                    $data .= '<tr>
                                  <td>
                                      <div class="custom-control custom-checkbox" data-id="'.$user->id.'">
                                          <input type="checkbox" onclick="enabledisable();" class="custom-control-input" data-id="'.$user->id.'" id="customCheck'.$user->id.'" >
                                      </div>
                                  </td>
                                  <td>
                                      <label class="custom-control-label" for="customCheck'.$user->id.'">'.$user->first_name.' '.$user->last_name.'</label>
                                  </td>
                                  '.$rec.'
                                  <td>'.$lswt.' '.$weightIcon.'</td>
                                  <td>'.$losbfper.' '.$bfIcon.'</td>
                                  <td>'.$user->last_weight_add.'</td>
                                  <td>'.$user->last_picture_add.'</td>
                                  <td>
                                      <label class="switch-user">
                                          <input type="checkbox" '.($user->status ? "checked" : "").' class="toggle" onclick="toggle(this)" name="is_approved" value="" data-url="'.route('user.status', ['id' => $user->id]).'">
                                          <span class="slider-user round"></span>
                                      </label>
                                  </td>
                                  <td>
                                      <a href="'.$url.'">
                                          <i class="fa fa-eye" aria-hidden="true" style="font-size: 20px;color: #337ab7;"></i>
                                      </a>
                                  </td>
                              </tr>';
                }

                $lastWeightAddArr[$key]  = $user->last_weight_add;
                $lastPictureAddArr[$key] = $user->last_picture_add;
        }

        $lastWeightAddArr  = array_unique($lastWeightAddArr);
        $lastPictureAddArr = array_unique($lastPictureAddArr);

        if ($request->ajax()) {
            return $data;
        }
        return view('userdashbaord.index', compact('workoutCategories', 'lastWeightAddArr', 'lastPictureAddArr', 'status'));
    }

    public  function getUserAddForm()
    {
         return view('userdashbaord.create');
    }

    public function removeUser(Request $request)
    {
        $data = $request->all();
        foreach ($data['ids'] as $id) {
            $enduser = User::find($id);

            if ($enduser) {
                $enduser->parent_id=0;
                $enduser->save();

                $dailypictures = Dailypicture::where('user_id',$id)->delete();

                $goal = Goal::where('user_id',$id)->delete();

                $groupmembers = GroupMember::where('user_id',$id)->delete();

                $groups = Group::where('user_id',$id)->delete();

                $notificationusers = NotificationUser::where('user_id',$id)->delete();

                $weighins = Weighin::where('user_id',$id)->delete();

                $enduser->delete();
            }
        }

        try {
            DB::commit();
            return redirect('user/home/active')->with('status','User Removed Successfully.');
        } catch(Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function viewGoal(Request $request,$id)
    {
        // dd($id);
        $user = Auth::user();

        $records = Goal::where('user_id',$id)->orderby('id','DESC')->paginate();
        $PWL = 0;
        foreach ($records as $val) {
            $weighIn = Weighin::where('user_id',$id)->orderby('id','DESC')->first();
            if ($weighIn) {
                $CW = $weighIn->weight;
                $SW = $val->fat_percent;

                if ($val->gain_loss)
                    $PWL = round((($SW-$CW)/$SW)*100);
                else
                    $PWL = round((($CW-$SW)/$CW)*100);
                    // $PWL = (($SW-$CW)/$SW)*100;
            } else {
                $PWL = 0;
            }

            $val->progress = $PWL;
        }
        // dd($records);
        if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1) {
            $startNum = (($_REQUEST['page'] -1) * $perPage) + 1;
        } else {
            $startNum = 1;
        }

        return view('userdashbaord.goal', compact('records','startNum'));
    }

    public function dailypictures(Request $request,$id)
    {
        $user = Auth::user();

        $records = Dailypicture::where('user_id',$id)->orderby('id','DESC')->paginate();

        // dd($records);
        if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1) {
            $startNum = (($_REQUEST['page'] -1) * $perPage) + 1;
        } else {
            $startNum = 1;
        }

        return view('userdashbaord.dailypictures', compact('records','startNum'));
    }

    public function dailyPicturesFilter(Request $request)
    {
        $dailypictures = Dailypicture::where('user_id', $request->user_id);

        if ($request->has('date_from') && $request->has('date_to')) {
            $dailypictures  = $dailypictures->whereBetween('created_at', [Carbon::parse($request->date_from), Carbon::parse($request->date_to)]);
        }

        $dailypictures = $dailypictures->orderby('id','DESC')->get();

        $data = "";
        if ($dailypictures->count()) {
            foreach($dailypictures as $picture){
                $data .='<tr>
                            <td class="text-center"><input type="checkbox" class="custom-control-input picture-checkboxes" onclick="allPictures()" id="customCheck'.$picture->id.'" data-id="'.$picture->id.'"></td>
                            <td class="text-center">
                                <a href="#">'.date("m/d/Y", strtotime($picture->created_at)).'</a>
                            </td>
                            <td class="text-center">
                                <img class="img-fluid front-image" style="width: 100px;" src="'.$picture->front_picture.'">
                            </td>
                            <td class="text-center">
                                <img class="img-fluid side-image" style="width: 100px;" src="'.$picture->side_picture.'">
                            </td>
                            <td class="text-center">
                                <img class="img-fluid back-image" style="width: 100px;" src="'.$picture->back_picture.'">
                            </td>
                        </tr>';
            }
        }

        return $data;
    }

    public function dailyPicturesRemove(Request $request)
    {
        Dailypicture::whereIn('id', $request->picture_ids)->delete();

        return response()->json('success');
    }

    public function getWeighin(Request $request,$id)
    {
        $user = Auth::user();
        $records = $weighin = Weighin::where('user_id',$id)->orderby('createdate','desc')->paginate();

        //dd($weighin);
        //dd($weighin->count());
        if ($weighin->count()) {
            foreach ($weighin as $weigh) {
                $oldweighin = Weighin::where('user_id',$id)->whereDate('createdate','<',$weigh->createdate)->first();
                if ($oldweighin) {
                    $oldWeight = $oldweighin->weight;
                } else {
                    $oldWeight = 0;
                }

                $weigh->old_weight = $oldWeight;
            }
        }
        // dd($records);
        if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1) {
            $startNum = (($_REQUEST['page'] -1) * $perPage) + 1;
        } else {
            $startNum = 1;
        }
        // dd($records);

        return view('userdashbaord.weighins', compact('records','startNum'));
    }

    public function getProfile(Request $request, $status, $id)
    {
        $owner = Auth::user();
        if ($owner->team_parent_id) {
            $owner = User::where('id', $owner->team_parent_id)->first();
        }
        $user  = User::find($id);
        // $oldWeighinUser = Weighin::where('user_id', $user->id)->orderby('created_at','asc')->first();
        // $currentWeighinUser = Weighin::where('user_id', $user->id)->orderby('created_at','desc')->first();
        $oldWeighinUser = Weighin::where('user_id', $user->id)->orderby('createdate','asc')->first();
        $currentWeighinUser = Weighin::where('user_id', $user->id)->orderby('createdate','desc')->first();

        if ($oldWeighinUser && $currentWeighinUser) {
            $user->weight_loss = $totalWeightLoss = $oldWeighinUser->weight - $currentWeighinUser->weight;
        } elseif ($currentWeighinUser) {
            $user->weight_loss = 0;
        }
        // dd()

        $workoutCategories = WorkoutCategory::where('user_id', $owner->id)->get();
        $data = '';
        $dailypictures = '';
        $weighins = '';
        $dailyPicturesDates = Dailypicture::where('user_id', $id)->pluck('created_at')->toArray();

        if ($request->ajax()) {
            if ($request->page_type == 'weighin') {
                $weighins = $weighin = Weighin::where('user_id',$id)->orderby('created_at','desc')->paginate(5);
                // dd($weighins);

                if ($weighin->count()) {
                    foreach ($weighin as $weigh) {
                        $oldweighin = Weighin::where('user_id',$id)->whereDate('createdate','<',$weigh->createdate)->first();
                        if ($oldweighin) {
                            $oldWeight = $oldweighin->weight;
                        } else {
                            $oldWeight = 0;
                        }

                        $weigh->old_weight = $oldWeight;
                        $img2 = env('APP_URL') . '/img/edit.png';
                        $data .= '<tr>
                                      <td class="text-center">
                                          <a href="#">'.date("m/d/Y", strtotime($weigh->date)).'</a>
                                      </td>
                                      <td class="text-center">
                                          <a href="#">'.$weigh->weight.'</a>
                                      </td>
                                      <td class="text-center">
                                          <a href="#">'.$weigh->old_weight.'</a>
                                      </td>
                                      <td class="text-center">
                                          <a href="#">'.$weigh->body_fat_percent.' %</a>
                                      </td>
                                      <td class="text-center">
                                          <a class="" data-toggle="modal" onclick="editgroup(this);" data-target="#editGroupModal" data-id="'.$weigh->id.'" data-fat="'.$weigh->body_fat_percent.'" data-weight="'.$weigh->weight.'" style="cursor: pointer;">
                                              <img src="'.$img2.'" style="padding: 0px 20px;" />
                                          </a>
                                      </td>
                                  </tr>';
                    }

                    return $data;
                }
            }

            if ($request->page_type == 'pictures') {
                // echo $request->page_type ;die;
                // dd($id);
                $dailypictures = Dailypicture::where('user_id', $id)->orderby('id','DESC')->paginate(5);
                // ->toSql();
                // dd($dailypictures);
                if ($dailypictures->count()) {
                    foreach($dailypictures as $picture){
                        $data .='<tr>
                                    <td class="text-center"><input type="checkbox" class="custom-control-input picture-checkboxes" onclick="allPictures()" id="customCheck'.$picture->id.'" data-id="'.$picture->id.'"></td>
                                    <td class="text-center">
                                        <a href="#">'.date("m/d/Y", strtotime($picture->created_at)).'</a>
                                    </td>
                                    <td class="text-center">
                                        <img class="img-fluid front-image" style="width: 100px;" src="'.$picture->front_picture.'">
                                    </td>
                                    <td class="text-center">
                                        <img class="img-fluid side-image" style="width: 100px;" src="'.$picture->side_picture.'">
                                    </td>
                                    <td class="text-center">
                                        <img class="img-fluid back-image" style="width: 100px;" src="'.$picture->back_picture.'">
                                    </td>
                                </tr>';
                    }
                }
            }

            $authId = Auth::id();
            if (Auth::user()->team_parent_id) {
                $authId = Auth::user()->team_parent_id;
            }
            if ($request->page_type == 'running_plan') {
                  $runningPlans = RunningPlan::where('user_id', $authId)->orderBy('total_weeks', 'asc')->pluck('total_weeks')->toArray();
                  $runningPlans = array_unique($runningPlans);

                  $data = '';
                  foreach ($runningPlans as $key => $runningPlan) {
                    $runningPlanClient = RunningPlanClient::where('user_id', $id)->first();
                    $checked = '';
                    if ($runningPlanClient && $runningPlanClient->weeks == $runningPlan) {
                        $checked = 'checked';
                    }
                      $img2 = env('APP_URL') . '/img/edit.png';
                      $data .= '<tr>
                                    <td class="text-center"><input type="radio" name="running_plan" class="custom-control-input" id="customCheck'.$runningPlan.'" data-id="'.$runningPlan.'" value="'.$runningPlan.'" '.$checked.'></td>
                                    <td scope="col text-left">
                                        <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$runningPlan.'">
                                            <label class="custom-control-label" for="customCheck'.$runningPlan.'">'.$runningPlan.' weeks running plan</label>
                                        </div>
                                    </td>
                                </tr>';
                  }
                  $data = $data. '<tr>
                                      <td style="padding-left: 50px;">
                                          <button class="btn btn-primary" onclick="assignRunningPlan()">Assign</button>
                                      </td>
                                      <td></td>
                                  </tr>';
            }

            if ($request->page_type == 'weekly_challenge') {
                $weeklyChallenges = WeeklyChallenges::where('user_id', $authId)->where('title', '!=', NULL)->get();

                $data = '';
                foreach ($weeklyChallenges as $key => $weeklyChallenge) {
                    $weeklyChallengeClient = WeeklyChallengeClient::where('user_id', $id)->first();
                    $checked = '';
                    if ($weeklyChallengeClient && $weeklyChallengeClient->weekly_challenge_id == $weeklyChallenge->id) {
                        $checked = 'checked';
                    }
                    if (strlen($weeklyChallenge->overview) > 50) {
                        $overview = substr($weeklyChallenge->overview, 0, 50) . '.....';
                    } else {
                        $overview = $weeklyChallenge->overview;
                    }
                    $img2 = env('APP_URL') . '/img/edit.png';
                    $data .= '<tr>
                                  <td class="text-center"><input type="radio" name="weekly_challenge" class="custom-control-input" id="customCheck'.$weeklyChallenge->id.'" data-id="'.$weeklyChallenge->id.'" value="'.$weeklyChallenge->id.'" '.$checked.'></td>
                                  <td scope="col text-left">
                                      <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$weeklyChallenge->id.'">
                                          <label class="custom-control-label" for="customCheck'.$weeklyChallenge->id.'">'.$weeklyChallenge->title.'</label>
                                      </div>
                                  </td>
                                  <td class="text-left" scope="col">'.$weeklyChallenge->total_weeks.' Weeks</td>
                                  <td class="text-left" scope="col">'.strip_tags($overview).'</td>
                              </tr>';
                }
                $data = $data. '<tr>
                                    <td style="padding-left: 50px;">
                                        <button class="btn btn-primary" onclick="assignWeeklyChallenge()">Assign</button>
                                    </td>
                                    <td></td><td></td><td></td>
                                </tr>';
            }

            if ($request->page_type == 'workout') {
                  $workoutTypes   = WorkoutType::where('user_id', $authId);
                  if ($request->workoutCategory && $request->workoutCategory != 'all') {
                      $workoutTypes   = $workoutTypes->where('workout_category_id', $request->workoutCategory);
                  }
                  $workoutTypes   = $workoutTypes->get();
                  $workoutVideos  = WorkoutVideo::where('user_id', $authId)->get();

                  $data = '';
                  foreach ($workoutTypes as $key => $type) {
                      $workoutClient = WorkoutClient::where('user_id', $id)->where('sub_category_id', $type->id)->first();
                      $checked = '';
                      if ($workoutClient) {
                          $checked = 'checked';
                      }
                      $workoutTypeVideoIds = WorkoutTypeVideo::where('workout_type_id', $type->id)->pluck('workout_video_id')->toArray();
                      $workoutVideoTitles = WorkoutVideo::whereIn('id', $workoutTypeVideoIds)->pluck('title')->toArray();
                      $videos = '';
                      foreach ($workoutVideoTitles as $key => $workoutVideoTitle) {
                          $videos .= '<div style="margin-bottom: 4px;"><span class="label label-success" style="margin-left: 4px;">'.$workoutVideoTitle.'</span></div>';
                      }

                      if (strlen($type->description) > 80) {
                          $description = substr($type->description, 0, 80) . '.....';
                      } else {
                          $description = $type->description;
                      }
                      if (optional($type->workoutCategory)['name']) {
                          $data .= '<tr>
                                        <td class="text-center"><input type="checkbox" onclick="assignWorkoutToClient(this)" class="custom-control-input" id="customCheck'.$type->id.'" data-id="'.$type->id.'" '.$checked.'></td>
                                        <td scope="col text-left">
                                            <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$type->id.'">
                                                <label class="custom-control-label" for="customCheck'.$type->id.'">'.$type->title.'</label>
                                            </div>
                                        </td>
                                        <td class="text-left" scope="col">'.$type->workoutCategory['name'].'</td>
                                        <td class="text-left" scope="col">'.$description.'</td>
                                        <td>'.$videos.'</td>
                                    </tr>';
                      }
                  }
            }

            if ($request->page_type == 'meal_plan') {
                $mealPlans = MealPlan::where('user_id', $authId)->paginate(25);

                $data = '';
                foreach ($mealPlans as $key => $mealPlan) {
                    $mealPlanClient = MealPlanClient::where('meal_plan_id', $mealPlan->id)
                                                    ->where('user_id', $id)
                                                    ->first();
                    if ($mealPlanClient) {
                        $checked = 'checked';
                    } else {
                        $checked = '';
                    }
                    $file = storage_path('app/public').$mealPlan->pdf;
                    $data .= '<tr>
                                  <td class="text-center"><input type="checkbox" onclick="assignMealPlansToClient(this);" class="custom-control-input" id="customCheck'.$mealPlan->id.'" data-id="'.$mealPlan->id.'" '.$checked.'></td>
                                  <td scope="col text-left">
                                      <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$mealPlan->id.'">
                                          <label class="custom-control-label" for="customCheck'.$mealPlan->id.'">'.$mealPlan->title.'</label>
                                      </div>
                                  </td>
                                  <td class="text-left" scope="col">
                                      <a href="'.$mealPlan->pdf.'" download="'.$mealPlan->pdf_name.'">'.$mealPlan->pdf_name.'</a>
                                  </td>
                              </tr>';
                }
            }

            if ($request->page_type == 'recipe') {
                $recipesCategories = RecipesCategory::where('user_id', $authId)->paginate(25);

                $data = '';
                foreach ($recipesCategories as $key => $category) {
                    $img1 = env('APP_URL') . '/img/add-user.png';
                    $img2 = env('APP_URL') . '/img/edit.png';
                    $recipeCategoryClient = RecipeClient::where('recipe_id', $category->id)
                                                        ->where('user_id', $id)
                                                        ->first();
                    if ($recipeCategoryClient) {
                        $checked = 'checked';
                    } else {
                        $checked = '';
                    }
                    $data .= '<tr>
                                  <td class="text-center"><input type="checkbox" onclick="assignRecipeToClient(this);" class="custom-control-input-box" id="customCheck'.$category->id.'" data-id="'.$category->id.'" '.$checked.'></td>
                                  <td scope="col text-left">
                                      <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$category->id.'">
                                          <label class="custom-control-label" for="customCheck'.$category->id.'">'.$category->name.'</label>
                                      </div>
                                  </td>
                              </tr>';
                }
            }

            // dd($data);
            return $data;
        }

        return view('userdashbaord.profile', compact('user', 'workoutCategories', 'dailyPicturesDates', 'status'));

        // dd($weighins);
        // dd($records);
        if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1) {
            $perPage = 10;
            $startNum = (($_REQUEST['page'] -1) * $perPage) + 1;
        } else {
            $startNum = 1;
        }

        // dd($records);
        return view('userdashbaord.profile', compact('user','weighins','dailypictures','startNum'));
    }

    public function getPlanForAssign(Request $request, $assignType)
    {
        $authId = Auth::id();
        if (Auth::user()->team_parent_id) {
            $authId = Auth::user()->team_parent_id;
        }
        $data = '';
        if ($assignType == 'running') {
            $runningPlans = RunningPlan::where('user_id', $authId)->orderBy('total_weeks', 'asc')->pluck('total_weeks')->toArray();
            $runningPlans = array_unique($runningPlans);

            foreach ($runningPlans as $key => $runningPlan) {
              // $runningPlanClient = RunningPlanClient::where('user_id', $id)->first();
              $checked = '';
              // if ($runningPlanClient && $runningPlanClient->weeks == $runningPlan) {
              //     $checked = 'checked';
              // }
                $img2 = env('APP_URL') . '/img/edit.png';
                $data .= '<tr>
                              <td class="text-center"><input type="radio" name="running_plan" onclick="assignPlansToClient(this)" class="custom-control-input-box" id="customCheck'.$runningPlan.'" data-id="'.$runningPlan.'" value="'.$runningPlan.'" '.$checked.'></td>
                              <td scope="col text-left">
                                  <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$runningPlan.'">
                                      <label class="custom-control-label" for="customCheck'.$runningPlan.'">'.$runningPlan.' weeks running plan</label>
                                  </div>
                              </td>
                          </tr>';
            }
        }
        if ($assignType == 'weekly') {
            $weeklyChallenges = WeeklyChallenges::where('user_id', $authId)->where('title', '!=', NULL)->get();

            foreach ($weeklyChallenges as $key => $weeklyChallenge) {
                // $weeklyChallengeClient = WeeklyChallengeClient::where('user_id', $id)->first();
                $checked = '';
                // if ($weeklyChallengeClient && $weeklyChallengeClient->weekly_challenge_id == $weeklyChallenge->id) {
                //     $checked = 'checked';
                // }
                if (strlen($weeklyChallenge->overview) > 100) {
                    $overview = substr($weeklyChallenge->overview, 0, 100) . '.....';
                } else {
                    $overview = $weeklyChallenge->overview;
                }
                $img2 = env('APP_URL') . '/img/edit.png';
                $data .= '<tr>
                              <td class="text-center"><input type="radio" name="weekly_challenge" onclick="assignWeeklyToClient(this)" class="custom-control-input-box" id="customCheck'.$weeklyChallenge->id.'" data-id="'.$weeklyChallenge->id.'" value="'.$weeklyChallenge->id.'" '.$checked.'></td>
                              <td scope="col text-left">
                                  <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$weeklyChallenge->id.'">
                                      <label class="custom-control-label" for="customCheck'.$weeklyChallenge->id.'">'.$weeklyChallenge->title.'</label>
                                  </div>
                              </td>
                              <td class="text-center" scope="col">'.$weeklyChallenge->total_weeks.' Weeks</td>'.
                              // <td class="text-left" scope="col">'.$overview.'</td>
                          '</tr>';
            }
        }
        if ($assignType == 'workout') {
            $workoutTypes   = WorkoutType::where('user_id', $authId);
            if ($request->workoutCategory && $request->workoutCategory != 'all') {
                $workoutTypes   = $workoutTypes->where('workout_category_id', $request->workoutCategory);
            }
            $workoutTypes   = $workoutTypes->get();
            $workoutVideos  = WorkoutVideo::where('user_id', $authId)->get();

            $data = '';
            foreach ($workoutTypes as $key => $type) {
                // $workoutClient = WorkoutClient::where('user_id', $id)->where('sub_category_id', $type->id)->first();
                $checked = '';
                // if ($workoutClient) {
                //     $checked = 'checked';
                // }
                // $workoutTypeVideoIds = WorkoutTypeVideo::where('workout_type_id', $type->id)->pluck('workout_video_id')->toArray();
                // $workoutVideoTitles = WorkoutVideo::whereIn('id', $workoutTypeVideoIds)->pluck('title')->toArray();
                // $videos = '';
                // foreach ($workoutVideoTitles as $key => $workoutVideoTitle) {
                //     $videos .= '<div style="margin-bottom: 4px;"><span class="label label-success" style="margin-left: 4px;">'.$workoutVideoTitle.'</span></div>';
                // }

                if (strlen($type->description) > 80) {
                    $description = substr($type->description, 0, 80) . '.....';
                } else {
                    $description = $type->description;
                }
                if ($type->workoutCategory['name']) {
                    $data .= '<tr>
                                  <td class="text-center"><input type="checkbox" onclick="assignWorkoutToClient(this)" class="custom-control-input-box" id="customCheck'.$type->id.'" data-id="'.$type->id.'" '.$checked.'></td>
                                  <td class="text-left">
                                      <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$type->id.'">
                                          <label class="custom-control-label" for="customCheck'.$type->id.'">'.$type->title.'</label>
                                      </div>
                                  </td>
                                  <td class="text-left" scope="col">'.$type->workoutCategory['name'].'</td>
                                  <td class="text-left" scope="col">'.$description.'</td>'.
                                  // <td>'.$videos.'</td>
                              '</tr>';
                }
            }
        }

        if ($assignType == 'meal_plan') {
            $mealPlans = MealPlan::where('user_id', $authId)->get();

            foreach ($mealPlans as $key => $mealPlan) {
              // $runningPlanClient = RunningPlanClient::where('user_id', $id)->first();
              $checked = '';
              // if ($runningPlanClient && $runningPlanClient->weeks == $runningPlan) {
              //     $checked = 'checked';
              // }
                $img2 = env('APP_URL') . '/img/edit.png';
                $data .= '<tr>
                              <td class="text-center"><input type="checkbox" onclick="assignMealPlansToClient(this)" class="custom-control-input-box" id="customCheck'.$mealPlan->id.'" data-id="'.$mealPlan->id.'" '.$checked.'></td>
                              <td scope="col text-left">
                                  <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center" data-id="'.$mealPlan->id.'">
                                      <label class="custom-control-label" for="customCheck'.$mealPlan->id.'">'.$mealPlan->title.'</label>
                                  </div>
                              </td>
                              <td class="text-left" scope="col">
                                  <a href="'.$mealPlan->pdf.'" download="'.$mealPlan->pdf_name.'">'.$mealPlan->pdf_name.'</a>
                              </td>
                          </tr>';
            }
        }

        if ($assignType == 'recipe') {
            $recipesCategories = RecipesCategory::where('user_id', $authId)->get();

            foreach ($recipesCategories as $key => $category) {
                $img1 = env('APP_URL') . '/img/add-user.png';
                $img2 = env('APP_URL') . '/img/edit.png';
                $checked = '';
                $data .= '<tr>
                              <td class="text-center"><input type="checkbox" onclick="assignRecipeToClient(this);" class="custom-control-input-box" id="customCheck'.$category->id.'" data-id="'.$category->id.'" '.$checked.'></td>
                              <td scope="col text-left">
                                  <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$category->id.'">
                                      <label class="custom-control-label" for="customCheck'.$category->id.'">'.$category->name.'</label>
                                  </div>
                              </td>
                          </tr>';
            }
        }

        return $data;
    }

    public function assignWorkoutToUsers(Request $request)
    {
        $userIds = $request->ids;
        if ($request->name == 'assign') {
            foreach ($userIds as $key => $userId) {
                $existWorkoutClient = WorkoutClient::where('user_id', $userId)->where('sub_category_id', $request->subcategoryId)->first();
                if (!$existWorkoutClient) {
                    $workoutType = WorkoutClient::create([
                        'user_id' => $userId,
                        'sub_category_id' => $request->subcategoryId
                    ]);
                }
            }
            return response()->json('assigned');
        }

        if ($request->name == 'unassign') {
            WorkoutClient::whereIn('user_id', $userIds)->where('sub_category_id', $request->subcategoryId)->delete();
            return response()->json('unassign');
        }
    }

    public function assignWeeklyToUsers(Request $request)
    {
        $userIds = $request->ids;
        WeeklyChallengeClient::whereIn('user_id', $userIds)->where('weekly_challenge_id', '!=', $request->weeklyChallengeId)->delete();
        foreach ($userIds as $userId) {
            $alreadyExist = WeeklyChallengeClient::where('user_id', $userId)->where('weekly_challenge_id', $request->weeklyChallengeId)->first();

            if (!$alreadyExist) {
                $weeklyChallengeClient = WeeklyChallengeClient::create([
                    'user_id'             => $userId,
                    'weekly_challenge_id' => $request->weeklyChallengeId
                ]);
            }
        }
        return response()->json('assigned');
    }

    public function assignRunningPlanToUsers(Request $request)
    {
        $authId = Auth::id();
        if (Auth::user()->team_parent_id) {
            $authId = Auth::user()->team_parent_id;
        }
        $userIds = $request->ids;
        RunningPlanClient::where('gym_owner_id', $authId)->whereIn('user_id', $userIds)->where('weeks', '!=', $request->weeks)->delete();
        foreach ($userIds as $userId) {
          $alreadyExist = RunningPlanClient::where('gym_owner_id', $authId)->where('user_id', $userId)->where('weeks', $request->weeks)->first();

          if (!$alreadyExist) {
              $runningPlanClient = RunningPlanClient::create([
                  'gym_owner_id' => $authId,
                  'user_id'      => $userId,
                  'weeks'        => $request->weeks
              ]);
          }

        }
        return response()->json('assigned');
    }

    public function assignMealPlanToUsers(Request $request)
    {
        $userIds = $request->ids;
        if ($request->name == 'assign') {
            foreach ($userIds as $key => $userId) {
                $existWorkoutClient = MealPlanClient::where('user_id', $userId)->where('meal_plan_id', $request->mealPlanId)->first();
                if (!$existWorkoutClient) {
                    $workoutType = MealPlanClient::create([
                        'user_id'      => $userId,
                        'meal_plan_id' => $request->mealPlanId
                    ]);
                }
            }
            return response()->json('assigned');
        }

        if ($request->name == 'unassign') {
            MealPlanClient::whereIn('user_id', $userIds)->where('meal_plan_id', $request->mealPlanId)->delete();
            return response()->json('unassign');
        }
    }

    public function assignRecipeToUsers(Request $request)
    {
        $userIds = $request->ids;
        if ($request->name == 'assign') {
            foreach ($userIds as $key => $userId) {
                $existRecipeClient = RecipeClient::where('user_id', $userId)->where('recipe_id', $request->recipeId)->first();
                if (!$existRecipeClient) {
                    $recipe = RecipeClient::create([
                        'user_id'   => $userId,
                        'recipe_id' => $request->recipeId
                    ]);
                }
            }
            return response()->json('assigned');
        }

        if ($request->name == 'unassign') {
            RecipeClient::whereIn('user_id', $userIds)->where('recipe_id', $request->recipeId)->delete();
            return response()->json('unassign');
        }
    }

    public function assignAllToUsers(Request $request)
    {
        $userIds = $request->userIds;
        $ids = $request->ids;

        if ($request->type == 'assign') {
            if ($request->name == 'workout') {
                foreach ($userIds as $key => $userId) {
                    foreach ($ids as $id) {
                        $existWorkoutClient = WorkoutClient::where('user_id', $userId)->where('sub_category_id', $id)->first();
                        if (!$existWorkoutClient) {
                            $workoutType = WorkoutClient::create([
                                'user_id'         => $userId,
                                'sub_category_id' => $id
                            ]);
                        }
                    }
                }
            }

            if ($request->name == 'meal_plan') {
                foreach ($userIds as $key => $userId) {
                    foreach ($ids as $mealPlanId) {
                        $existWorkoutClient = MealPlanClient::where('user_id', $userId)->where('meal_plan_id', $mealPlanId)->first();
                        if (!$existWorkoutClient) {
                            $workoutType = MealPlanClient::create([
                                'user_id'      => $userId,
                                'meal_plan_id' => $mealPlanId
                            ]);
                        }
                    }
                }
            }

            if ($request->name == 'recipe') {
                foreach ($userIds as $key => $userId) {
                    foreach ($ids as $recipeId) {
                        $existRecipeClient = RecipeClient::where('user_id', $userId)->where('recipe_id', $recipeId)->first();
                        if (!$existRecipeClient) {
                            $recipe = RecipeClient::create([
                                'user_id'   => $userId,
                                'recipe_id' => $recipeId
                            ]);
                        }
                    }
                }
            }

            return response()->json('assigned');
        }

        if ($request->type == 'unassign') {
            if ($request->name == 'workout') {
                $clients = WorkoutClient::whereIn('user_id', $userIds)->whereIn('sub_category_id', $ids)->delete();
            }
            if ($request->name == 'meal_plan') {
                $clients = MealPlanClient::whereIn('user_id', $userIds)->whereIn('meal_plan_id', $ids)->delete();
            }
            if ($request->name == 'recipe') {
                $clients = RecipeClient::whereIn('user_id', $userIds)->whereIn('recipe_id', $ids)->delete();
            }

            return response()->json('unassign');
        }
    }

    public function createUser(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'phone'      => 'required|regex:/[0-9]{10}/|digits:10',
            'first_name' => 'required',
            'last_name'  => 'required',
            'email'      => 'required'
        ]);

        // Test validation
        if ($validator->fails()) {
            $message = $validator->errors();

            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        try {
            $inputs = $request->all();
            $deviceId = "default";
            $existEmail = User::where('email', $request->email)
                              ->where('phone', '!=', $request->phone)
                              ->withTrashed()->first();
            if ($existEmail) {
                return redirect()->back()->withInput($request->all())->withErrors('This email is already Exist.');
            }
            $existingUser = User::where('phone', $request->phone)->withTrashed()->first();
            if ($existingUser && $existingUser->parent_id == 0 && $existingUser->parent_id != NULL) {
                $existingUser->restore();
                $existingUser->parent_id  = $user->id;
                $existingUser->name       = $request->first_name.' '.$request->last_name;
                $existingUser->first_name = $request->first_name;
                $existingUser->last_name  = $request->last_name;
                $existingUser->email      = $request->email;
                $existingUser->save();

                return redirect('user/home/active')->with('status','User Re-added Successfully.');
            } elseif ($existingUser && $existingUser->parent_id == 0 && $existingUser->parent_id == NULL) {
                return redirect()->back()->withInput($request->all())->withErrors('This user already Exist as Gym Owner');
            } elseif ($existingUser && $existingUser->parent_id != 0) {
                return redirect()->back()->withInput($request->all())->withErrors('This user already Exist');
            }

            $inputs['country_code'] = '+'.$request->country_code;
            $inputs['country_name'] = strtoupper($request->country);
            $inputs['device_token'] = $deviceId;
            $inputs['name']         = $request->first_name.' '.$request->last_name;
            $inputs['first_name']   = $request->first_name;
            $inputs['last_name']    = $request->last_name;
            $inputs['email']        = $request->email;
            $inputs['password']     = bcrypt($request->phone);
            $inputs['usertype']     = 0;
            $inputs['parent_id']    = $user->id;
            $inputs['status']       = 1;

            // dd($inputs);
            $user = User::create($inputs);
            $user->save();

            DB::commit();

            return redirect('user/home/active')->with('status','User Added Successfully.');
        } catch (Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function removeGroup(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();

        if (count($data['ids']) > 0) {
            try {
                foreach ($data['ids'] as $id) {
                    $group = Group::find($id);
                    if ($group) {
                        $grooupMember = GroupMember::where('group_id',$id)->delete();
                        $notificationGroup = NotificationGroup::where('group_id',$id)->delete();
                        $group->delete();
                    }
                }

                DB::commit();
                return redirect('user/groups')->with('success','Group Removed Successfully.');
            } catch (Exception $e) {
                echo $e->getMessage();die;
                DB::rollback();
                return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
            }
        }
    }

    public function notificationRemove(Request $request)
    {
        $data = $request->all();
        if (count($data['ids']) > 0) {
            try{
                foreach ($data['ids'] as $id) {
                    $notification = Notification::find($id);
                    if ($notification) {
                        $notificationUsers = NotificationUser::where('notification_id',$id)->delete();
                        $notificationGroup = NotificationGroup::where('notification_id',$id)->delete();
                        $notification->delete();
                    }
                }

                DB::commit();
                return array('success'=>'Notification Re-sent successfully');
            } catch (Exception $e) {
                DB::rollback();
                return array('error'=>'Something went wrong.');
            }
        }
    }

    public function createGroup(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'group_title'=>'required',
        ]);

        // Test validation
        if ($validator->fails()) {
            $message = $validator->errors();
            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        try {
            $inputs = $request->all();
            $deviceId = "default";
            $existingGroup = Group::where('title', $request->group_title)->where('user_id',$user->id)->first();

            if ($existingGroup) {
                return redirect()->back()->withInput($request->all())->withErrors('Group name should be unique');
            }

            $inputs['user_id'] = $user->id;
            $inputs['title'] = $request->group_title;

            $group = Group::create($inputs);
            $group->save();

            DB::commit();
            return redirect('user/groups')->with('success','Group Created Successfully.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function editGroup(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'edit_group_id'=>'required',
        ]);

        // Test validation
        if ($validator->fails()) {
            $message = $validator->errors();
            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        try {
            $group = Group::where('id',$request->edit_group_id)->update(['title' => $request->edit_group_title]);

            DB::commit();
            return redirect('user/groups')->with('success','Group Updated Successfully.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function uploadlogoform(Request $request)
    {
        $item = $user = Auth::user();

        return view('userdashbaord.uploadpicture', compact('item'));
    }

    public function messageform(Request $request)
    {
        return view('userdashbaord.message');
    }

    public function general(Request $request)
    {
        $item = $user = Auth::user();
        return view('userdashbaord.general',compact('item'));
    }


    public function updatemessage(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'message'=>'required',
        ]);
        // Test validation
        if ($validator->fails()) {
            $message = $validator->errors();
            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        try{
            $user->message = $request['message'];
            $user->save();

            DB::commit();
            return redirect('user/home/active')->with('status','Share Message Updated successfully.');
        } catch (Exception $e) {
             DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function uploadlogo(Request $request)
    {
        $user = Auth::user();
        if ($user->team_parent_id) {
            $validator = Validator::make($request->all(), [
                'company_contact' => 'required|regex:/[0-9]{10}/|digits:10',
                'email'           => 'required|email|unique:users,email,'.$user->id.',id',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                // 'picture'         => 'required|mimes:jpeg,jpg,png,psd',
                // 'picture'         => 'required',
                'company_contact' => 'required|regex:/[0-9]{10}/|digits:10',
                'email'           => 'required|email|unique:users,email,'.$user->id.',id',
                'company_name'    => 'required',
                'company_address' => 'required',
                // 'workout_url'     => 'required|url',
                // 'nutration_url'   => 'required|url',
                'referral_url'    => 'required|url',
                // 'message'=>'required',
            ]);
        }

        // Test validation
        if ($validator->fails()) {
            $message = $validator->errors();
            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        try {
            $data = $request->all();
            if (isset($request->picture)) {
                $imageName = $user->id . '_' . $request['picture']->getClientOriginalName();
                $request->file('picture')->move(public_path() . '/uploads/profile/', $imageName);
                $request->app_picture = $imageName;

                $data['app_picture'] = $imageName;
                unset($data['picture']);
            }

            $user->update($data);

            DB::commit();
            return redirect('user/upload/logo')->with('status', 'Data added successfully.');
        } catch(Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function notifications(Request $request)
    {
        // dummy data
        $data = [];
        $owner = Auth::user();
        if ($owner->team_parent_id) {
            $owner = User::where('id', $owner->team_parent_id)->first();
        }
        $notifications = Notification::where('owner_id',$owner->id)->orderBy('created_at','desc')->paginate(25);
        // dd($notifications);
        $data = '';

        foreach ($notifications as $notification) {
            $notificationUsers = NotificationUser::select('users.*')->join('users','users.id','=','notification_users.user_id')->where('notification_users.notification_id',$notification->id)->where('users.deleted_at',NULL)->get();
            $time = Carbon::parse($notification->created_at)->format('F d,Y | h:i a');
            $groups = NotificationGroup::select('groups.*')->join('groups','groups.id','=','notification_groups.group_id')->where('notification_groups.notification_id',$notification->id)->get();
            $img1 = env('APP_URL') . '/img/re-send.png';

            $data .= '<div class="col-md-12 col-xs-12 lines">
                           <div class="col-md-1 col-xs-1">
                              <div class="custom-control custom-checkbox" data-id="'.$notification->id.'">
                                <input type="checkbox" onclick="enabledisable();" class="custom-control-input" data-id="'.$notification->id.'" id="customCheck1">
                                <label class="custom-control-label" for="customCheck1"></label>
                            </div>
                           </div>
                           <div class="col-md-5 col-xs-5 lines-7">
                                 <p class="show-read-more" data-id="'.$notification->id.'">'.$notification->message.'</p>
                           </div>
                           <div class="col-md-2 col-xs-5">'.$time.'</div><div class="col-md-3 col-xs-6"><p class="show-read-more2" data-id="'.$notification->id.'">';

             $count = 0 ;
            if (count($groups) > 0) {
                foreach ($groups as $group) {
                    if ($count > 0) {
                        $data .= ', '.$group->title;
                    } else {
                        $data .= $group->title;
                        $count++;
                    }
                }
            }

            if (count($notificationUsers) > 0) {
                foreach ($notificationUsers as $user) {
                    if ($count > 0) {
                        $data .= ', '.$user->first_name.' '.$user->last_name;
                    } else {
                        $data .= $user->first_name.' '.$user->last_name;
                        $count++;
                    }
                }
            }

            $data .= ' </p></div>
                          <div class="col-md-1 col-xs-5 text-center">
                              <a href="#" onclick="resend_notifiction(this); return false;" data-id="'.$notification->id.'"><img src="'.$img1.'"></a>
                          </div>
                      </div>';
        }

        if ($request->ajax()) {
            return $data;
        }

        // dummy data
        return view('userdashbaord.notifications');
    }

    public function notificationMessage(Request $request)
    {
        $data = '';
        $owner = Auth::user();
        $notification = Notification::find($request->notification_id);

        if ($notification) {
            $data .= '<p>'.$notification->message.'</p>';
        } else {
            $data .= '';
        }

        if ($request->ajax()) {
            return $data;
        }
    }

    public function notificationGroupusers(Request $request)
    {
        $data = '';
        $owner = Auth::user();
        $notification = Notification::find($request->notification_id);
        $groups = NotificationGroup::select('groups.*')->join('groups','groups.id','=','notification_groups.group_id')->where('notification_groups.notification_id',$notification->id)->get();
        $notificationUsers = NotificationUser::select('users.*')->join('users','users.id','=','notification_users.user_id')->where('notification_users.notification_id',$notification->id)->where('users.deleted_at',NULL)->get();
        $data .= '<p>';

        $count=0;

        if (count($groups) > 0) {
            foreach ($groups as $group) {
                if ($count > 0) {
                    $data .= ', '.$group->title;
                } else {
                    $data .= $group->title;;
                    $count++;
                }
            }
        }

        if (count($notificationUsers) > 0) {
            foreach ($notificationUsers as $user) {
                if ($count > 0) {
                    $data .= ', '.$user->first_name.' '.$user->last_name;
                } else {
                    $data .= $user->first_name.' '.$user->last_name;
                    $count++;
                }
            }
        }

        $data .= '</p>';
        if ($request->ajax()) {
            return $data;
        }
    }

    public function removeUserGroup(Request $request)
    {
        $owner = Auth::user();
        if (isset($request->group_id)) {
            try {
                $data = $request->all();

                if (count($data['ids'])>0 && isset($data['group_id'])) {
                    foreach ($data['ids'] as $id) {
                        $grooupMember = GroupMember::where('user_id',$id)->where('group_id',$data['group_id'])->delete();
                    }
                }

                DB::commit();
                return redirect('user/groups')->with('success','Users removed from group successfully.');
            } catch (Exception $e) {
                DB::rollback();
                return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
            }
        } else {
            return redirect('user/groups')->with('error','Something went wrong.');
        }
    }

    public function addUserGroup(Request $request)
    {
        $owner = Auth::user();

        try {
            $data = $request->all();

            if (count($data['ids'])>0 && isset($data['group_id'])) {
                foreach ($data['ids'] as $id) {
                    $alreadyExist = GroupMember::where('user_id',$id)->where('group_id',$data['group_id'])->first();

                    if (!$alreadyExist) {
                        $grooupMember = GroupMember::create(array('user_id'=>$id,'group_id'=>$data['group_id']));
                        $grooupMember->save();
                    }
                }
            }

            DB::commit();
            return redirect('user/home/active')->with('status','Users added in group successfully.');
        } catch(Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function groups(Request $request)
    {
        $data = [];
        $owner = Auth::user();
        if ($owner->team_parent_id) {
            $owner = User::where('id', $owner->team_parent_id)->first();
        }

        $groups = Group::where('user_id',$owner->id)->paginate(25);
        $data = '';

        foreach ($groups as $group) {
            $img1 = env('APP_URL') . '/img/add-user.png';
            $img2 = env('APP_URL') . '/img/edit.png';
            $data .= '<tr>
                        <td style="padding-left: 40px;">
                            <div  class="custom-control custom-checkbox" data-id="'.$group->id.'">
                                <input type="checkbox" onclick="enabledisable();" class="custom-control-input" data-id="'.$group->id.'" id="customCheck1" >
                                <label class="custom-control-label" for="customCheck1">'.$group->title.'</label>
                            </div>
                        </td>
                        <td ><p class="show-read-more" data-id="'.$group->id.'">';
            $groupMembers = GroupMember::select('users.*')->where('group_members.group_id',$group->id)
                                      ->join('users', 'users.id', '=', 'group_members.user_id')->where('users.deleted_at', NULL)
                                      ->orderby('users.first_name','asc')
                                      ->get();
            $count = 0;
            foreach ($groupMembers as $member) {
                if ($count==0) {
                    $data .= $member->first_name.' '.$member->last_name;
                } else {
                    $data .= ', '.$member->first_name.' '.$member->last_name;
                }
                $count++;
            }
            // $data .= 'lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum, lorem ipsum,';
            if (!\Auth::user()->team_parent_id) {
            $data .= '</p>
                        </td>
                        <td class="text-right">

                            <div class="col-md-3 col-xs-3 text-center" style="display: contents;">
                              <a class="" data-toggle="modal" onclick="addinguser(this);" data-target="#addGroupModal" data-id="'.$group->id.'" style="cursor: pointer;">
                                  <img src="'.$img1.'"/>

                              </a>
                              <a class="" data-toggle="modal" onclick="editgroup(this);" data-target="#editGroupModal" data-id="'.$group->id.'" data-name="'.$group->title.'" style="cursor: pointer;">
                                <img src="'.$img2.'" style="float: right; padding: 0px 20px;" />
                                </a>
                            </div>
                        </td>
                    </tr>';
            } else {
                $data .= '</p>
                            </td>
                            <td class="text-right">
                            </td>
                        </tr>';
            }
        }

        if ($request->ajax()) {
            return $data;
        }

        $users = User::where('usertype',0)
                    ->where('parent_id',$owner->id)->with('groupmembers')
                    ->get();

        return view('userdashbaord.groups',compact('users'));
    }

    public function groupMembers(Request $request)
    {
        $data = '';
        $owner = Auth::user();
        if (isset($request->search) && strlen($request->search)>0) {
            $search = $request->search;
            $users = User::where('usertype',0)
                          ->where('parent_id',$owner->id)
                          ->where(function($query) use ($search) {
                          $query->Where('first_name', 'LIKE', "%{$search}%")
                                ->orWhere('last_name', 'LIKE', "%{$search}%");
                          })
                          ->orderby('first_name','asc')
                          ->get();
        } else {
            $users = User::where('usertype',0)->where('parent_id',$owner->id)->orderby('first_name','asc')->get();
        }

        if (count($users)>0) {
            $count = 0;
            foreach ($users as $user) {
                $groupMember = GroupMember::select('group_members.user_id')->where('group_members.group_id',$request->group_id)->where('user_id',$user->id)
                    ->first();
                if ($groupMember) {
                    $data .= '<div  class="form-group form-check" data-id="'.$user->id.'">
                                <input type="checkbox" checked onclick="enabledisablebuttons(this);" class="custom-control-inputs" data-id="'.$user->id.'" id="customCheck1" name="user_member" >
                                <label class="custom-control-label" for="customCheck1" style="margin-left: 30px;"> '.$user->first_name.' '.$user->last_name.'</label>
                            </div>';

                            $count ++;
                } else {
                    $data .= '<div  class="form-group form-check" data-id="'.$user->id.'">
                                <input type="checkbox" onclick="enabledisablebuttons(this);" class="custom-control-inputs" data-id="'.$user->id.'" id="customCheck1" name="user_member" >
                                <label class="custom-control-label" for="customCheck1" style="margin-left: 30px;"> '.$user->first_name.' '.$user->last_name.'</label>
                            </div>';
                }
            }

            if ($count>0) {
                $resultpre = '
                            <div class="row" style="margin: 20px 0px;">
                            <div class="col-md-4 col-xs-4 text-left" style="padding: 0px;">
                            <input type="checkbox"  class="custom-control-input" data-id="'.$user->id.'" id="group_select_all" onclick="selectAll(this);" name="user_member" >
                            <label class="custom-control-label" for="customCheck1" style="    margin-left: 20px;">Select All </label>
                            </div>

                            <div class="col-md-4 col-xs-4">
                            <a class="" href="#" id="rm_user_group">
                             <button type="button" style="color:#ffffff; background: #e81b3f;border: 1px solid #e81b3f;    margin-top: 0px;" onclick="rmgfrmroup();">Remove User</button>
                             </a>
                             </div>
                             <div class="col-md-4 col-xs-4">
                             <a class="" href="#" id="add_user_group">
                             <button type="button" class="btn btn-primary no-margin" onclick="addingroup();"><i class="fa fa-plus" aria-hidden="true"></i>
                             Add Users</button>
                             </a></div> </div><div class="group_users" id="group_users" style="padding-bottom: 10px;">';
                $resultpo = '</div>';
            } else {
                $resultpre = '
                            <div class="row" style="margin: 20px 0px;">
                            <div class="col-md-4 col-xs-4 text-left" style="padding: 0px;">
                            <input type="checkbox"  class="custom-control-input" data-id="'.$user->id.'" id="group_select_all" onclick="selectAll(this);" name="user_member" >
                            <label class="custom-control-label" for="customCheck1" style="    margin-left: 20px;">Select All </label>
                            </div>
                            <div class="col-md-4 col-xs-4">
                                <a class="" href="#" id="rm_user_group">
                             <button type="button" class="btn no-margin" disabled style="color:#ffffff; background: #7f7f7f99;border: 1px solid #7f7f7f99;margin-right: 12px;" onclick="rmgfrmroup();">Remove User</button>
                             </a>
                             </div>
                             <div class="col-md-4 col-xs-4">
                             <a class="" href="#" id="add_user_group">
                             <button type="button" disabled  style="color:#ffffff; background: #7f7f7f99;border: 1px solid #7f7f7f99;" class="btn btn-primary no-margin" onclick="addingroup();"><i class="fa fa-plus" aria-hidden="true"></i>Add Users</button>
                             </a>
                             </div></div>
                             <div class="group_users" id="group_users" style="padding-bottom: 10px;">';
                $resultpo = '</div>';
            }

            $finalData = $resultpre.$data.$resultpo;
        } else {
            $finalData = '<div style="float:left;">Users not found.</div>';
        }

        if ($request->ajax()) {
            return $finalData;
        }
    }

    public function groupMembersJoin(Request $request)
    {
        $data = '';
        $owner = Auth::user();

        $groupMembers = User::select('users.*')->where('group_members.group_id',$request->group_id)
                        ->join('group_members','group_members.user_id','=','users.id')->get();

         // GroupMember::where('group_members.group_id',$request->group_id)->get();
        if (count($groupMembers)>0) {
            foreach ($groupMembers as $user) {
                    $data .= '<div class="form-group form-check">
                               <label class="form-check-label"  for="Check1">'.$user->first_name.' '.$user->last_name.'</label>
                            </div>';
            }
        } else {
            $data .= '<div style="float:left;">Users not found.</div>';
        }

        if ($request->ajax()) {
            return $data;
        }
    }

    public function groupSearch(Request $request)
    {
        $data = '';
        $owner = Auth::user();
        // $groups = Group::where('title', 'like', "%{$request->search}%")->get();

        // if (count($groups) > 0) {
        //     foreach ($groups as $group) {
        //         $data .= '<option value="'.$group->title.'">';
        //     }
        // } else {
        //     $data .= '';
        // }

        $users = User::where('parent_id',$owner->id)
                      ->orderBy('first_name','asc')
                      ->get();

        if (count($users) > 0) {
            foreach ($users as $user) {
                $data .= '<option value="'.$user->id.'" data-badge="">'.$user->first_name.' '.$user->last_name.'</option>';
            }
        } else {
             $data = '';
        }

        if ($request->ajax()) {
            return $data;
        }
    }


    public function groupsnameSearch(Request $request)
    {
        $data = '';
        $owner = Auth::user();

        $groups = Group::where('title', 'like', "%{$request->search}%")
                        ->where('user_id',$owner->id)
                        ->orderBy('title','asc')
                        ->get();

        if (count($groups) > 0) {
            foreach ($groups as $group) {
                $data .= '<option value="'.$group->id.'" data-badge="">'.$group->title.'</option>';
            }
        } else {
            $data .= '';
        }

        $users = User::where('parent_id',$owner->id)->get();

        if ($request->ajax()) {
            return $data;
        }
    }

    public function notificationSend(Request $request)
    {
        $owner = Auth::user();
        if ($owner->team_parent_id) {
            $owner = User::where('id', $owner->team_parent_id)->first();
        }

        if (!isset($request->group_search) && !isset($request->group_name )) {
            return redirect()->back()->withInput($request->all())->withErrors('Please enter group name.');
        }

        if (!$request->notification_msg) {
            return redirect()->back()->withInput($request->all())->withErrors('Please enter message.');
        }

        try {
            $notification = Notification::Create(array('owner_id'=>$owner->id,'message'=>$request->notification_msg));
            $notification->save();

            if (isset($request->group_name) && count($request->group_name)) {
                foreach ($request->group_name as $groupid) {
                    $checkGroup = Group::find($groupid);
                    if ($checkGroup) {
                        $groupMembers = GroupMember::select('users.*')->where('group_id',$checkGroup->id)->join('users','users.id','=','group_members.user_id')->get();

                        foreach ($groupMembers as $user) {
                            $this->sendNotification($user,$request->notification_msg);
                        }

                        $notificationGroup = NotificationGroup::Create(array('group_id'=>$checkGroup->id,'notification_id'=>$notification->id));
                        $notificationGroup->save();
                    }
                }
            }

            if (isset($request->group_search)) {
                foreach ($request->group_search as $userSearchId) {
                    $checkUser = User::find($userSearchId);
                    if ($checkUser) {
                        $this->sendNotification($checkUser,$request->notification_msg);

                        $notificationUser = NotificationUser::Create(array('user_id'=>$checkUser->id,'notification_id'=>$notification->id));
                        $notificationUser->save();
                    }
                }
            }

            DB::commit();
            return redirect('user/notifications')->with('success','Notification sent successfully');
        } catch(Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function notificationResend(Request $request)
    {
        $owner = Auth::user();
        if ($owner->team_parent_id) {
            $owner = User::where('id', $owner->team_parent_id)->first();
        }

        if (!$request->notification_id) {
            return array('error'=>'Something went wrong.');
        }

        $notification = Notification::find($request->notification_id);

        if (!$notification) {
            // return redirect()->back()->withInput($request->all())->withErrors('Something went wrong.');
            return array('error'=>'Something went wrong.');
        }

        $notificationUsers = NotificationUser::select('users.*')->join('users','users.id','=','notification_users.user_id')->where('notification_users.notification_id',$notification->id)->where('users.deleted_at',NULL)->get();

        if (count($notificationUsers) > 0) {
            foreach ($notificationUsers as $notificationuser) {
                $this->sendNotification($notificationuser,$notification->message);
            }
        }

        //$groupMembers = NotificationGroup::select('groups.*')->join('groups','groups.id','=','notification_groups.group_id')->where('notification_groups.notification_id',$notification->id)->get();
        $groupMembers = NotificationGroup::select('users.*')
                                        ->join('groups','groups.id','=','notification_groups.group_id')
                                        ->join('group_members','group_members.group_id','=','groups.id')
                                        ->join('users','users.id','=','group_members.user_id')
                                        ->where('notification_groups.notification_id',$notification->id)
                                        ->get();

        if (count($groupMembers) > 0) {
            foreach ($groupMembers as $groupuser) {
                $this->sendNotification($groupuser,$notification->message);
            }
        }

        // echo 'sdsadad';die;
        return array('success'=>'Notification Re-sent successfully');
    }

    public function sendNotification($user, $msg)
    {
        if (strlen($user->device_token) >50) {
            $url = 'https://fcm.googleapis.com/fcm/send';
            /*api_key available in:Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key*/
            // old Key
            $api_key ='AAAARbMHGHg:APA91bFQVjiCpstSDFaGrXv-Fc5JZnCcaFMWjQWo1Mad_vZ9CElqzMrLhCtgpIRnBhrw2PV1fCwqI0zL_Y3o60BSp0YT6WX82XntrkUrWYKp-vGDHwtQh5ToN7dcoxxCTUTC0kjqBBjQ';
            // New Key
            // $api_key ='AAAAo8AdicQ:APA91bE1QtyFul_AD7_tUVX4VizCbJZSYBzuZDmcmifCmgsGXxB79r_Klu094sujaDMN74NAHIFJP-v5bazoZwHCpN5FyepHbXbMcA2grSHdtVy4mfqFnSUJQhlpXSE77jVhZqBPDfhk';
            $fields = [
                    "to" =>$user->device_token,
                    "collapse_key" => "type_a",
                    "notification" => [
                        "body" => $msg,
                        "title" => $user->first_name.' '.$user->last_name,
                        "sound" => "default",
                    ],
                    "data" => [
                        "body" => "Body of Your Notification in Data",
                        "title" => "Title of Your Notification in Title",
                        "sender_name" => $user->first_name.' '.$user->last_name,
                        "sender_picture" => $user->picture,
                    ]
            ];
            //header includes Content type and api key
            $headers = array(
                    'Content-Type:application/json',
                    'Authorization:key='.$api_key
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            $result = curl_exec($ch);
            // dd($result);
            if ($result === FALSE) {
                    die('FCM Send Error: ' . curl_error($ch));
            }
            curl_close($ch);
        } else {
                print ('device token not found.'.'</br>');
        }
    }

    public function editWeighin(Request $request)
    {
        $owner = Auth::user();
        $validator = Validator::make($request->all(), [
            'weight_edit'=>'required',
            'weighin_id'=>'required',
        ],
        [
            'weight_edit.required' => 'Weight is required',
            'weighin_id.required' => 'Something went wrong.'
        ]);

        // Test validation
        if ($validator->fails()) {
            $message = $validator->errors();
            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        if (!isset($request->user_id)) {
            return redirect()->back()->withInput($request->all())->withErrors('Something went wrong.');
        }

        $user=User::find($request->user_id);

        if (!$user) {
            return redirect()->back()->withInput($request->all())->withErrors('Something went wrong.');
        }

        try {
            $currentDate = date("m/d/Y");
            // dd($currentDate);

            $checkweighIn = Weighin::where('user_id',$user->id)->where('id',$request->weighin_id)->first();
            $bodyfat = isset($request->edit_body_fat)?$request->edit_body_fat:0;
            if ($checkweighIn) {
                $checkweighIn->weight = $request->weight_edit;
                $checkweighIn->body_fat_percent = $bodyfat;
                $checkweighIn->save();
                $msg = 'Weigh In Updated Successfully.';
            } else {
                $weighIn = Weighin::Create(array('createdate'=>Carbon::createFromFormat('m/d/Y', $currentDate)->format('Y-m-d h:i:s'),'user_id'=>$user->id,'date'=>  $currentDate,'weight'=>$request->weight,'body_fat_percent'=>$bodyfat));
                $weighIn->save();
                $msg = 'Weigh In Created Successfully.';
            }

            DB::commit();
            return redirect('user/profile/'.$user->id)->with('success',$msg);
        } catch(Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function createWeighin(Request $request)
    {
        // dd( $request->all());
        $owner = Auth::user();

        $validator = Validator::make($request->all(), [
            'weight'=>'required',
            // 'body_fat'=>'required',
        ]);

        // Test validation
        if ($validator->fails()) {
            $message = $validator->errors();
            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        if (!isset($request->user_id)) {
            return redirect()->back()->withInput($request->all())->withErrors('Something went wrong.');
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return redirect()->back()->withInput($request->all())->withErrors('Something went wrong.');
        }

        try {
            $currentDate = date("m/d/Y");
            // dd($currentDate);

            $checkweighIn = Weighin::where('user_id',$user->id)->where('date',$currentDate)->first();
            $bodyfat = isset($request->body_fat)?$request->body_fat:0;
            if ($checkweighIn) {
                $checkweighIn->weight = $request->weight;
                $checkweighIn->body_fat_percent = $bodyfat;
                $checkweighIn->save();
                $msg = 'Weigh In Updated Successfully.';
            } else {
                $weighIn = Weighin::Create(array('createdate'=>Carbon::createFromFormat('m/d/Y', $currentDate)->format('Y-m-d h:i:s'),'user_id'=>$user->id,'date'=>  $currentDate,'weight'=>$request->weight,'body_fat_percent'=>$bodyfat));
                $weighIn->save();
                $msg = 'Weigh In Created Successfully.';
            }

            DB::commit();
            return redirect('user/profile/'.$user->id)->with('success',$msg);
        } catch(Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function enableDisableUsers(Request $request, $id)
    {
        $user = User::where('id', $id)->first();

        if ($request->status == 'enabled') {
            $status = 1;
        } else {
            $user->tokens()->delete();
            $status = 0;
        }

        $user->update([
            'status' => $status,
        ]);

        return response()->json($request->status);
    }

    public function teamMembers(Request $request)
    {
        $teamMembers = User::where('team_parent_id', Auth::id())->paginate(25);

        $data = '';
        foreach ($teamMembers as $key => $teamMember) {
            $img2 = env('APP_URL') . '/img/edit.png';
            $data .= '<tr class="row1" data-id="'.$teamMember->id.'">
                          <td class="text-center"><input type="checkbox" onclick="enabledisable();" class="custom-control-input" id="customCheck'.$teamMember->id.'" data-id="'.$teamMember->id.'"></td>
                          <td scope="col text-left">
                              <div class="custom-control custom-checkbox d-flex justify-content-start align-items-center"  data-id="'.$teamMember->id.'">
                                  <label class="custom-control-label" for="customCheck'.$teamMember->id.'">'.$teamMember->name.'</label>
                              </div>
                          </td>
                          <td class="text-left">'.$teamMember->phone.'</td>
                          <td class="text-left">'.$teamMember->email.'</td>
                          <td>
                              <label class="switch-user">
                                  <input type="checkbox" '.($teamMember->status ? "checked" : "").' class="toggle" onclick="toggle(this)" name="is_approved" value="" data-url="'.route('user.status', ['id' => $teamMember->id]).'">
                                  <span class="slider-user round"></span>
                              </label>
                          </td>
                          <td>
                              <a href="javascript:void(0)" data-href="'.route('user.single-team-members.remove', $teamMember->id).'" class="btn btn-danger" onclick="confirmDelete(this)">Remove</a>
                          </td>'.
                          // <td>
                          //     <a class="" data-toggle="modal" onclick="editTeamMember(this);" data-target="#editModal" data-id="'.$teamMember->id.'" data-firstname="'.$teamMember->first_name.'" data-lastname="'.$teamMember->last_name.'" data-phone="'.$teamMember->phone.'" data-code="'.$teamMember->country_name.'" data-email="'.$teamMember->email.'" style="cursor: pointer;">
                          //         <img src="'.$img2.'" />
                          //     </a>
                          // </td>
                      '</tr>';
        }

        if ($request->ajax()) {
            return $data;
        }

        return view('userdashbaord.team-members.index');
    }

    public function createTeamMember(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'phone'      => 'required|regex:/[0-9]{10}/|digits:10',
            'first_name' => 'required',
            'last_name'  => 'required',
            'email'      => 'required'
        ]);

        if ($validator->fails()) {
            $message = $validator->errors();

            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        try {
            $inputs = $request->all();
            $deviceId = "default";
            $existEmail = User::where('email', $request->email)
                              ->where('phone', '!=', $request->phone)
                              ->withTrashed()->first();
            if ($existEmail) {
                return redirect()->back()->withInput($request->all())->withErrors('This email is already Exist.');
            }
            $existingUser = User::where('phone', $request->phone)->withTrashed()->first();
            if ($existingUser && $existingUser->team_parent_id != NULL) {
                return redirect()->back()->withInput($request->all())->withErrors('This user already Exist as Team Member');
            }
            if ($existingUser && $existingUser->parent_id == 0 && $existingUser->parent_id != NULL) {
                $existingUser->restore();
                $existingUser->parent_id  = $user->id;
                $existingUser->name       = $request->first_name.' '.$request->last_name;
                $existingUser->first_name = $request->first_name;
                $existingUser->last_name  = $request->last_name;
                $existingUser->email      = $request->email;
                $existingUser->save();

                return redirect('user/team-members')->with('status', 'Team Member Re-added Successfully.');
            } elseif ($existingUser && $existingUser->parent_id == 0 && $existingUser->parent_id == NULL) {
                return redirect()->back()->withInput($request->all())->withErrors('This user already Exist as Gym Owner');
            } elseif ($existingUser && $existingUser->parent_id != 0) {
                return redirect()->back()->withInput($request->all())->withErrors('This user already Exist');
            }

            $inputs['country_code'] = '+'.$request->country_code;
            $inputs['country_name'] = $request->country;
            $inputs['device_token'] = $deviceId;
            $inputs['name']         = $request->first_name.' '.$request->last_name;
            $inputs['first_name']   = $request->first_name;
            $inputs['last_name']    = $request->last_name;
            $inputs['email']        = $request->email;
            $inputs['password']     = bcrypt($request->phone);
            $inputs['usertype']     = 1;
            // $inputs['parent_id']    = $user->id;
            $inputs['status']       = 1;
            $inputs['team_parent_id'] = $user->id;

            // dd($inputs);
            $user = User::create($inputs);
            $user->save();

            DB::commit();

            return redirect('user/team-members')->with('success', 'Team Member Added Successfully.');
        } catch (Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput($request->all())->withErrors($e->getMessage());
        }
    }

    public function updateTeamMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|regex:/[0-9]{10}/|digits:10|unique:users,phone,'.$request->edit_team_member_id,
            'first_name'   => 'required',
            'last_name'    => 'required',
            'email'        => 'required|unique:users,email,'.$request->edit_team_member_id
        ]);

        if ($validator->fails()) {
            $message = $validator->errors();

            return redirect()->back()->withInput($request->all())->withErrors($message);
        }

        User::where('id', $request->edit_team_member_id)->update([
            'name'       => $request->first_name.' '.$request->last_name,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'phone'      => $request->phone_number
        ]);

        return redirect('user/team-members')->with('status', 'Team Member Updated Successfully.');
    }

    public function removeSingleTeamMember($id)
    {
        $teamMember = User::findOrFail($id);
        $teamMember->forcedelete();

        return redirect('user/team-members')->with('success', 'Team Member Removed Successfully.');
    }

    public function removeTeamMember(Request $request)
    {
        $data = $request->all();
        foreach ($data['ids'] as $id) {
            $enduser = User::find($id);

            if ($enduser) {
                $enduser->forcedelete();
            }
        }

        return redirect('user/team-members')->with('success', 'Team Member Removed Successfully.');
    }
}
