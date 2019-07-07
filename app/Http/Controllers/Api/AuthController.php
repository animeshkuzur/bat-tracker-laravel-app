<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiResponse;
use Validator;
use App\User;
use GuzzleHttp\Client; 
use Illuminate\Foundation\Application;
use Carbon\Carbon;

class AuthController extends Controller
{
	private $msg;
    private $apiResponse;
    private $json_data;
    private $apiConsumer;
    private $db;
 	private $auth;
 	protected $username = 'phone';

	public function __construct(Application $app, ApiResponse $apiResponse){
        $this->msg="";
        $this->apiResponse=$apiResponse;
        $this->apiConsumer = new Client();
        $this->auth = $app->make('auth');
        $this->db = $app->make('db');
        $this->otp_digits = 6;
        $this->MSG91_AUTHKEY = env('MSG91_APP_KEY');
        $this->MSG91_SENDERID = env('MSG91_SENDER_ID');
        $this->send_request = new Client();
    }

    public function proxy($grantType, array $data = []){
    	$config = app()->make('config');
        $data = array_merge($data, [
            'client_id'     => env('PASSWORD_CLIENT_ID'),
            'client_secret' => env('PASSWORD_CLIENT_SECRET'),
            'grant_type'    => $grantType
        ]);
        try{
        	$user = User::where('phone',$data['username'])->first();
        	$response = $this->apiConsumer->post(sprintf('%s/oauth/token', $config->get('app.url')), [
                'form_params' => $data
            ]);
            $data = json_decode($response->getBody());
	        $token_data = [
	        	'access_token' => $data->access_token,
	        	'expires_in' => $data->expires_in,
	            'refresh_token' => $data->refresh_token,
	        ];
            return $this->apiResponse->sendResponse(200,'Login Successful',$token_data);
        }
        catch(\GuzzleHttp\Exception\BadResponseException $e){
        	$response = json_decode($e->getResponse()->getBody());
        	echo $response;
        	$data = [
                'access_token' => '',
                'expires_in' => '',
                'refresh_token' => '',
            ];
            return $this->apiResponse->sendResponse($e->getCode(),$response['message'],$data);
        }
    }

    public function proxyLogin($phone,$password){
    	$user = User::where('phone',$phone)->first();
    	if (!is_null($user)) {
            $res = 1;
            $accessTokens = $this->token($user->id);
            foreach ($accessTokens as $accessToken) {
                $res = $res * $this->proxyLogout($accessToken->id);
            }

            return $this->proxy('password', [
                'username' => $phone,
                'password' => $password
            ]);
        }
        else{
        	$data = [
        		'access_token' => '',
            	'expires_in' => '',
            	'refresh_token' => '',
            ];
            return $this->apiResponse->sendResponse(401,'The user credentials were incorrect.',$data);
        }
    }

    public function proxyRefresh($refreshToken,$phone){
    	return $this->proxy('refresh_token', [
            'refresh_token' => $refreshToken,
            'username' => $phone
        ]);
    }

    public function refresh(Request $request){
    	try{
    		$validator = Validator::make($request->all(), [
	            'phone' => 'required|max:10|min:10',
	            'refresh_token' => 'required',
	        ]);

	        if($validator->fails()){
	        	return $this->apiResponse->sendResponse(400,$validator->errors(),'');
	        }

    		$refreshToken = $request->get('refresh_token');
            $phone = $request->get('phone');
            $response = $this->proxyRefresh($refreshToken,$phone);
            return $response;
    	}
    	catch(Exception $e){
    		return $this->apiResponse->sendResponse(500,'Internal server error',$this->json_data);
    	}
    }

    public function login(Request $request){
    	try{
    		$validator = Validator::make($request->all(), [
	            'phone' => 'required|max:10|min:10',
	            'password' => 'required',
	        ]);

	        if($validator->fails()){
	        	return $this->apiResponse->sendResponse(400,$validator->errors(),'');
	        }
	        $phone = $request->get('phone');
            $password = $request->get('password');

            $response = $this->proxyLogin($phone, $password);
            return $response;
    	}
    	catch(Exception $e){
    		return $this->apiResponse->sendResponse(500,'Internal server error',$this->json_data);
    	}
    }

    public function register(Request $request){
    	try{
    		$validator = Validator::make($request->all(), [
	            'name' => 'required',
	            'password' => 'required',
	            'c_password' => 'required|same:password',
	            'phone' => 'required|max:10|min:10',
	        ]);

	        if($validator->fails()){
	        	return $this->apiResponse->sendResponse(400,$validator->errors(),'');
	        }

	        $data = $request->all();
	        $user = new User();
	        $user->name = $data['name'];
	        $user->phone = $data['phone'];
	        $user->password = bcrypt($data['password']);
	        $user->save();

	        $response = $this->proxyLogin($data['phone'], $data['password']);
	        if($user){
	        	return $this->apiResponse->sendResponse(201,'User successfully registered.',$response['data']);
	        }
	        else{
	        	return $this->apiResponse->sendResponse(500,'Internal Server Error','');
	        }
    	}
    	catch(Exception $e){
    		return $this->apiResponse->sendResponse(500,'Internal Server Error','');
    	}
    	
    }

    public function token($user_id){
        try{
            $token = $this->db
                ->table('oauth_access_tokens')
                ->where('user_id',$user_id)
                ->where('revoked',0)
                ->get(['id']);
            return $token;
        }
        catch(Exception $e){

        }
        
    }

    public function logout(Request $request){
    	try{
    		$response=1;
    	    $user_id = $request->user()->id;
    	    $accessTokens = $this->token($user_id);
    	    foreach ($accessTokens as $accessToken) {
    	        $response = $response * $this->proxyLogout($accessToken->id);
    	    }
    	    if($response){
    	        return $this->apiResponse->sendResponse(200,'Token successfully destroyed',$this->json_data);
    	    }
    		return $this->apiResponse->sendResponse(500,'Internal server error',$this->json_data);
    	}
    	catch(Exception $e){
    		return $this->apiResponse->sendResponse(500,'Internal server error',$this->json_data);
    	}
    }

    public function proxyLogout($accessToken){
    	try{
        	$refreshToken = $this->db
            	->table('oauth_refresh_tokens')
            	->where('access_token_id', $accessToken)
            	->update([
                	'revoked' => true
            	]);
        	if($refreshToken){
                if($this->revoke($accessToken)){
                    return 1;
                }    
            }
            return 0;
    	}
    	catch(Exception $e){

    	}
    }

    public function revoke($accessToken){
        try{
            $Token = $this->db
                ->table('oauth_access_tokens')
                ->where('id', $accessToken)
                ->update([
                    'revoked' => true
                ]);
            if($Token){
                return 1;
            }
            return 0;
        }
        catch(Exception $e){

        }
    }

    public function send_otp(Request $request){
    	try{

            $phone = $request->user()->phone;
            $verified_at = $request->user()->phone_verified_at;
            $otp = rand(pow(10, $this->otp_digits-1), pow(10, $this->otp_digits)-1);
            $message = urlencode($otp.' is your OTP for Bat Tracker');
            $response = $this->send_request->get('https://control.msg91.com/api/sendotp.php?authkey='.$this->MSG91_AUTHKEY.'&mobile='.$phone.'&message='.$message.'&sender='.$this->MSG91_SENDERID.'&otp='.$otp);
            $data = json_decode($response->getBody());
            $new_data = [
                'phone'     => $phone,
                'message_id' => $data->message,
                'type' => $data->type,
                'verified_at' => $verified_at
            ];
            if($data->type == 'success'){
                return $this->apiResponse->sendResponse(200,'OTP sent successfully',$new_data);
            }
            else{
                return $this->apiResponse->sendResponse(500,'unable to send OTP',$new_data);
            }

        }
        catch(Exception $e){
            $new_data = [
                'phone'     => '',
                'message_id' => '',
                'type' => '',
                'verified_at' => ''
            ];
            return $this->apiResponse->sendResponse(500,'Internal server error',$new_data);
        }
    }

    public function verify_otp(Request $request){
    	try{
    		$validator = Validator::make($request->all(), [
	            'otp' => 'required',
	        ]);

	        if($validator->fails()){
	        	return $this->apiResponse->sendResponse(400,$validator->errors(),'');
	        }

            $phone = $request->user()->phone;
            $otp = $request->only('otp');
            $response = $this->send_request->get('https://control.msg91.com/api/verifyRequestOTP.php?authkey='.$this->MSG91_AUTHKEY.'&mobile='.$phone.'&otp='.$otp['otp']);
            $data = json_decode($response->getBody());
            $new_data = [
                'phone'     => $phone,
                'message_id' => $data->message,
                'type' => $data->type,
                'verified_at' => ''
            ];
            if($data->type == 'success'){
                $driver = User::where(['phone'=>$phone])->update(['phone_verified_at'=>Carbon::now('Asia/Kolkata')]);
                $new_data['verified_at'] = Carbon::now('Asia/Kolkata');
                return $this->apiResponse->sendResponse(200,'OTP verified successfully',$new_data);
            }
            else{
                return $this->apiResponse->sendResponse(500,'unable to verify OTP',$new_data);
            }


        }
        catch(Exception $e){
            $new_data = [
                'phone'     => '',
                'message_id' => '',
                'type' => '',
                'verified_at' => ''
            ];
            return $this->apiResponse->sendResponse(500,'Internal server error',$new_data);
        }
    }
}
