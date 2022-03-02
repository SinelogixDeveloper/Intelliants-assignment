<?php

namespace App\Console\Commands;

use App\Models\UserDetailsModel;
use Illuminate\Console\Command;
use Config;

class StoreUsersDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:insert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store new users from dummyapi.io to user_details table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $app_id = Config::get('app.APP_ID');
        $headerAccept = ['accept: application/json', 'content-type: application/json', 'app-id: ' . $app_id];
        $get_api_users = $this->curlApiRequest('GET', 'https://dummyapi.io/data/v1/user', null, [], $headerAccept);
        
        if(isset($get_api_users['status']) && $get_api_users['status']){ // User Details found for store and update
            $api_user_decode = json_decode($get_api_users['data'],true);
            foreach($api_user_decode['data'] as $val_user ) {                                 
                UserDetailsModel::updateOrCreate(
                    ['id' => $val_user['id']],
                    ['id' => $val_user['id'],'title' => $val_user['title'],'firstName' => $val_user['firstName'],'lastName' => $val_user['lastName'],'picture' => $val_user['picture']]
                );            
            } // Loops Ends
            echo "User Details Operations Insert and Updates are done successfully...! \n";
            \Log::channel('user_insert_logs')->info('User Details Operations Insert and Updates are done successfully...!');
        }else{ // No User details found
            echo "User Details not found from Dummyapi...! \n";
            \Log::channel('user_insert_logs')->info('User Details not found from Dummyapi...!');            
        }
        $deleted_users = $this->delete_user_details();
        \Log::channel('user_insert_logs')->info('User Details Deleted Report :: !'.json_encode($deleted_users));
        echo "All Operations are performed...! \n";
        exit;
    }

   /*
   * @params : apiMethod = GET / POST
   * @params : apiUrl = url name which we have to call
   * @params : apiUrlAddtionalParams = url additional parameters ?myparam1={id1}&myparam2={id2}
   * @params : postData = ['data1'=> 123,'data2'=> 345] // data which we need to send to curl server
   * @params : headerAccept = ['accept: *//*','Authorization: Bearer ' . this->tokenCode;,'accept-language: en-US,en;q=0.8','content-type: application/json']
   * @example : multi_arr = array(array('foo', 'bar'), array('baz', 'qux'));
   * @return  : echo in_array_r('baz', multi_array) ? 'found' : 'not found';
   */
    function curlApiRequest($apiMethod = 'GET', $apiUrl = '', $apiUrlAddtionalParams = '', $postData = [], $headerAccept = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        // Set Curl Request URL
        if (isset($apiUrl) && (!empty($apiUrl) && empty($apiUrlAddtionalParams)))
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
        else
            curl_setopt($ch, CURLOPT_URL, $apiUrl . $apiUrlAddtionalParams);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        // Post Method Setup and Bind PostData
        if ((strtolower($apiMethod) == 'post') && (is_array($postData) && sizeof($postData) > 0)) {
            curl_setopt($ch, CURLOPT_POST, true);
            // Enable / Uncomment any of PostData Options
            // curl_setopt(ch, CURLOPT_POSTFIELDS, http_build_query(postData));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }
        if (is_array($headerAccept) && sizeof($headerAccept) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerAccept);
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/json'));
        }
        $response = curl_exec($ch); // Curl Execute
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
        }
        curl_close($ch); // Curl Close
        if (isset($error_msg)) { // Curl Error Triggers - Handle cURL error accordingly
            $resp['status'] = false;
            $resp['message'] = 'Fail, Curl has fail to do your operations..!';
            $resp['curl_error_message'] = $error_msg;
            $resp['data'] = [];
        } else {
            $resp['status'] = true;
            $resp['message'] = 'Success, Curl has successfully done your operations..!';
            $resp['curl_error_message'] = [];
            $resp['data'] = $response;
        }

        return $resp;
    }

    /* 
     *  Delete existing user_details data over 90 days previous
     */
    public function delete_user_details() {
        try {   
            $user_details_delete_list = UserDetailsModel::where('created_at','<',\Carbon\Carbon::today()->subDays(91))
            ->pluck('id')->toArray();          
            if(isset($user_details_delete_list) && !empty($user_details_delete_list)){                
                UserDetailsModel::whereIn('id', $user_details_delete_list)->delete();
                return [
                'status' => true,
                'data' => [],
                'message' => 'User detail deleted successfully..!'
                ];
            }else{
                throw new \Exception("No User details are found before ".date("d-m-Y",strtotime(\Carbon\Carbon::today()->subDays(91)))." days...!", 403); 
            }            
        } catch (\Exception $e) {
            return [
                'status' => false,
                'data' => [],
                'message' => $e->getMessage()
                ];
        }   
    }
}
