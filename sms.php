<?php
require 'vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;

class Sms {
    protected $phone;
    protected $AT;

    public function __construct($phone) {
       
        $this->phone = $this->formatPhoneNumber($phone);
        
        
        $apiKey = "atsk_b5f1d4990b5f1bf5f9a071cdd2a7f76c10c76e5fa434e432f6adca0ecbbb121944922568";
        $username = "sandbox"; 
        
        try {
            error_log("Initializing Africa's Talking SDK with username: " . $username);
            $this->AT = new AfricasTalking($username, $apiKey);
            error_log("Africa's Talking SDK initialized successfully");
        } catch (Exception $e) {
            error_log("Failed to initialize Africa's Talking SDK: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
        }
    }

    private function formatPhoneNumber($phone) {
        
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        error_log("Original phone number: " . $phone);
        error_log("Formatted phone number: " . $phone);
        return $phone;
    }

    public function sendSms($message, $recipients) {
        try {
            if (!$this->AT) {
                error_log("Africa's Talking SDK not initialized");
                return false;
            }

            $sms = $this->AT->sms();
            
            
            error_log("Attempting to send SMS to: " . $recipients);
            error_log("Message content: " . $message);
            
           
            $params = [
                'to' => $recipients,
                'message' => $message,
                'from' => "HomeTutor"
            ];
            error_log("SMS request parameters: " . print_r($params, true));
            
            $result = $sms->send($params);
            
            
            error_log("Raw SMS Send Result: " . print_r($result, true));
            
            
            if (is_array($result) && isset($result['data'])) {
                $data = $result['data'];
             
                if (isset($data->SMSMessageData)) {
                    $recipients = $data->SMSMessageData->Recipients;
                    if (is_array($recipients) && count($recipients) > 0) {
                        $status = $recipients[0]->status;
                        error_log("SMS Status: " . $status);
                        return $status === "Success";
                    }
                }
            }
            
          
            error_log("Unexpected SMS response format");
            return false;
            
        } catch (Exception $e) {
            error_log("SMS Error: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function sendWelcomeMessage($name) {
        $message = "Welcome to Home Tutor Finder! Dear $name, you have successfully registered. You can now find and choose teachers for your children.";
        return $this->sendSms($message, $this->phone);
    }

    public function sendTeacherConfirmation($teacherName, $subject, $contact) {
        $message = "You have successfully chosen $teacherName as your teacher for $subject.\n";
        $message .= "Teacher's Contact: $contact\n";
        $message .= "Please contact the teacher to arrange your first session.";
        return $this->sendSms($message, $this->phone);
    }
} 