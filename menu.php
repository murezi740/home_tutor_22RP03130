<?php
require_once 'util.php';
require_once 'config.php';
require_once 'sms.php';

class Menu {
    private $conn;

    function __construct() {
        global $conn;
        if (!$conn) {
            die("Database connection not available");
        }
        $this->conn = $conn;
    }

    public function mainMenuUnregistered() {
        $response = "CON Welcome to Home Tutor Finder\n";
        $response .= "1. Register\n";
        echo $response;
    }

    public function menuRegister($textArray, $phoneNumber) {
        $level = count($textArray);

        if ($level == 1) {
            echo "CON Enter your full name\n";
        }
        else if ($level == 2) {
            echo "CON Enter number of children (optional)\n";
        }
        else if ($level == 3) {
            $name = $textArray[1];
            $children_count = $textArray[2];
            
           
            $stmt = $this->conn->prepare("INSERT INTO parents (phone_number, full_name, children_count) VALUES (?, ?, ?)");
            if (!$stmt) {
                echo "END Registration failed. Please try again later.";
                return;
            }
            
            $stmt->bind_param("ssi", $phoneNumber, $name, $children_count);
            
            if ($stmt->execute()) {
              
                $sms = new Sms($phoneNumber);
                $sms->sendWelcomeMessage($name);
                
                echo "END Dear $name, you have successfully registered to Home Tutor Finder";
            } else {
                echo "END Registration failed. Please try again later.";
            }
            $stmt->close();
        }
    }

    public function mainMenuRegistered() {
        $response = "CON Welcome to Home Tutor Finder\n";
        $response .= "1. Choose Teacher\n";
        $response .= "2. View Chosen Teachers\n";
       
        echo $response;
    }

    public function menuChooseTeacher($textArray, $phoneNumber) {
        $level = count($textArray);

        if ($level == 1) {
          
            $stmt = $this->conn->prepare("SELECT id, full_name, subject, location, contact FROM teachers ORDER BY id LIMIT 5");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $response = "CON Available Teachers:\n";
            $i = 1;
            while ($row = $result->fetch_assoc()) {
                $response .= "$i. {$row['full_name']} - {$row['subject']} ({$row['location']})\n";
                $i++;
            }
            $response .= "98. Back\n";
            $response .= "99. Main Menu\n";
            echo $response;
        }
        else if ($level == 2) {
            $menuSelection = $textArray[1];
            
           
            $stmt = $this->conn->prepare("SELECT id, full_name, subject, contact FROM teachers ORDER BY id LIMIT 5");
            $stmt->execute();
            $result = $stmt->get_result();
            $teachers = array();
            $i = 1;
            while ($row = $result->fetch_assoc()) {
                $teachers[$i] = $row;
                $i++;
            }

            if (!isset($teachers[$menuSelection])) {
                echo "END Invalid teacher selection. Please try again.";
                return;
            }

            $selectedTeacher = $teachers[$menuSelection];
            $response = "CON Are you sure you want to choose this teacher?\n";
            $response .= "1. Confirm\n";
            $response .= "2. Cancel\n";
            $response .= "98. Back\n";
            $response .= "99. Main Menu\n";
            echo $response;
        }
        else if ($level == 3) {
            if ($textArray[2] == 1) {
                $menuSelection = $textArray[1];
                
               
                $stmt = $this->conn->prepare("SELECT id, full_name, subject, contact FROM teachers ORDER BY id LIMIT 5");
                $stmt->execute();
                $result = $stmt->get_result();
                $teachers = array();
                $i = 1;
                while ($row = $result->fetch_assoc()) {
                    $teachers[$i] = $row;
                    $i++;
                }

                if (!isset($teachers[$menuSelection])) {
                    echo "END Invalid teacher selection. Please try again.";
                    return;
                }

                $selectedTeacher = $teachers[$menuSelection];
                $teacher_id = $selectedTeacher['id'];

               
                $stmt = $this->conn->prepare("SELECT id FROM parents WHERE phone_number = ?");
                $stmt->bind_param("s", $phoneNumber);
                $stmt->execute();
                if ($stmt->get_result()->num_rows == 0) {
                    echo "END Parent not found. Please register first.";
                    return;
                }

              
                $stmt = $this->conn->prepare("SELECT id FROM parent_teachers WHERE parent_phone = ? AND teacher_id = ?");
                $stmt->bind_param("si", $phoneNumber, $teacher_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    echo "END You have already selected this teacher. Please choose a different teacher.";
                    return;
                }

               
                $stmt = $this->conn->prepare("INSERT INTO parent_teachers (parent_phone, teacher_id) VALUES (?, ?)");
                $stmt->bind_param("si", $phoneNumber, $teacher_id);
                
                if ($stmt->execute()) {
                  
                    try {
                        $sms = new Sms($phoneNumber);
                        $smsResult = $sms->sendTeacherConfirmation(
                            $selectedTeacher['full_name'], 
                            $selectedTeacher['subject'],
                            $selectedTeacher['contact']
                        );
                        
                        if ($smsResult === false) {
                            error_log("Failed to send SMS to $phoneNumber for teacher selection");
                            echo "END Teacher selected successfully. You will receive an SMS with contact details shortly.";
                        } else {
                            echo "END Teacher successfully selected. You will receive an SMS with contact details shortly.";
                        }
                    } catch (Exception $e) {
                        error_log("Exception while sending SMS: " . $e->getMessage());
                        echo "END Teacher selected successfully. You will receive an SMS with contact details shortly.";
                    }
                } else {
                    echo "END Failed to select teacher. Error: " . $stmt->error;
                }
            } else if ($textArray[2] == 2) {
                echo "END Teacher selection cancelled.";
            }
        }
    }

    public function menuViewChosenTeachers($textArray, $phoneNumber) {
        $level = count($textArray);

        if ($level == 1) {
            $stmt = $this->conn->prepare("
                SELECT t.id, t.full_name, t.subject, t.location 
                FROM teachers t 
                JOIN parent_teachers pt ON t.id = pt.teacher_id 
                WHERE pt.parent_phone = ?
            ");
            $stmt->bind_param("s", $phoneNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows == 0){
                echo "END You have no chosen teachers yet.";
                return;
            }
            
            $response = "CON Your Chosen Teachers:\n";
            $teachers = array();
            $i = 1;
            while ($row = $result->fetch_assoc()) {
                $teachers[$i] = $row;
                $response .= "$i. {$row['full_name']} - {$row['subject']} ({$row['location']})\n";
                $i++;
            }
            $response .= "98. Back\n";
            $response .= "99. Main Menu\n";
            echo $response;
        }
        else if ($level == 2) {
            $menuSelection = $textArray[1];
            
            $stmt = $this->conn->prepare("
                SELECT t.id, t.full_name, t.subject, t.location 
                FROM teachers t 
                JOIN parent_teachers pt ON t.id = pt.teacher_id 
                WHERE pt.parent_phone = ?
            ");
            $stmt->bind_param("s", $phoneNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $teachers = array();
            $i = 1;
            while ($row = $result->fetch_assoc()) {
                $teachers[$i] = $row;
                $i++;
            }

            if (!isset($teachers[$menuSelection])) {
                echo "END Invalid selection. Please try again.";
                return;
            }

            $response = "CON Do you want to remove this teacher?\n";
            $response .= "1. Yes\n";
            $response .= "2. No\n";
            $response .= "98. Back\n";
            $response .= "99. Main Menu\n";
            echo $response;
        }
        else if ($level == 3) {
            if ($textArray[2] == 1) {
                $menuSelection = $textArray[1];
                
                $stmt = $this->conn->prepare("
                    SELECT t.id, t.full_name, t.subject 
                    FROM teachers t 
                    JOIN parent_teachers pt ON t.id = pt.teacher_id 
                    WHERE pt.parent_phone = ?
                ");
                $stmt->bind_param("s", $phoneNumber);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $teachers = array();
                $i = 1;
                while ($row = $result->fetch_assoc()) {
                    $teachers[$i] = $row;
                    $i++;
                }

                if (!isset($teachers[$menuSelection])) {
                    echo "END Invalid selection. Please try again.";
                    return;
                }

                $selectedTeacher = $teachers[$menuSelection];
                $teacher_id = $selectedTeacher['id'];

                $stmt = $this->conn->prepare("DELETE FROM parent_teachers WHERE parent_phone = ? AND teacher_id = ?");
                $stmt->bind_param("si", $phoneNumber, $teacher_id);
                
                if ($stmt->execute()) {
                    $sms = new Sms($phoneNumber);
                    $message = "Teacher Removal Confirmation:\n";
                    $message .= "You have removed {$selectedTeacher['full_name']} from your chosen teachers.\n";
                    $message .= "Subject: {$selectedTeacher['subject']}";
                    $sms->sendSms($message, $phoneNumber);
                    echo "END Teacher has been removed from your list.";
                } else {
                    echo "END Failed to remove teacher. Please try again.";
                }
            } else if ($textArray[2] == 2) {
                echo "END Teacher removal cancelled.";
            }
        }
    }

    public function goBack($text) {
        $xplodedText = explode("*", $text);
        while (array_search(util::$GO_BACK, $xplodedText) != false) {
            $firstIndex = array_search(util::$GO_BACK, $xplodedText);
            array_splice($xplodedText, $firstIndex - 1, 2);
        }
        return join("*", $xplodedText);
    }

    public function goToMainMenu($text) {
        $explodedText = explode("*", $text);
        while (array_search(util::$GO_TO_MAIN_MENU, $explodedText) != false) {
            $firstindex = array_search(util::$GO_TO_MAIN_MENU, $explodedText);
            $explodedText = array_slice($explodedText, $firstindex + 1);
        }
        return join("*", $explodedText);
    }

    public function middleware($text) {
        return $this->goBack($this->goToMainMenu($text));
    }
} 