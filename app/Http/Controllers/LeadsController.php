<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;

class LeadsController extends Controller
{
    // Get the 5 most recent prospects from Zoho CRM
    public function getRecentProspects()
    {
        $url =  env('ZOHO_CRM_GET_URL');

        // Zoho API Call
        $response = $this->makeZohoApiRequest('GET', $url, [
            'query' => [
                'page' => 1,
                'per_page' => 5,
                'fields' => 'Name,Email,First_Name,Mobile,DOB,Tax_File_Number,Agreed_Terms,Status'
            ]
        ]);

        $prospects = json_decode($response->getBody(), true);

        // Process the prospects and return the response
        // You can customize the response structure based on your needs
        return response()->json(['prospects' => $prospects]);
    }

    // Create a new prospect in Zoho CRM
    public function createProspect(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'First_Name' => 'required',
            'Name' => 'required',
            'Mobile' => 'required', //|regex:/^04\d{8}$/
            'Email' => 'required|email',
            'DOB' => 'required|date_format:Y-m-d',
            'Tax_File_Number' => 'required|digits:9',
            'Agreed_Terms' => 'required|in:Yes,No',
            'Status' => 'required|in:Ready For Search,New Prospect',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $url = env('ZOHO_CRM_CREATE_URL');

        $data = [
            [
                'First_Name' => $request->First_Name,
                'Name' => $request->Name,
                'Mobile' => $request->Mobile,
                'Email' => $request->Email,
                'DOB' => $request->DOB,
                'Tax_File_Number' => $request->Tax_File_Number,
                'Agreed_Terms' => $request->Agreed_Terms,
                'Status' => $request->Status
            ]
        ];

        // Make Zoho API call
        $response = $this->makeZohoApiRequest('POST', $url, [
            'json' => ['data' => $data]
        ]);

        $prospect = json_decode($response->getBody(), true);
        
        // Prepare email
        $to = 'it@truewealth.com.au';
        $subject = 'New Prospect Created';
        $message = 'A new prospect has been created in Zoho CRM.' . "\r\n";
        $message .= 'ID: ' . $prospect['data'][0]['details']['id'] . "\r\n";
        $message .= 'Name: ' . $request->First_Name . ' ' . $request->Name . "\r\n";
        $message .= 'Email: ' . $request->Email . "\r\n";
        $message .= 'Zoho CRM Link: https://crmsandbox.zoho.com.au/crm/newff/tab/CustomModule1/' . $prospect['data'][0]['details']['id'] . "\r\n";
    
        // Send Email
        Mail::raw($message, function ($email) use ($to, $subject) {
            $email->to($to)
                ->subject($subject);
        });        

        // Return the response or any additional data as needed
        return response()->json(['prospect' => $prospect]);
    }

    // Helper method to make API requests
    private function makeZohoApiRequest($method, $url, $options = [])
    {
        $client = new Client();
        $response = $client->request($method, $url, array_merge([
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . Config::get('services.zoho.crm_token'),
                'Content-Type' => 'application/json'
            ]
        ], $options));

        return $response;
    }
}
