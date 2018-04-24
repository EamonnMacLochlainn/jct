<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 24/08/2016
 * Time: 16:25
 */

namespace JCT\texter;


use Exception;

class texter_controller
{
    private $model;

    private $account_data = [
        'account_balance' => 0,
        'texts_sent_today' => 0,
        'credits_used_today' => 0
    ];

    function __construct(texter_model $model)
    {
        $this->model = $model;
    }

    function get_account_data($args)
    {
        try
        {
            $org_guid = (empty($args['org_guid'])) ? $this->model->user_org_guid : $args['org_guid'];
            $org_guid = strtoupper($org_guid);

            $tmp = $this->model->get_user_service_provider($org_guid);

            if(!empty($tmp['error']))
                throw new Exception($tmp['error']);

            if(empty($tmp['provider']))
                throw new Exception('This Organisation\'s SMS Service Provider has not been set.');

            $provider = $tmp['provider'];
            $fn = 'get_' . $provider . '_account_parameters';
            if(!method_exists($this, $fn))
                throw new Exception('No Method exists to retrieve account parameters for this Organisation\'s Service Provider (' . $tmp['provider'] . ').');

            $tmp = $this->$fn($org_guid);
            if(!empty($tmp['error']))
                throw new Exception($tmp['error']);

            $account_params = $tmp['account_parameters'];

            $fn = 'set_' . $provider . '_account_data';
            if(!method_exists($this, $fn))
                throw new Exception('No Method exists to retrieve account data for this Organisation\'s Service Provider (' . $tmp['provider'] . ').');

            $tmp = $this->$fn($account_params);
            if(!empty($tmp['error']))
                throw new Exception($tmp['error']);

            return ['success'=>'ok', 'account_data'=>$this->account_data];
        }
        catch(Exception $e)
        {
            return ['error'=>'An error occurred while retrieving Account Data: ' . $e->getMessage()];
        }
    }

    function get_neon_account_parameters($org_guid = null)
    {
        $org_guid = (is_null($org_guid)) ? $this->model->user_org_guid : $org_guid;
        $org_guid = strtoupper($org_guid);

        return $this->model->get_neon_account_parameters($org_guid);
    }

    function set_neon_account_data($account_params)
    {
        $user_name = $account_params['neon_username'];
        $user_pass = $account_params['neon_pass'];

        $response = file_get_contents('http://api.neonsolutions.ie/balance.php?user=' . $user_name . '&clipwd=' . $user_pass);
        if(empty($response))
            return ['error'=>'No response was detected for the Balance Request for this Organisation\'s Service Provider.'];

        $response_arr = explode(':', $response);
        $response_token = strtoupper($response_arr[0]);

        $acceptable_responses = ['ERR', 'OK'];
        if(!in_array($response_token, $acceptable_responses))
            return ['error'=>'The Service Provider has returned an unrecognised response.'];

        if(!isset($response_arr[1]))
            return ['error'=>'The Service Provider has returned an empty response.'];

        if($response_token == 'ERR')
            return ['error'=>'The Service Provider returned the following error: ' . $response_arr[1]];

        if(!isset($response_arr[2]))
            return ['error'=>'The Service Provider has returned an incomplete response (missing texts_sent_today).'];

        if(!isset($response_arr[3]))
            return ['error'=>'The Service Provider has returned an incomplete response (missing credits_used_today).'];

        $this->account_data['account_balance'] = floatval($response_arr[1]);
        $this->account_data['texts_sent_today'] = floatval($response_arr[2]);
        $this->account_data['credits_used_today'] = floatval($response_arr[3]);

        return ['success'=>'ok'];
    }
}