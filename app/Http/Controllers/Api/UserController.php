<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiResponse;
use App\User;
use App\TrackerToken;
use Carbon\Carbon;

class UserController extends Controller
{
	private $msg;
	private $apiResponse;
    private $json_data;

	public function __construct(ApiResponse $apiResponse){
        $this->apiResponse=$apiResponse;
        $this->msg="";
        $this->json_data=[];
    }

    public function get_profile(Request $request,$phone){
    	try{
            if(is_null($phone)){
                $data=[];
                return $this->apiResponse->sendResponse(400,'Bad request, phone number required',$data);
            }
            $user=User::where('phone',$phone)->first();
            $gps=$user->location_history->last();
            if(is_null($gps)){
                $data=[
                    'name'=>$user->name,
                    'phone'=>$user->phone,
                    'last_known'=>[
                        'lat'=>NULL,
                        'lng'=>NULL,
                        'time'=>NULL,
                    ],
                ];
            }
            else{
                $data=[
                    'name'=>$user->name,
                    'phone'=>$user->phone,
                    'last_known'=>[
                        'lat'=>$gps->lat,
                        'lng'=>$gps->lng,
                        'time'=>$gps->timestamp,
                    ],
                ];
            }
    		return $this->apiResponse->sendResponse(200,'User details fetched successfully.',$data);
    	}
    	catch(Exception $e){
    		return $this->apiResponse->sendResponse(500,'Internal server error.',$this->json_data);
    	}
    }

    public function tracker_token(Request $request){
        try{
            $user_id = $request->user()->id;
            $token = User::where('id',$user_id)->first();
            $token = $token->token;
            if(is_null($token)){
                $token = substr(hash('sha256', mt_rand() . microtime() . "kukur"), 0, 20);
                $new_token = new TrackerToken;
                $new_token->user_id = $user_id;
                $new_token->token = $token;
                $new_token->save();
            }
            else{
                $token = $token['token'];
            }
            $data=[
                'user_id'=>$user_id,
                'phone'=>$request->user()->phone,
                'token'=>$token,
            ];
            return $this->apiResponse->sendResponse(200,'Token fetched successfully',$data);
        }
        catch(Exception $e){
            return $this->apiResponse->sendResponse(500,'Internal server error.',$this->json_data);
        }
    }

    public function history(Request $request,$phone){
        try{

        }
        catch(Exception $e){
            return $this->apiResponse->sendResponse(500,'Internal server error.',$this->json_data);
        }
    }
}
