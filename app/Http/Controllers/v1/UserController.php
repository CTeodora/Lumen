<?php
/**
 * Created by PhpStorm.
 * User: iongh
 * Date: 8/1/2018
 * Time: 3:37 PM
 */

namespace App\Http\Controllers\v1;


use App\Http\Controllers\Controller;
use App\User;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


class UserController extends Controller
{
    /**
     * Login User
     *
     * @param Request $request
     * @param User $userModel
     * @param JwtToken $jwtToken
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \GenTux\Jwt\Exceptions\NoTokenException
     */
    public function login(Request $request, User $userModel, JwtToken $jwtToken)
    {
        $rules = [
            'email'    => 'required|email',
            'password' => 'required'
        ];

        $messages = [
            'email.required' => 'Email empty',
            'email.email'    => 'Email invalid',
            'password.required'    => 'Password empty'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ( ! $validator->passes()) {
            return $this->returnBadRequest();
        }

        $user = $userModel->login($request->email, $request->password);

        if ( ! $user) {
            return $this->returnNotFound('User sau parola gresite');
        }

        $token = $jwtToken->createToken($user);

        $data = [
            'user' => $user,
            'jwt'  => $token->token()
        ];

        return $this->returnSuccess($data);
    }

    public function register(Request $request, User $userModel, JwtToken $jwtToken){

        
        $rules = [
            'name'     => 'required',
            'email'    => 'required|email',
            'password' => 'required'
        ];

        $messages = [
            'name.required'  => 'Name empty',
            'email.required' => 'Email empty',
            'email.email'    => 'Email invalid',
            'password.required'    => 'Password empty'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ( ! $validator->passes()) {
            return $this->returnBadRequest();
        }
        
        $user = $userModel->create($request->name,$request->email, $request->password);

        $data = [
            'user' => $user          
        ];

        return $this->returnSuccess($data);

    }

    public function forgotPassword(Request $request, User $userModel)
    {
        if ($request->has('code')) {
            return $this->changePassword($request, $userModel);
        }
       
        $rules = [
            'email' => 'required|email|exists:user'
        ];

        $messages = [
            'email.required' => 'error.email_required',
            'email.email' => 'error.email_invalid',
            'email.exists' => 'error.email_not_registered',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        
        if (!$validator->passes()) {
            return $this->returnBadRequest($validator->messages());
        }

        $user = $userModel::where('email', $request->get('email'))->get()->first();

        if ($user->status === User::STATUS_UNCONFIRMED) {
            return $this->returnError('error.account_not_activated');
        }

        if ($user->updatedAt > Carbon::now()->subMinute()->format('Y-m-d H:i:s')) {
            return $this->returnError('error.resend_cooldown');
        }

        $user->forgotPasswordCode = strtoupper(str_random(6));
        $user->generatedForgotPassword = Carbon::now()->format('Y-m-d H:i:s');
        $user->save();
        
        Mail::send('emails.forgot', ['user' => $user], function ($message) use ($user) {
            $message->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $message->subject('Forgot password code');
            $message->to($user->email);
        });

        $user->updatedAt = Carbon::now()->format('Y-m-d H:i:s');
        $user->save();

        return $this->returnSuccess();
        
        
    }

    public function index(){
 
    	$user  = User::all();
 
    	return response()->json($user);
 
	}

    public function edit(Request $request, $id){

        $user = User::find($id);
        $userRole = $user['id_role']; 
        if($userRole == 1){                  //daca e administrator
            $userModel->updateUser($request, $id);           
        }
        else{
            if($id == auth()->user()->id){
                $userModel->updateUser($request, $id);
            }
            else
                return $this->returnError();                       
        }
    }

    public function show($id)
     {
        $user = User::find($id);
        return response()->json($user);
     }
}