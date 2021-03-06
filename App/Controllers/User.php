<?php

namespace AWorDS\App\Controllers;

use \AWorDS\App\Constants;
use AWorDS\Config;

class User extends Controller
{
    public function register(){
        extract($this->_params);
        /**
         * @var string $name
         * @var string $email
         * @var string $pass
         */
        $this->set_model();
        if(empty($name) OR empty($email) OR empty($pass)) $status = Constants::SHORTAGE_OF_ARGUMENTS;
        else $status = $this->{$this->_model}->register($name, $email, $pass);
        switch($status){
            case Constants::ACCOUNT_EXISTS:
                $_SESSION['register_error'] = "<strong>Account already exists!</strong> There's already an account associated with this account. If this email is really yours, <a href=\"reset_pass\">reset your password</a>.";
                break;
            case Constants::REGISTER_FAILURE:
                $_SESSION['register_error'] = "<strong>Failed!</strong> Account creation failed due to technical difficulties, please try again.";
                break;
            case Constants::SHORTAGE_OF_ARGUMENTS:
                $_SESSION['register_error'] = "<strong>Failed!</strong> You need to provide your name, email and password (ie. fill up all the boxes) to register.";
        }
        if($status == Constants::LOGIN_SUCCESS){
            $_SESSION['register_success'] = true;
            $this->redirect(Config::WEB_DIRECTORY . 'register_success');
        }else $this->redirect();
    }
    
    public function register_success(){
        if(!isset($_SESSION['register_success'])){
            $this->redirect();
        }
        unset($_SESSION['register_success']);
        // else load the GUI
    }
    
    public function login(){
        extract($this->_params);
        /**
         * @var string $email
         * @var string $pass
         */
        $this->set_model();
        /**
         * @var \AWorDS\App\Models\User $user
         */
        $user = $this->{$this->_model};
        // Go home if already logged in
        if($user->login_check()){
            $this->redirect();
            exit();
        }
        // First check the parameters
        if(empty($email) OR empty($pass)) $status = Constants::SHORTAGE_OF_ARGUMENTS;
        else $status = $this->{$this->_model}->login($email, $pass);
        switch($status){
            case Constants::LOGIN_LOCKED:
                $_SESSION['login_error'] = "<strong>Your account is locked!</strong> An email was sent to your account, use that email to unlock your account.";
                break;
            case Constants::LOGIN_FAILURE:
                $_SESSION['login_error'] = "<strong>Login failed!</strong> Please try again with your email and password or create an account if you don't have one.";
                break;
            case Constants::SHORTAGE_OF_ARGUMENTS:
                $_SESSION['login_error'] = "<strong>Login failed!</strong> You need to provide both email and password to login.";
        }
        // Redirect to homepage or login page based on criteria
        $this->redirect(Config::WEB_DIRECTORY . ($status == Constants::LOGIN_SUCCESS ? '' : 'login'));
    }
    
    public function login_page(){
        extract($this->_params);
        /**
         * @var string $email
         */
        $this->set_model();
        /**
         * @var \AWorDS\App\Models\User $user
         */
        $user = $this->{$this->_model};
        // Go home if already logged in
        if($user->login_check()){
            $this->redirect();
            exit();
        }

        $this->set('email', isset($email) ? $email : '');
    }
    
    public function logout(){
        $this->set_model();
        $this->{$this->_model}->logout();
        $this->redirect();
    }
    
    public function unlock(){
        $this->set_model();
        extract($this->_params);
        /**
         * @var string $email
         * @var string $key
         */
        $this->set('is_unlocked', $this->{$this->_model}->unlock($email, $key));
        $this->set('email', $email);
    }
    
    public function reset_password(){
        extract($this->_params);
        /**
         * @var string $email
         * @var string $pass
         */
        $this->set_model();/**
         * @var \AWorDS\App\Models\User $user
         */
        $user = $this->{$this->_model};
        if(empty($pass)){    // If only email is provided, send an activation code to the email
            $user->email_reset_request($email);
            $this->set('alert_type', 'request');
        }else{              // If email and password are provided, save password, provide a notification and redirect to the login page.
            if(isset($_SESSION['valid_reset_request'])){
                $user->reset_password($_SESSION['reset_email'], $pass);
                $this->set('alert_type', 'reset');
            }else $this->redirect(Config::WEB_DIRECTORY . 'reset_pass');
        }
    }
    
    public function reset_password_page(){
        extract($this->_params);
        /**
         * @var string $email
         * @var string $key
         */
        $this->set_model();
        /**
         * @var \AWorDS\App\Models\User $user
         */
        $user = $this->{$this->_model};
        $logged_in = $user->login_check();
        if(empty($key) AND !$logged_in){ // load the password reset request form
            $form_type = 'request';
        }else{                           // load the password reset form if the reset request is valid
            if($user->valid_reset_request($email, $key) OR $logged_in){
                if($logged_in) $email = $user->get_email();
                $_SESSION['valid_reset_request'] = true;
                $_SESSION['reset_email'] = $email;
                $form_type = 'reset';
            }else{
                $form_type = 'request';
            }
        }
        $this->set('form_type', $form_type);
        $this->set('email', $email);
    }
}