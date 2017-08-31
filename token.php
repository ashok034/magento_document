<?php

if (!defined('sugarEntry'))
    define('sugarEntry', true);
require_once("include/entryPoint.php");
global $sugar_config;
$site_url = $sugar_config['site_url'];
$GLOBALS['url'] = $site_url . "/service/v4_1/rest.php";

$jsonEncodedData = file_get_contents('php://input');
$jsonDecodedData = json_decode($jsonEncodedData);

//get Token in HeaderList
$headers = apache_request_headers();
if(!empty($headers['Authorization'])){
    $headerString =  explode('bearer ',$headers['Authorization']);//explode token in header list
    $getToken =  $headerString[1];//get token
}else{
    $getToken = '';
}

if (!empty($jsonDecodedData->data->action)) {
    $action = $jsonDecodedData->data->action;
} else {
    $action = '';
}
switch ($action) {
    case "event":
        getEventData($getToken,$jsonDecodedData);
        break;
    case "token":
        $username = $jsonDecodedData->data->user_name;
        $password = $jsonDecodedData->data->password;
        generateToken($username, $password);
        break;
    default:
        noAction();
        break;
}

//function to fetch website events
function getEventData($getToken,$jsonDecodedData) {
    global $db;
    $session_id = $getToken;//$jsonDecodedData->data->token;
    $update_date = date('Y-m-d', strtotime($jsonDecodedData->data->update_date));
    $data = array();
    $data['updated_data'] = '';
    $data['insert_data'] = '';
    $data['deleted_data'] = '';
    $i = 0;

    if (!empty($session_id)) {
        if ($jsonDecodedData->data->update_date != '') {
            //sugar API code
            $get_entry_list_parameters = array(
                //session id
                'session' => $session_id,
                //The name of the module from which to retrieve records
                'module_name' => 'Camp_Activity',
                //The SQL WHERE clause without the word "where".
                'query' => "name = 'Website Event'",
                //The SQL ORDER BY clause without the phrase "order by".
                'order_by' => "",
                //The record offset from which to start.
                'offset' => '0',
                //Optional. A list of fields to include in the results.
                'select_fields' => array(
                    'id',
                    'event_name',
                    'event_desc',
                    'event_place',
                    'start_date',
                    'end_date',
                    'asset_id_c',
                    'deleted',
                    'date_entered',
                    'date_modified',
                ),
                /*
                  A list of link names and the fields to be returned for each link name.
                  Example: 'link_name_to_fields_array' => array(array('name' => 'email_addresses', 'value' => array('id', 'email_address', 'opt_out', 'primary_address')))
                 */
                'link_name_to_fields_array' => array(
                ),
                //The maximum number of results to return.
                'max_results' => '',
            );

            $get_entry_list_result = call('get_entry_list', $get_entry_list_parameters, $GLOBALS['url']);

            //echo '<pre>';
            // print_r($get_entry_list_result->entry_list);
            // echo '</pre>';
            //$eresult = array();
            if (!empty($get_entry_list_result->entry_list)) {
                foreach ($get_entry_list_result->entry_list as $index => $value_list) {
                    //echo'<pre>';  print_r($value_list->name_value_list);
                    $listed['event_id'] = $value_list->name_value_list->id->value;
                    $listed['event_title'] = $value_list->name_value_list->event_name->value;
                    $listed['event_desc'] = $value_list->name_value_list->event_desc->value;
                    $listed['event_place'] = $value_list->name_value_list->event_place->value;
                    $listed['event_start_date'] = $value_list->name_value_list->start_date->value;
                    $listed['event_end_date'] = $value_list->name_value_list->end_date->value;
                    $listed['asset_id_c'] = $value_list->name_value_list->asset_id_c->value;
                    $listed['date_entered'] = $value_list->name_value_list->date_entered->value;
                    $listed['date_modified'] = $value_list->name_value_list->date_modified->value;
                    $listed['deleted'] = $value_list->name_value_list->deleted->value;
                    // $eresult[] = $listed;

                    $sql_assets = "SELECT file_name FROM `asset_assets` WHERE id IN ('" . str_replace(",", "','", $value_list->name_value_list->asset_id_c->value) . "')";
                    $listImages = $db->query($sql_assets);
                    $arr = array();
                    while ($row_assets = $db->fetchByAssoc($listImages)) {
                        $arr[] = $row_assets['file_name'];
                    }
                    $event_list_img = isset($arr[0]) ? $arr[0] : '';
                    $event_detail_img = isset($arr[1]) ? $arr[1] : '';
                    //echo'<pre>';  print_r($arr);echo'<br/>';

                    if (strtotime(date('Y-m-d', strtotime($listed['date_entered']))) >= strtotime($update_date)) {
                        //echo 'insert='.strtotime(date('Y-m-d',strtotime($row['date_entered']))); echo '>='; echo strtotime($update_date); echo'<br/>';
                        $data['insert'][$i]['event_id'] = $listed['event_id'];
                        $data['insert'][$i]['event_title'] = $listed['event_title'];
                        $data['insert'][$i]['event_desc'] = $listed['event_desc'];
                        $data['insert'][$i]['event_place'] = $listed['event_place'];
                        $data['insert'][$i]['event_start_date'] = $listed['event_start_date'];
                        $data['insert'][$i]['event_end_date'] = $listed['event_end_date'];
                        $data['insert'][$i]['event_list_img_url'] = $event_list_img;
                        $data['insert'][$i]['event_detail_img_url'] = $event_detail_img;
                        $data['insert_data'][] = $data['insert'][$i]; //insert_data
                    } elseif (((strtotime(date('Y-m-d', strtotime($listed['date_modified']))) >= strtotime($update_date)) && $listed['deleted'] == 1)) {
                        //echo 'updated='.strtotime(date('Y-m-d',strtotime($row['date_modified']))); echo '>='; echo strtotime($update_date); echo'<br/>';
                        $data['delete'][$i]['event_id'] = $listed['event_id'];
                        $data['delete'][$i]['event_title'] = $listed['event_title'];
                        $data['delete'][$i]['event_desc'] = $listed['event_desc'];
                        $data['delete'][$i]['event_place'] = $listed['event_place'];
                        $data['delete'][$i]['event_start_date'] = $listed['event_start_date'];
                        $data['delete'][$i]['event_end_date'] = $listed['event_end_date'];
                        $data['delete'][$i]['event_list_img_url'] = $event_list_img;
                        $data['delete'][$i]['event_detail_img_url'] = $event_detail_img;
                        $data['deleted_data'][] = $data['delete'][$i]; //deleted_data
                    } elseif (strtotime(date('Y-m-d', strtotime($listed['date_modified']))) >= strtotime($update_date)) {
                        //echo 'updated='.strtotime(date('Y-m-d',strtotime($row['date_modified']))); echo '>='; echo strtotime($update_date); echo'<br/>';
                        $data['update'][$i]['event_id'] = $listed['event_id'];
                        $data['update'][$i]['event_title'] = $listed['event_title'];
                        $data['update'][$i]['event_desc'] = $listed['event_desc'];
                        $data['update'][$i]['event_place'] = $listed['event_place'];
                        $data['update'][$i]['event_start_date'] = $listed['event_start_date'];
                        $data['update'][$i]['event_end_date'] = $listed['event_end_date'];
                        $data['update'][$i]['event_list_img_url'] = $event_list_img;
                        $data['update'][$i]['event_detail_img_url'] = $event_detail_img;
                        $data['updated_data'][] = $data['update'][$i]; //updated_data
                    }
                    $i++;
                }
                // echo '<pre>'; print_r($data['insert_data']);
                // die;
                //End sugar API code


                if (!empty($data['insert_data']) || !empty($data['updated_data']) || !empty($data['deleted_data'])) {
                    $return_data = array(
                        "data" => array(
                            "insert_data" => $data['insert_data'],
                            "updated_data" => $data['updated_data'],
                            "deleted_data" => $data['deleted_data'],
                            "update_date" => date('Y-m-d H:i:s'),
                        ),
                        "error" => array("error_code" => 0, "error_msg" => "Success")
                    );
                } else {
                    $return_data = array(
                        "data" => array(),
                        "error" => array("error_code" => 1, "error_msg" => "Data not found.")
                    );
                }
            } else {
                $return_data = array(
                    "data" => array(),
                    "error" => array("error_code" => 1, "error_msg" => "Token not match.")
                );
            }
        } else {
            $return_data = array(
                "data" => array(),
                "error" => array("error_code" => 1, "error_msg" => "Update date not Received.")
            );
        }
    } else {
        $return_data = array(
            "data" => array(),
            "error" => array("error_code" => 1, "error_msg" => "Token not Received.")
        );
    }
    echo json_encode($return_data);
}

//function to genrate session id as a token
function generateToken($username, $password) {
    if (!empty($username) && !empty($password)) {
        $login_parameters = array(
            "user_auth" => array(
                "user_name" => $username,
                "password" => md5($password),
                "version" => "1"
            ),
            "application_name" => "RestTest",
            "name_value_list" => array(),
        );

        $login_result = call("login", $login_parameters, $GLOBALS['url']);
        //get session id
        if (!empty($login_result->id)) {
            $session_id = $login_result->id;
            $return_data = array(
                "data" => array("authentication_token" => $session_id),
                "error" => array("error_code" => 0, "error_msg" => "Succees.")
            );
        } else {
            $return_data = array(
                "data" => array(),
                "error" => array("error_code" => 1, "error_msg" => "Credential mismatch.")
            );
        }
    } else {
        $return_data = array(
            "data" => array(),
            "error" => array("error_code" => 1, "error_msg" => "Credential missing.")
        );
    }
    echo json_encode($return_data);
}

//function to make cURL request
function call($method, $parameters, $url) {
    ob_start();
    $curl_request = curl_init();

    curl_setopt($curl_request, CURLOPT_URL, $url);
    curl_setopt($curl_request, CURLOPT_POST, 1);
    curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($curl_request, CURLOPT_HEADER, 1);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);

    $jsonEncodedData = json_encode($parameters);

    $post = array(
        "method" => $method,
        "input_type" => "JSON",
        "response_type" => "JSON",
        "rest_data" => $jsonEncodedData
    );

    curl_setopt($curl_request, CURLOPT_POSTFIELDS, $post);
    $result = curl_exec($curl_request);
    curl_close($curl_request);

    $result = explode("\r\n\r\n", $result, 2);
    $response = json_decode($result[1]);
    ob_end_flush();

    return $response;
}

//function to make check action available or not
function noAction() {
    $return_data = array(
        "data" => array(),
        "error" => array("error_code" => 1, "error_msg" => "Method not found.")
    );
    echo json_encode($return_data);
}