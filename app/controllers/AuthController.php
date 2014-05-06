<?php
/*
=================================================
CMS Name  :  DOPTOR
CMS Version :  v1.2
Available at :  www.doptor.org
Copyright : Copyright (coffee) 2011 - 2014 Doptor. All rights reserved.
License : GNU/GPL, visit LICENSE.txt
Description :  Doptor is Opensource CMS.
===================================================
*/
class AuthController extends BaseController {

    /**
     * View for the login page
     * @return View
     */
    public function getLogin($target='admin')
    {
        if (Sentry::check()) {
            return Redirect::to($target);
        }
        $this->layout = View::make($target . '.'.$this->current_theme.'._layouts._login');
        $this->layout->title = 'Login';
        $this->layout->content = View::make($target . '.'.$this->current_theme.'.login');
    }

    /**
     * Login action
     * @return Redirect
     */
    public function postLogin($target='admin')
    {
        $input = Input::all();

        $credentials = array(
            'username' => $input['username'],
            'password' => $input['password']
        );
        $remember = ($input['remember'] == 'checked') ? true : false;
        // dd($remember);

        try {
            $user = Sentry::authenticate($credentials, $remember);

            if ($user) {
                if (isset($input['api'])) {
                    return Response::json(array(), 200);
                } else {
                    return Redirect::intended($target);
                }
            }
        } catch (Cartalyst\Sentry\Users\UserNotActivatedException $e) {
            if (isset($input['api'])) {
                return Response::json(array(
                                        'error' => 'Check your email for account activation details.'
                                        ), 200);
            } else {
                return Redirect::back()
                                    ->withErrors('Check your email for account activation details.');
            }
        } catch(Exception $e) {
            if (isset($input['api'])) {
                return Response::json(array(
                                        'error' => 'Invalid username or password'
                                        ), 200);
            } else {
                return Redirect::back()
                                    ->withErrors('Invalid username or password');
            }
        }
    }

    /**
     * Logout action
     * @return Redirect
     */
    public function getLogout()
    {
        Sentry::logout();

        return Redirect::to('/');
    }

    public function postForgotPassword()
    {
        $input = Input::all();

        $validator = User::validate_reset($input);

        if ($validator->passes()) {
            $user = User::whereEmail($input['email'])->first();

            if ($user) {
                $user = Sentry::findUserByLogin($user->username);

                $resetCode = $user->getResetPasswordCode();

                $data = array(
                            'user'      => $user,
                            'resetCode' => $resetCode
                        );

                Mail::queue('backend.'.$this->current_theme.'.reset_password_email', $data, function($message) use($input, $user) {
                    $message->from(get_setting('email_username'), Setting::value('website_name'))
                            ->to($input['email'], "{$user->first_name} {$user->last_name}")
                            ->subject('Password reset ');
                });

                return Redirect::back()
                                   ->with('success_message', 'Password reset code has been sent to your email. Follow the instructions in the email to reset your password.');
            } else {
                return Redirect::back()
                                ->with('error_message', 'No user exists with the specified email address');
            }
        } else {
            return Redirect::back()
                            ->withInput()
                            ->with('error_message', implode('<br>', $validator->messages()->get('email')));
        }
    }

    public function getResetPassword($id, $token, $target='backend')
    {
        if (Sentry::check()) {
            return Redirect::to($target);
        }
        try {
            $user = Sentry::findUserById($id);

            $this->layout = View::make($target . '.'.$this->current_theme.'._layouts._login');
            $this->layout->title = 'Reset Password';

            if ($user->checkResetPasswordCode($token)) {
                $this->layout->content = View::make($target . '.'.$this->current_theme.'.reset_password')
                                                ->with('id', $id)
                                                ->with('token', $token)
                                                ->with('target', $target);
            } else {
                $this->layout->content = View::make($target . '.'.$this->current_theme.'.reset_password')
                                                ->withErrors(array(
                                                        'invalid_reset_code'=>'The provided password reset code is invalid'
                                                    ));
            }
        } catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $this->layout->content = View::make($target . '.'.$this->current_theme.'.reset_password')
                                            ->withErrors('The specified user doesn\'t exist');
        }
    }

    public function postResetPassword()
    {
        extract(Input::all());

        try {
            $user = Sentry::findUserById($id);

            if ($user->checkResetPasswordCode($token)) {
                if ($user->attemptResetPassword($token, $password)) {
                    return Redirect::to("login/$target")
                                        ->with('success_message', 'Password reset is successful. Now you can log in with your new password');
                } else {
                    return Redirect::back()
                                    ->with('success_message', 'Password reset failed');
                }
            } else {
                return Redirect::back()
                                    ->withErrors(array(
                                            'invalid_reset_code'=>'The provided password reset code is invalid'
                                        ));
            }
        } catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {
            return Redirect::back()
                                ->with('success_message', 'The specified user doesn\'t exist');
        }
    }
}