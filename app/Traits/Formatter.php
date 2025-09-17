<?php

namespace App\Traits;

use Illuminate\Validation\ValidationException;

trait Formatter
{
    private $http_status = [
        'unauthenticated_error' => 401,
        'with_error' => 417,
        'not_found' => 204,
        'validation_error' => 422,
        'success' => 200,
        'created' => 201,
    ];

    public function withSuccess($message, $data = [])
    {
        return $this->response($message, 'success', $data);
    }
    public function unAuthenticated($message="", $data = [])
    {
        $data['data'] = [];
        $data['auth_permission'] = false;
        $data['auth_message'] = "You are not permitted for this action";
        if($message){
            return $this->response($message, 'with_error', $data);
        }
        else{
            $message = "You are not permitted for this action";
            return $this->response($message, 'with_error', $data);
        }
        
    }

    public function withNotFound($data)
    {
        return $this->response($data, 'not_found');
    }

    public function withCreated($data)
    {
        return $this->response($data, 'created');
    }
    public function withUpdated($data)
    {
        return $this->response($data, 'updated');
    }

    public function withErrors($data)
    {
        return $this->response($data, 'with_error');
    }

    public function unauthenticatedError($data)
    {
        return $this->response($data, 'unauthenticated_error');
    }

    public function validationErrorResponse(ValidationException $exception)
    {
        $messages = array_map(function ($v) {
            return $v[0];
        }, $exception->errors());

        return $this->response($messages, 'validation_error');
    }

    /**
     * formatting the message and the exception
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory | \Illuminate\Http\Response
     **/
    private function response($messages, string $status = 'success', array $data = [])
    {

        if ($status == 'validation_error') {
            return response([
                'status' => false,
                'data' => $messages,
            ], $this->http_status[$status]);
        }

        if ($status == 'created') {
            return response([
                'status' => $status == true,
                'message' => $messages,
            ]);
        }
        if (! is_string($messages) && ! count($data)) {
            return response([
                'status' => $status == 'success' ? true : false,
                'data' => $messages,
            ], $this->http_status[$status]);
        }

        return response([
            'status' => $status == 'success' ? true : false,
            'message' => $messages,
            ...$data,
        ], $this->http_status[$status]);
    }
    public function withCreatedId($data)
    {
        return $this->response($data, 'created');
    }
    public function checkPermission($user,$targetPermission){
        // return false;
        // dd($targetPermission, $user);
        if(!$user->can($targetPermission) && !$user->isAdmin()){
            return false;
        } 
        return true;              
    }
}
